<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdministracionAccesosController extends Controller
{
    private const PERMISO_PROTEGIDO = 'usuarios.administrar';

    private const ROLES_PROTEGIDOS = ['administrador', 'asesor', 'cliente'];

    public function __construct()
    {
        $this->middleware('can:'.self::PERMISO_PROTEGIDO);
        $this->middleware('can:usuarios.crear')->only('storeUser');
    }

    public function index(Request $request)
    {
        $buscar = trim((string) $request->input('buscar', ''));
        $rolId = $request->integer('rol') > 0 && Role::whereKey($request->integer('rol'))->exists()
            ? $request->integer('rol')
            : null;
        $abrir = $request->input('abrir') === 'crear' ? 'crear' : null;
        $usuarios = User::query()
            ->with(['roles:id,name', 'permissions:id,name'])
            ->when($buscar, fn ($query) => $query->where(function ($subquery) use ($buscar) {
                $subquery->where('name', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%");
            }))
            ->when($rolId, fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->whereKey($rolId)))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $usuario) => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'roles' => $usuario->roles->map->only(['id', 'name'])->values(),
                'permisos_directos' => $usuario->permissions->map->only(['id', 'name'])->values(),
                'permisos_efectivos' => $usuario->getAllPermissions()->pluck('name')->sort()->values(),
                'es_actual' => $usuario->is($request->user()),
            ]);

        $roles = Role::query()->with('permissions:id,name')->withCount('users')->orderBy('name')->get()
            ->map(fn (Role $rol) => [
                'id' => $rol->id,
                'name' => $rol->name,
                'permissions' => $rol->permissions->map->only(['id', 'name'])->values(),
                'users_count' => $rol->users_count,
                'protegido' => in_array(mb_strtolower($rol->name), self::ROLES_PROTEGIDOS, true),
            ]);
        $conteosDirectos = DB::table(config('permission.table_names.model_has_permissions'))
            ->selectRaw('permission_id, COUNT(*) total')->groupBy('permission_id')->pluck('total', 'permission_id');
        $permisos = Permission::query()->withCount('roles')->orderBy('name')->get()
            ->map(fn (Permission $permiso) => [
                'id' => $permiso->id,
                'name' => $permiso->name,
                'roles_count' => $permiso->roles_count,
                'users_count' => (int) ($conteosDirectos[$permiso->id] ?? 0),
                'protegido' => $permiso->name === self::PERMISO_PROTEGIDO,
            ]);

        return Inertia::render('Administracion/Accesos', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'permisos' => $permisos,
            'filtros' => ['buscar' => $buscar, 'rol' => $rolId ? (string) $rolId : '', 'abrir' => $abrir],
            'resumen' => [
                'usuarios' => User::count(),
                'roles' => $roles->count(),
                'permisos' => $permisos->count(),
                'administradores' => $this->cantidadAdministradores(),
            ],
        ]);
    }

    public function storeUser(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists(config('permission.table_names.roles'), 'id')],
        ]);

        DB::transaction(function () use ($datos) {
            $usuario = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['password']),
            ]);
            $usuario->syncRoles(Role::whereIn('id', collect($datos['roles'])->unique())->get());
        });
        $this->limpiarCache();

        return back()->with('success', 'Usuario creado y roles asignados correctamente.');
    }

    public function storePermission(Request $request)
    {
        $datos = $this->validarNombrePermiso($request);
        Permission::create(['name' => $datos['name'], 'guard_name' => 'web']);
        $this->limpiarCache();

        return back()->with('success', 'Permiso creado correctamente.');
    }

    public function updatePermission(Request $request, Permission $permission)
    {
        $this->protegerPermiso($permission);
        $datos = $this->validarNombrePermiso($request, $permission->id);
        $permission->update(['name' => $datos['name']]);
        $this->limpiarCache();

        return back()->with('success', 'Permiso actualizado correctamente.');
    }

    public function destroyPermission(Permission $permission)
    {
        $this->protegerPermiso($permission);
        $permission->delete();
        $this->limpiarCache();

        return back()->with('success', 'Permiso eliminado correctamente.');
    }

    public function storeRole(Request $request)
    {
        $datos = $this->validarNombreRol($request);
        Role::create(['name' => $datos['name'], 'guard_name' => 'web']);
        $this->limpiarCache();

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->protegerRol($role);
        $datos = $this->validarNombreRol($request, $role->id);
        $role->update(['name' => $datos['name']]);
        $this->limpiarCache();

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroyRole(Role $role)
    {
        $this->protegerRol($role);
        $role->delete();
        $this->limpiarCache();

        return back()->with('success', 'Rol eliminado correctamente.');
    }

    public function syncRolePermissions(Request $request, Role $role)
    {
        $datos = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists(config('permission.table_names.permissions'), 'id')],
        ]);
        $ids = collect($datos['permissions'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        if (mb_strtolower($role->name) === 'administrador') {
            $permisoAdministrar = Permission::findByName(self::PERMISO_PROTEGIDO, 'web');
            $ids->push($permisoAdministrar->id);
        }
        $role->syncPermissions(Permission::whereIn('id', $ids->unique())->get());
        $this->limpiarCache();

        return back()->with('success', "Permisos del rol {$role->name} actualizados.");
    }

    public function syncUserAccess(Request $request, User $user)
    {
        $datos = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists(config('permission.table_names.roles'), 'id')],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists(config('permission.table_names.permissions'), 'id')],
        ]);
        $roles = Role::whereIn('id', collect($datos['roles'] ?? [])->unique())->get();
        $permisos = Permission::whereIn('id', collect($datos['permissions'] ?? [])->unique())->get();

        $this->validarAdministradorRestante($user, $roles);
        if ($user->is($request->user()) && ! $this->conservaraAccesoAdministrativo($roles, $permisos)) {
            throw ValidationException::withMessages([
                'roles' => 'No puedes quitarte tu propio acceso a la administración de usuarios.',
            ]);
        }

        DB::transaction(function () use ($user, $roles, $permisos) {
            $user->syncRoles($roles);
            $user->syncPermissions($permisos);
        });
        $this->limpiarCache();

        return back()->with('success', "Accesos de {$user->name} actualizados.");
    }

    private function validarNombrePermiso(Request $request, ?int $ignorar = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:125', 'regex:/^[a-z0-9._-]+$/', Rule::unique(config('permission.table_names.permissions'), 'name')->ignore($ignorar)],
        ], ['name.regex' => 'Usa únicamente minúsculas, números, puntos, guiones y guiones bajos.']);
    }

    private function validarNombreRol(Request $request, ?int $ignorar = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:125', 'regex:/^[a-z0-9._-]+$/', Rule::unique(config('permission.table_names.roles'), 'name')->ignore($ignorar)],
        ], ['name.regex' => 'Usa únicamente minúsculas, números, puntos, guiones y guiones bajos.']);
    }

    private function protegerPermiso(Permission $permission): void
    {
        if ($permission->name === self::PERMISO_PROTEGIDO) {
            throw ValidationException::withMessages(['permission' => 'El permiso de administración está protegido.']);
        }
    }

    private function protegerRol(Role $role): void
    {
        if (in_array(mb_strtolower($role->name), self::ROLES_PROTEGIDOS, true)) {
            throw ValidationException::withMessages(['role' => 'Los roles base del sistema no se pueden renombrar ni eliminar.']);
        }
    }

    private function validarAdministradorRestante(User $user, $roles): void
    {
        $eraAdministrador = $user->roles()->whereRaw('LOWER(name) = ?', ['administrador'])->exists();
        $seguiraSiendoAdministrador = $roles->contains(fn (Role $rol) => mb_strtolower($rol->name) === 'administrador');
        if ($eraAdministrador && ! $seguiraSiendoAdministrador && $this->cantidadAdministradores() <= 1) {
            throw ValidationException::withMessages(['roles' => 'Debe existir al menos un usuario con el rol administrador.']);
        }
    }

    private function conservaraAccesoAdministrativo($roles, $permisos): bool
    {
        if ($permisos->contains('name', self::PERMISO_PROTEGIDO)) {
            return true;
        }

        return $roles->contains(fn (Role $rol) => $rol->permissions()->where('name', self::PERMISO_PROTEGIDO)->exists());
    }

    private function cantidadAdministradores(): int
    {
        return User::query()->whereHas('roles', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['administrador']))->count();
    }

    private function limpiarCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
