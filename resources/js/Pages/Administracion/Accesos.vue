<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import FormSelect from '@/Pages/Pedidos/Components/FormSelect.vue';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';
import SectionCard from '@/Pages/Pedidos/Components/SectionCard.vue';

const props = defineProps({
    usuarios: Object,
    roles: Array,
    permisos: Array,
    filtros: Object,
    resumen: Object,
});
const page = usePage();

const tab = ref('usuarios');
const busqueda = ref(props.filtros?.buscar ?? '');
const rolFiltro = ref(props.filtros?.rol ?? '');
const modalUsuario = ref(false);
const modalRol = ref(false);
const modalPermiso = ref(false);
const modalEditarRol = ref(false);
const modalCrearUsuario = ref(props.filtros?.abrir === 'crear');
const usuarioSeleccionado = ref(null);
const rolSeleccionado = ref(null);
const permisoSeleccionado = ref(null);

const nuevoRol = useForm({ name: '' });
const nuevoPermiso = useForm({ name: '' });
const editarRol = useForm({ name: '' });
const editarPermiso = useForm({ name: '' });
const accesosUsuario = useForm({ roles: [], permissions: [] });
const permisosRol = useForm({ permissions: [] });
const crearUsuario = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
});

const tarjetas = computed(() => [
    ['Usuarios', props.resumen.usuarios, 'fa-users', 'bg-red-100 text-red-600'],
    ['Administradores', props.resumen.administradores, 'fa-user-shield', 'bg-amber-100 text-amber-700'],
    ['Roles', props.resumen.roles, 'fa-id-badge', 'bg-red-100 text-red-700'],
    ['Permisos', props.resumen.permisos, 'fa-key', 'bg-amber-100 text-amber-700'],
]);
const puedeCrearUsuarios = computed(() => page.props.auth?.permissions?.includes('usuarios.crear'));

const permisosAgrupados = computed(() => {
    const grupos = {};
    props.permisos.forEach((permiso) => {
        const grupo = permiso.name.split('.')[0] || 'otros';
        (grupos[grupo] ??= []).push(permiso);
    });
    return grupos;
});

const consultar = () => router.get(route('administracion.accesos.index'), {
    buscar: busqueda.value,
    rol: rolFiltro.value,
}, { preserveState: true, replace: true });
const limpiarBusqueda = () => {
    busqueda.value = '';
    rolFiltro.value = '';
    consultar();
};
const crearRol = () => nuevoRol.post(route('administracion.accesos.roles.store'), {
    preserveScroll: true,
    onSuccess: () => nuevoRol.reset(),
});
const crearPermiso = () => nuevoPermiso.post(route('administracion.accesos.permisos.store'), {
    preserveScroll: true,
    onSuccess: () => nuevoPermiso.reset(),
});
const guardarNuevoUsuario = () => crearUsuario.post(route('administracion.accesos.usuarios.store'), {
    preserveScroll: true,
    onSuccess: () => {
        modalCrearUsuario.value = false;
        crearUsuario.reset();
    },
});

const abrirUsuario = (usuario) => {
    usuarioSeleccionado.value = usuario;
    accesosUsuario.roles = usuario.roles.map((rol) => rol.id);
    accesosUsuario.permissions = usuario.permisos_directos.map((permiso) => permiso.id);
    accesosUsuario.clearErrors();
    modalUsuario.value = true;
};
const guardarUsuario = () => accesosUsuario.put(route('administracion.accesos.usuarios.update', usuarioSeleccionado.value.id), {
    preserveScroll: true,
    onSuccess: () => { modalUsuario.value = false; },
});

const abrirRol = (rol) => {
    rolSeleccionado.value = rol;
    permisosRol.permissions = rol.permissions.map((permiso) => permiso.id);
    permisosRol.clearErrors();
    modalRol.value = true;
};
const guardarPermisosRol = () => permisosRol.put(route('administracion.accesos.roles.permisos', rolSeleccionado.value.id), {
    preserveScroll: true,
    onSuccess: () => { modalRol.value = false; },
});
const editarNombreRol = (rol) => {
    rolSeleccionado.value = rol;
    editarRol.name = rol.name;
    editarRol.clearErrors();
    modalEditarRol.value = true;
};
const guardarNombreRol = () => editarRol.patch(route('administracion.accesos.roles.update', rolSeleccionado.value.id), {
    preserveScroll: true,
    onSuccess: () => { modalEditarRol.value = false; rolSeleccionado.value = null; },
});

const editarNombrePermiso = (permiso) => {
    permisoSeleccionado.value = permiso;
    editarPermiso.name = permiso.name;
    editarPermiso.clearErrors();
    modalPermiso.value = true;
};
const guardarNombrePermiso = () => editarPermiso.patch(route('administracion.accesos.permisos.update', permisoSeleccionado.value.id), {
    preserveScroll: true,
    onSuccess: () => { modalPermiso.value = false; permisoSeleccionado.value = null; },
});

const confirmarEliminar = async (tipo, item) => {
    const resultado = await Swal.fire({
        title: `¿Eliminar ${tipo}?`,
        text: tipo === 'rol'
            ? `El rol “${item.name}” se quitará de ${item.users_count} usuario(s).`
            : `El permiso “${item.name}” se quitará de todos los roles y usuarios.`,
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
    });
    if (!resultado.isConfirmed) return;
    router.delete(route(`administracion.accesos.${tipo === 'rol' ? 'roles' : 'permisos'}.destroy`, item.id), { preserveScroll: true });
};

watch(() => props.filtros?.buscar, (valor) => { busqueda.value = valor ?? ''; });
watch(() => props.filtros?.rol, (valor) => { rolFiltro.value = valor ?? ''; });
watch(() => page.props.flash?.success, (mensaje) => {
    if (!mensaje) return;
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: mensaje, showConfirmButton: false, timer: 2200 });
});
</script>

<template>
    <Head title="Usuarios, roles y permisos" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">🛡️ Usuarios, roles y permisos</h2>
                <p class="mt-1 text-sm text-gray-500">Administra quién puede acceder a cada función del sistema.</p>
            </div>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="tarjeta in tarjetas" :key="tarjeta[0]" class="rounded-xl border border-gray-100 bg-white p-5 shadow">
                        <div class="flex items-center justify-between"><div><p class="text-sm text-gray-500">{{ tarjeta[0] }}</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ tarjeta[1] }}</p></div><div :class="tarjeta[3]" class="flex h-12 w-12 items-center justify-center rounded-full"><i :class="['fa-solid', tarjeta[2], 'text-xl']"></i></div></div>
                    </article>
                </section>

                <div class="flex flex-wrap gap-2 rounded-xl border border-red-100 bg-gradient-to-r from-white via-red-50 to-amber-50 p-2 shadow-sm">
                    <button v-for="opcion in [['usuarios','Usuarios','fa-users'],['roles','Roles','fa-id-badge'],['permisos','Permisos','fa-key']]" :key="opcion[0]" type="button" @click="tab = opcion[0]" :class="tab === opcion[0] ? 'bg-gradient-to-r from-red-600 to-amber-500 text-white shadow-md' : 'text-gray-600 hover:bg-white'" class="rounded-lg px-4 py-2.5 text-sm font-bold transition"><i :class="['fa-solid', opcion[2], 'mr-2']"></i>{{ opcion[1] }}</button>
                </div>

                <SectionCard v-if="tab === 'usuarios'" title="Accesos por usuario" description="Asigna uno o varios roles y permisos excepcionales directamente a cada usuario." icon="fa-solid fa-users-gear text-red-500">
                    <template #actions><div class="flex flex-col gap-2 sm:flex-row"><div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-800"><i class="fa-solid fa-circle-info mr-2"></i>Los permisos directos complementan los del rol.</div><SecondaryButton v-if="puedeCrearUsuarios" type="button" @click="modalCrearUsuario = true"><i class="fa-solid fa-user-plus mr-2"></i>Agregar usuario</SecondaryButton></div></template>
                    <form class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_minmax(220px,320px)_auto]" @submit.prevent="consultar">
                        <div><InputLabel for="buscar-usuario-acceso" value="Buscar usuario" /><TextInput id="buscar-usuario-acceso" v-model="busqueda" class="mt-1 block w-full" placeholder="Nombre o correo electrónico..." /></div>
                        <FormSelect id="filtrar-usuarios-rol" v-model="rolFiltro" label="Filtrar por rol" icon="fa-solid fa-id-badge" @change="consultar"><option value="">Todos los roles</option><option v-for="rol in roles" :key="rol.id" :value="String(rol.id)">{{ rol.name }} ({{ rol.users_count }})</option></FormSelect>
                        <div class="flex items-end gap-2"><PrimaryButton><i class="fa-solid fa-magnifying-glass mr-2"></i>Buscar</PrimaryButton><SecondaryButton type="button" @click="limpiarBusqueda"><i class="fa-solid fa-eraser mr-2"></i>Limpiar</SecondaryButton></div>
                    </form>
                    <div class="overflow-x-auto rounded-lg shadow"><table class="min-w-full border border-gray-200 text-sm"><thead><tr class="border-b bg-gray-100"><th class="px-4 py-3 text-left">Usuario</th><th class="px-4 py-3 text-left">Roles</th><th class="px-4 py-3 text-left">Permisos directos</th><th class="px-4 py-3 text-left">Permisos efectivos</th><th class="sticky right-0 bg-gray-100 px-4 py-3 text-center">Acciones</th></tr></thead><tbody><tr v-for="usuario in usuarios.data" :key="usuario.id" class="border-b hover:bg-gray-100"><td class="px-4 py-3"><p class="font-bold text-gray-900">{{ usuario.name }} <span v-if="usuario.es_actual" class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">Tú</span></p><p class="text-xs text-gray-500">{{ usuario.email }}</p></td><td class="px-4 py-3"><div class="flex flex-wrap gap-1"><span v-for="rol in usuario.roles" :key="rol.id" class="rounded-lg bg-gradient-to-r from-red-100 to-amber-100 px-2.5 py-1 text-xs font-bold text-red-900">{{ rol.name }}</span><span v-if="!usuario.roles.length" class="text-xs text-gray-400">Sin rol</span></div></td><td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-700">{{ usuario.permisos_directos.length }}</span></td><td class="px-4 py-3"><span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-bold text-green-800">{{ usuario.permisos_efectivos.length }}</span></td><td class="sticky right-0 bg-white px-4 py-3 text-center"><PrimaryButton type="button" @click="abrirUsuario(usuario)"><i class="fa-solid fa-user-pen mr-2"></i>Administrar</PrimaryButton></td></tr><tr v-if="!usuarios.data.length"><td colspan="5" class="px-6 py-14 text-center text-gray-500">No se encontraron usuarios.</td></tr></tbody></table></div>
                    <Pagination :links="usuarios.links" />
                </SectionCard>

                <div v-else-if="tab === 'roles'" class="space-y-6">
                    <SectionCard title="Crear rol" description="Crea un perfil de acceso reutilizable para varios usuarios." icon="fa-solid fa-circle-plus text-red-500">
                        <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="crearRol"><div class="flex-1"><InputLabel for="nuevo-rol" value="Nombre técnico del rol" /><TextInput id="nuevo-rol" v-model="nuevoRol.name" class="mt-1 block w-full" placeholder="ejemplo: supervisor" /><InputError class="mt-2" :message="nuevoRol.errors.name" /></div><PrimaryButton :disabled="nuevoRol.processing"><i class="fa-solid fa-plus mr-2"></i>Crear rol</PrimaryButton></form>
                    </SectionCard>
                    <SectionCard title="Roles disponibles" description="Configura los permisos que heredan los usuarios asignados." icon="fa-solid fa-id-badge text-amber-500">
                        <div class="grid gap-4 lg:grid-cols-2"><article v-for="rol in roles" :key="rol.id" class="rounded-2xl border border-red-100 bg-gradient-to-br from-white via-red-50/40 to-amber-50 p-5 shadow-sm"><div class="flex items-start justify-between gap-3"><div><div class="flex items-center gap-2"><h3 class="text-lg font-bold text-gray-900">{{ rol.name }}</h3><span v-if="rol.protegido" class="rounded-full bg-gray-900 px-2 py-0.5 text-xs font-bold text-white">Base</span></div><p class="mt-1 text-sm text-gray-500">{{ rol.users_count }} usuarios · {{ rol.permissions.length }} permisos</p></div><div class="flex gap-2"><button v-if="!rol.protegido" type="button" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-100" title="Renombrar" @click="editarNombreRol(rol)"><i class="fa-solid fa-pen"></i></button><DangerButton v-if="!rol.protegido" type="button" class="h-9 w-9 justify-center !px-0" title="Eliminar" @click="confirmarEliminar('rol', rol)"><i class="fa-solid fa-trash"></i></DangerButton></div></div><div class="mt-4 flex flex-wrap gap-1"><span v-for="permiso in rol.permissions.slice(0, 8)" :key="permiso.id" class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm">{{ permiso.name }}</span><span v-if="rol.permissions.length > 8" class="rounded-md bg-red-100 px-2 py-1 text-xs font-bold text-red-700">+{{ rol.permissions.length - 8 }}</span><span v-if="!rol.permissions.length" class="text-xs text-gray-400">Sin permisos asignados</span></div><PrimaryButton type="button" class="mt-5" @click="abrirRol(rol)"><i class="fa-solid fa-key mr-2"></i>Asignar permisos</PrimaryButton></article></div>
                    </SectionCard>
                </div>

                <div v-else class="space-y-6">
                    <SectionCard title="Crear permiso" description="Utiliza la nomenclatura módulo.acción, por ejemplo reportes.exportar." icon="fa-solid fa-circle-plus text-red-500">
                        <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="crearPermiso"><div class="flex-1"><InputLabel for="nuevo-permiso" value="Nombre técnico del permiso" /><TextInput id="nuevo-permiso" v-model="nuevoPermiso.name" class="mt-1 block w-full" placeholder="modulo.accion" /><InputError class="mt-2" :message="nuevoPermiso.errors.name" /></div><PrimaryButton :disabled="nuevoPermiso.processing"><i class="fa-solid fa-plus mr-2"></i>Crear permiso</PrimaryButton></form>
                    </SectionCard>
                    <SectionCard title="Permisos disponibles" description="Editar un nombre técnico puede afectar las pantallas que lo utilizan." icon="fa-solid fa-key text-amber-500">
                        <div class="space-y-5"><section v-for="(grupo, nombre) in permisosAgrupados" :key="nombre"><h3 class="mb-2 flex items-center gap-2 text-sm font-black uppercase tracking-wide text-gray-600"><span class="h-5 w-1.5 rounded-full bg-gradient-to-b from-red-500 to-amber-500"></span>{{ nombre }}</h3><div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3"><article v-for="permiso in grupo" :key="permiso.id" class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3 hover:border-red-200 hover:bg-red-50/30"><div class="min-w-0"><p class="truncate font-mono text-sm font-bold text-gray-800">{{ permiso.name }}</p><p class="text-xs text-gray-400">{{ permiso.roles_count }} roles · {{ permiso.users_count }} usuarios directos</p></div><div v-if="!permiso.protegido" class="flex shrink-0 gap-1"><button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-600 hover:bg-white" @click="editarNombrePermiso(permiso)"><i class="fa-solid fa-pen"></i></button><DangerButton type="button" class="h-8 w-8 justify-center !px-0" @click="confirmarEliminar('permiso', permiso)"><i class="fa-solid fa-trash"></i></DangerButton></div><span v-else class="rounded-full bg-gray-900 px-2 py-1 text-xs font-bold text-white">Protegido</span></article></div></section></div>
                    </SectionCard>
                </div>
            </div>
        </template>
    </LayoutPageHeader>

    <Modal :show="modalCrearUsuario" max-width="2xl" @close="modalCrearUsuario = false">
        <form class="max-h-[90vh] overflow-y-auto p-6" @submit.prevent="guardarNuevoUsuario">
            <div class="flex items-start justify-between">
                <div><h2 class="text-xl font-bold text-gray-900"><i class="fa-solid fa-user-plus mr-2 text-red-600"></i>Agregar usuario</h2><p class="mt-1 text-sm text-gray-500">Registra sus datos y asigna el rol con el que comenzará.</p></div>
                <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100" @click="modalCrearUsuario = false"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><InputLabel for="crear-usuario-nombre" value="Nombre completo" /><TextInput id="crear-usuario-nombre" v-model="crearUsuario.name" type="text" class="mt-1 block w-full" autocomplete="name" required autofocus /><InputError class="mt-2" :message="crearUsuario.errors.name" /></div>
                <div class="sm:col-span-2"><InputLabel for="crear-usuario-email" value="Correo electrónico" /><TextInput id="crear-usuario-email" v-model="crearUsuario.email" type="email" class="mt-1 block w-full" autocomplete="username" required /><InputError class="mt-2" :message="crearUsuario.errors.email" /></div>
                <div><InputLabel for="crear-usuario-password" value="Contraseña" /><TextInput id="crear-usuario-password" v-model="crearUsuario.password" type="password" class="mt-1 block w-full" autocomplete="new-password" required /><InputError class="mt-2" :message="crearUsuario.errors.password" /></div>
                <div><InputLabel for="crear-usuario-confirmacion" value="Confirmar contraseña" /><TextInput id="crear-usuario-confirmacion" v-model="crearUsuario.password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" required /></div>
            </div>
            <section class="mt-6 rounded-2xl border border-red-100 bg-gradient-to-r from-white via-red-50 to-amber-50 p-4">
                <h3 class="font-bold text-gray-900"><i class="fa-solid fa-id-badge mr-2 text-red-600"></i>Rol inicial</h3>
                <p class="mt-1 text-xs text-gray-500">Selecciona al menos un rol. Los permisos correspondientes se aplicarán inmediatamente.</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <label v-for="rol in roles" :key="rol.id" class="flex items-center gap-3 rounded-xl border border-red-100 bg-white p-3 shadow-sm transition hover:border-red-300 hover:bg-red-50">
                        <Checkbox v-model:checked="crearUsuario.roles" :value="rol.id" />
                        <div><p class="font-bold text-gray-800">{{ rol.name }}</p><p class="text-xs text-gray-400">{{ rol.permissions.length }} permisos</p></div>
                    </label>
                </div>
                <InputError class="mt-2" :message="crearUsuario.errors.roles" />
            </section>
            <div class="mt-6 flex flex-col-reverse gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                <PrimaryButton type="button" class="justify-center" @click="modalCrearUsuario = false">Cancelar</PrimaryButton>
                <SecondaryButton type="submit" class="justify-center" :disabled="crearUsuario.processing"><i class="fa-solid fa-user-check mr-2"></i>Crear y asignar rol</SecondaryButton>
            </div>
        </form>
    </Modal>

    <Modal :show="modalUsuario" max-width="4xl" @close="modalUsuario = false"><form class="max-h-[88vh] overflow-y-auto p-6" @submit.prevent="guardarUsuario"><div class="flex items-start justify-between"><div><h2 class="text-xl font-bold text-gray-900"><i class="fa-solid fa-user-shield mr-2 text-red-600"></i>Accesos de {{ usuarioSeleccionado?.name }}</h2><p class="mt-1 text-sm text-gray-500">{{ usuarioSeleccionado?.email }}</p></div><button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100" @click="modalUsuario = false"><i class="fa-solid fa-xmark text-xl"></i></button></div><div class="mt-6 grid gap-6 lg:grid-cols-2"><section><h3 class="font-bold text-gray-900">Roles asignados</h3><p class="mb-3 text-xs text-gray-500">El usuario heredará todos los permisos de estos roles.</p><div class="space-y-2"><label v-for="rol in roles" :key="rol.id" class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 hover:bg-red-50"><Checkbox v-model:checked="accesosUsuario.roles" :value="rol.id" /><span class="font-bold text-gray-800">{{ rol.name }}</span><span class="ml-auto text-xs text-gray-400">{{ rol.permissions.length }} permisos</span></label></div><InputError class="mt-2" :message="accesosUsuario.errors.roles" /></section><section><h3 class="font-bold text-gray-900">Permisos directos</h3><p class="mb-3 text-xs text-gray-500">Úsalos únicamente para excepciones individuales.</p><div class="max-h-96 space-y-4 overflow-y-auto pr-2"><div v-for="(grupo, nombre) in permisosAgrupados" :key="nombre"><p class="mb-1 text-xs font-black uppercase text-red-700">{{ nombre }}</p><label v-for="permiso in grupo" :key="permiso.id" class="flex items-center gap-2 py-1.5"><Checkbox v-model:checked="accesosUsuario.permissions" :value="permiso.id" /><span class="font-mono text-xs text-gray-700">{{ permiso.name }}</span></label></div></div><InputError class="mt-2" :message="accesosUsuario.errors.permissions" /></section></div><div class="mt-6 flex justify-end gap-3 border-t pt-4"><PrimaryButton type="button" @click="modalUsuario = false">Cancelar</PrimaryButton><SecondaryButton type="submit" :disabled="accesosUsuario.processing"><i class="fa-solid fa-floppy-disk mr-2"></i>Guardar accesos</SecondaryButton></div></form></Modal>

    <Modal :show="modalRol" max-width="4xl" @close="modalRol = false"><form class="max-h-[88vh] overflow-y-auto p-6" @submit.prevent="guardarPermisosRol"><div class="flex items-start justify-between"><div><h2 class="text-xl font-bold text-gray-900"><i class="fa-solid fa-id-badge mr-2 text-red-600"></i>Permisos del rol {{ rolSeleccionado?.name }}</h2><p class="mt-1 text-sm text-gray-500">Los cambios afectarán a todos los usuarios con este rol.</p></div><button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100" @click="modalRol = false"><i class="fa-solid fa-xmark text-xl"></i></button></div><div class="mt-6 grid gap-5 md:grid-cols-2"><section v-for="(grupo, nombre) in permisosAgrupados" :key="nombre" class="rounded-xl border border-gray-200 p-4"><h3 class="mb-3 font-black uppercase text-red-700">{{ nombre }}</h3><div class="space-y-2"><label v-for="permiso in grupo" :key="permiso.id" class="flex items-center gap-3"><Checkbox v-model:checked="permisosRol.permissions" :value="permiso.id" :disabled="rolSeleccionado?.name?.toLowerCase() === 'administrador' && permiso.name === 'usuarios.administrar'" /><span class="font-mono text-xs text-gray-700">{{ permiso.name }}</span></label></div></section></div><InputError class="mt-3" :message="permisosRol.errors.permissions" /><div class="mt-6 flex justify-end gap-3 border-t pt-4"><PrimaryButton type="button" @click="modalRol = false">Cancelar</PrimaryButton><SecondaryButton type="submit" :disabled="permisosRol.processing"><i class="fa-solid fa-floppy-disk mr-2"></i>Guardar permisos</SecondaryButton></div></form></Modal>

    <Modal :show="modalEditarRol" max-width="md" @close="modalEditarRol = false"><form class="p-6" @submit.prevent="guardarNombreRol"><h2 class="text-lg font-bold text-gray-900">Renombrar rol</h2><div class="mt-5"><InputLabel for="editar-rol" value="Nombre técnico" /><TextInput id="editar-rol" v-model="editarRol.name" class="mt-1 block w-full" /><InputError class="mt-2" :message="editarRol.errors.name || editarRol.errors.role" /></div><div class="mt-6 flex justify-end gap-3"><PrimaryButton type="button" @click="modalEditarRol = false">Cancelar</PrimaryButton><SecondaryButton type="submit">Guardar</SecondaryButton></div></form></Modal>

    <Modal :show="modalPermiso" max-width="md" @close="modalPermiso = false"><form class="p-6" @submit.prevent="guardarNombrePermiso"><h2 class="text-lg font-bold text-gray-900">Editar permiso</h2><div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-800"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Si el permiso está usado en el código, también debes actualizar esa referencia.</div><div class="mt-5"><InputLabel for="editar-permiso" value="Nombre técnico" /><TextInput id="editar-permiso" v-model="editarPermiso.name" class="mt-1 block w-full" /><InputError class="mt-2" :message="editarPermiso.errors.name || editarPermiso.errors.permission" /></div><div class="mt-6 flex justify-end gap-3"><PrimaryButton type="button" @click="modalPermiso = false">Cancelar</PrimaryButton><SecondaryButton type="submit">Guardar</SecondaryButton></div></form></Modal>
</template>
