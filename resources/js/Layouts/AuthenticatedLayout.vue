<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const page = usePage();

/*
|--------------------------------------------------------------------------
| Estados del menú
|--------------------------------------------------------------------------
*/

const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);
const profileMenuOpen = ref(false);

const expandedSections = ref({
    Pedidos: false,
    Inventario: false,
    Correos: false,
    Códigos: false,
    Finanzas: false,
    Estadísticas: false,
    Jumpseller: false,
});

/*
|--------------------------------------------------------------------------
| Permisos
|--------------------------------------------------------------------------
*/

const permissions = computed(() => {
    return page.props.auth.permissions ?? [];
});

const can = (permission) => {
    if (!permission) {
        return true;
    }

    return permissions.value.includes(permission);
};

/*
|--------------------------------------------------------------------------
| Configuración del menú
|--------------------------------------------------------------------------
*/

const menuSections = computed(() => [
    // {
    //     label: 'Pedidos',
    //     icon: '📦',
    //     items: [
    //         {
    //             label: 'Todos los pedidos',
    //             icon: '📋',
    //             route: 'pedidos.index',
    //             active: 'pedidos.index',
    //             permission: 'pedidos.ver',
    //         },
    //         {
    //             label: 'Crear pedido',
    //             icon: '➕',
    //             route: 'pedidos.create',
    //             active: 'pedidos.create',
    //             permission: 'pedidos.crear',
    //         },
    //         {
    //             label: 'Verificar pagos',
    //             icon: '💳',
    //             route: 'comprobantes.pendientes',
    //             active: 'comprobantes.*',
    //             permission: 'pagos.revisar',
    //         },
    //     ],
    // },

    {
        label: 'Ventas',
        icon: '🛒',
        items: [
            {
                label: 'Consultar ventas',
                icon: '🧾',
                route: 'ventas.index',
                active: 'ventas.index',
                permission: 'ventas.ver',
            },
            {
                label: 'Registrar venta manual',
                icon: '💸',
                route: 'ventas.create',
                active: 'ventas.create',
                permission: 'ventas.crear',
            },
        ],
    },

    {
        label: 'Inventario',
        icon: '🎮',
        items: [
            {
                label: 'Consultar inventario',
                icon: '🧰',
                route: 'consultar-inventario',
                active: 'consultar-inventario',
                permission: 'inventario.consultar',
            },
            {
                label: 'Titulos de juegos',
                icon: '🎮',
                route: 'juegos.index',
                active: 'juegos.*',
                permission: 'inventario.titulos',
            },
        ],
    },

    {
        label: 'Cuentas',
        icon: '✉️',
        items: [
            {
                label: 'Correos principales',
                icon: '📩',
                route: 'correo-principal.index',
                active: 'correo-principal.*',
                permission: 'correos.principales',
            },
            {
                label: 'Correos madre',
                icon: '👤',
                route: 'correo-madre.index',
                active: 'correo-madre.*',
                permission: 'correos.madre',
            },
            {
                label: 'Correos globales',
                icon: '🌐',
                route: 'correo-globales.index',
                active: 'correo-globales.*',
                permission: 'correos.globales',
            },
            {
                label: 'Correos juegos',
                icon: '📧',
                route: 'correo-juegos.index',
                active: 'correo-juegos.index',
                permission: 'correos.juegos',
            },
            {
                label: 'Crear juego manual',
                icon: '➕',
                route: 'correo-juegos.crear-juego-manual',
                active: 'correo-juegos.crear-juego-manual',
                permission: 'correos.juegos.manual',
            },
        ],
    },

    {
        label: 'Códigos',
        icon: '🔐',
        items: [
            {
                label: 'Consultar códigos',
                icon: '🔒',
                route: 'codigo-verificacion.index',
                active: 'codigo-verificacion.index',
                permission: 'codigos.consultar',
            },
            {
                label: 'Generar código',
                icon: '🪄',
                route: 'codigo-verificacion.generar',
                active: 'codigo-verificacion.generar',
                permission: 'codigos.generar',
            },
            {
                label: 'Crear código manual',
                icon: '🔨',
                route: 'codigo-verificacion.create',
                active: 'codigo-verificacion.create',
                permission: 'codigos.crear',
            },
        ],
    },

    {
        label: 'Finanzas',
        icon: '💰',
        items: [
            {
                label: 'Movimientos',
                icon: '🔄',
                route: 'movimientos.index',
                active: 'movimientos.*',
                permission: 'finanzas.movimientos',
            },
            {
                label: 'Registrar pago',
                icon: '💵',
                route: 'pagos.create',
                active: 'pagos.*',
                permission: 'finanzas.ver.pago',
            },
            {
                label: 'Cerrar caja',
                icon: '🎰',
                route: 'cierre-caja.create',
                active: 'cierre-caja.create',
                permission: 'finanzas.cerrar.caja',
            },
            {
                label: 'Historial de cierres',
                icon: '📚',
                route: 'cierre-caja.index',
                active: 'cierre-caja.index',
                permission: 'finanzas.cerrar.caja.ver',
            },
            {
                label: 'Presupuestos',
                icon: '📈',
                route: 'presupuestos.index',
                active: 'presupuestos.*',
                permission: 'finanzas.presupuesto',
            },
        ],
    },

    {
        label: 'Reportes',
        icon: '📊',
        items: [
            {
                label: 'Estadística de juegos',
                icon: '🎮',
                route: 'estadistica-juegos',
                active: 'estadistica-juegos',
                permission: 'reportes.juegos',
            },
            {
                label: 'Resumen mensual',
                icon: '📝',
                route: 'resumen-mensual',
                active: 'resumen-mensual',
                permission: 'reportes.mensual',
            },
        ],
    },

    {
        label: 'Integraciones',
        icon: '🧩',
        items: [
            {
                label: 'Productos en oferta',
                icon: '🏷️',
                route: 'productos-oferta-jumpseller',
                active: 'productos-oferta-jumpseller',
                permission: 'integraciones.productos.ofertas',
            },
            {
                label: 'Sincronizar productos',
                icon: '🔃',
                route: 'productos-sincronizar',
                active: 'productos-sincronizar',
                permission: 'integraciones.productos.sincronizar',
            },
        ],
    },
]);

/*
|--------------------------------------------------------------------------
| Menú filtrado por permisos
|--------------------------------------------------------------------------
*/

const visibleMenuSections = computed(() => {
    return menuSections.value
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => can(item.permission)),
        }))
        .filter((section) => section.items.length > 0);
});

/*
|--------------------------------------------------------------------------
| Rutas activas
|--------------------------------------------------------------------------
*/

const isRouteActive = (routePattern) => {
    return Boolean(route().current(routePattern));
};

const isSectionActive = (section) => {
    return section.items.some((item) => {
        return isRouteActive(item.active ?? item.route);
    });
};

/*
|--------------------------------------------------------------------------
| Acciones del menú
|--------------------------------------------------------------------------
*/

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

const closeMobileSidebar = () => {
    sidebarOpen.value = false;
};

const toggleSection = (section) => {
    /*
     * Cuando el menú está contraído y se presiona una sección,
     * primero se expande el menú y luego se abre la sección.
     */
    if (sidebarCollapsed.value) {
        sidebarCollapsed.value = false;
        expandedSections.value[section.label] = true;
        return;
    }

    expandedSections.value[section.label] =
        !expandedSections.value[section.label];
};

const isSectionExpanded = (section) => {
    return expandedSections.value[section.label] ?? false;
};

/*
|--------------------------------------------------------------------------
| Abrir automáticamente la sección activa
|--------------------------------------------------------------------------
*/

const openActiveSection = () => {
    const activeSection = visibleMenuSections.value.find((section) => {
        return isSectionActive(section);
    });

    if (activeSection) {
        expandedSections.value[activeSection.label] = true;
    }
};

onMounted(() => {
    openActiveSection();
});

/*
 * El layout puede persistir entre navegaciones de Inertia.
 * Por eso observamos la URL para abrir la nueva sección activa.
 */
watch(
    () => page.url,
    () => {
        openActiveSection();
        closeMobileSidebar();
    }
);

const toggleProfileMenu = () => {
    profileMenuOpen.value = !profileMenuOpen.value;
};

const closeProfileMenu = () => {
    profileMenuOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 lg:flex">
        <!-- Fondo oscuro móvil -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-black/60 lg:hidden"
                @click="closeMobileSidebar"
            />
        </Transition>

        <!-- Menú lateral -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex flex-col bg-gray-950 text-white transition-all duration-300 lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:shrink-0 lg:translate-x-0"
            :class="[
                sidebarOpen
                    ? 'translate-x-0'
                    : '-translate-x-full',
                sidebarCollapsed
                    ? 'w-20'
                    : 'w-72',
            ]"
        >
            <!-- Encabezado del menú -->
            <div
                class="flex h-16 shrink-0 items-center border-b border-gray-800 px-3"
                :class="
                    sidebarCollapsed
                        ? 'justify-center'
                        : 'justify-between'
                "
            >
                <!-- Logo -->
                <Link
                    :href="route('dashboard')"
                    class="flex items-center justify-center"
                    @click="closeMobileSidebar"
                >
                    <ApplicationLogo
                        :class="
                            sidebarCollapsed
                                ? 'h-10 w-10 object-contain'
                                : 'h-12 w-auto object-contain'
                        "
                    />
                </Link>

                <!-- Botón para contraer en escritorio -->
                <button
                    type="button"
                    class="hidden rounded-lg p-2 text-gray-400 transition hover:bg-gray-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-red-500 lg:inline-flex"
                    :title="
                        sidebarCollapsed
                            ? 'Expandir menú'
                            : 'Contraer menú'
                    "
                    @click="toggleSidebar"
                >
                    <svg
                        class="h-5 w-5 transition-transform duration-300"
                        :class="{
                            'rotate-180': sidebarCollapsed,
                        }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M11 19l-7-7 7-7M4 12h16"
                        />
                    </svg>
                </button>

                <!-- Botón cerrar móvil -->
                <button
                    type="button"
                    class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-red-500 lg:hidden"
                    @click="closeMobileSidebar"
                >
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Navegación -->
            <nav
                class="sidebar-scroll flex-1 overflow-y-auto overflow-x-hidden px-3 py-4"
            >
                <!-- Dashboard -->
                <Link
                    :href="route('dashboard')"
                    class="mb-2 flex items-center rounded-xl py-2.5 text-sm font-medium transition"
                    :class="[
                        sidebarCollapsed
                            ? 'justify-center px-2'
                            : 'gap-3 px-3',
                        route().current('dashboard')
                            ? 'bg-red-600 text-white shadow-sm'
                            : 'text-gray-300 hover:bg-gray-800 hover:text-white',
                    ]"
                    title="Dashboard"
                    @click="closeMobileSidebar"
                >
                    <span class="shrink-0 text-lg">
                        📊
                    </span>

                    <span
                        v-if="!sidebarCollapsed"
                        class="truncate"
                    >
                        Dashboard
                    </span>
                </Link>

                <!-- Secciones -->
                <div
                    v-for="section in visibleMenuSections"
                    :key="section.label"
                    class="mb-2"
                >
                    <!-- Botón principal de la sección -->
                    <button
                        type="button"
                        class="flex w-full items-center rounded-xl py-2.5 text-left text-sm font-medium transition"
                        :class="[
                            sidebarCollapsed
                                ? 'justify-center px-2'
                                : 'justify-between px-3',
                            isSectionActive(section)
                                ? 'bg-gray-800 text-red-400'
                                : 'text-gray-300 hover:bg-gray-800 hover:text-white',
                        ]"
                        :title="
                            sidebarCollapsed
                                ? section.label
                                : null
                        "
                        @click="toggleSection(section)"
                    >
                        <span
                            class="flex min-w-0 items-center"
                            :class="{
                                'gap-3': !sidebarCollapsed,
                            }"
                        >
                            <span class="shrink-0 text-lg">
                                {{ section.icon }}
                            </span>

                            <span
                                v-if="!sidebarCollapsed"
                                class="truncate"
                            >
                                {{ section.label }}
                            </span>
                        </span>

                        <!-- Flecha -->
                        <svg
                            v-if="!sidebarCollapsed"
                            class="h-4 w-4 shrink-0 transition-transform duration-200"
                            :class="{
                                'rotate-180':
                                    isSectionExpanded(section),
                            }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>

                    <!-- Opciones de la sección -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="-translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="-translate-y-1 opacity-0"
                    >
                        <div
                            v-if="
                                !sidebarCollapsed &&
                                isSectionExpanded(section)
                            "
                            class="mt-1 space-y-1 ps-3"
                        >
                            <Link
                                v-for="item in section.items"
                                :key="item.route"
                                :href="route(item.route)"
                                class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition"
                                :class="
                                    isRouteActive(
                                        item.active ?? item.route
                                    )
                                        ? 'bg-red-600 font-semibold text-white shadow-sm'
                                        : 'text-gray-400 hover:bg-gray-800 hover:text-white'
                                "
                                @click="closeMobileSidebar"
                            >
                                <span class="shrink-0 text-base">
                                    {{ item.icon }}
                                </span>

                                <span class="truncate">
                                    {{ item.label }}
                                </span>
                            </Link>
                        </div>
                    </Transition>
                </div>

                <!-- Notificaciones -->
                <Link
                    :href="route('notificaciones.index')"
                    class="mt-2 flex items-center rounded-xl py-2.5 text-sm font-medium transition"
                    :class="[
                        sidebarCollapsed
                            ? 'justify-center px-2'
                            : 'gap-3 px-3',
                        route().current('notificaciones.*')
                            ? 'bg-red-600 text-white shadow-sm'
                            : 'text-gray-300 hover:bg-gray-800 hover:text-white',
                    ]"
                    title="Notificaciones"
                    @click="closeMobileSidebar"
                >
                    <span class="shrink-0 text-lg">
                        🔔
                    </span>

                    <span
                        v-if="!sidebarCollapsed"
                        class="truncate"
                    >
                        Notificaciones
                    </span>
                </Link>
            </nav>

            <!-- Usuario -->
            <div class="relative shrink-0 border-t border-gray-800 p-3">
                <!-- Menú desplegable hacia arriba -->
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="translate-y-2 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="translate-y-2 opacity-0"
                >
                    <div
                        v-if="profileMenuOpen"
                        class="absolute bottom-full z-50 mb-2 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl"
                        :class="
                            sidebarCollapsed
                                ? 'left-3 w-56'
                                : 'left-3 right-3'
                        "
                    >
                        <Link
                            :href="route('profile.edit')"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-100"
                            @click="closeProfileMenu"
                        >
                            <span>👤</span>
                            <span>Perfil</span>
                        </Link>

                        <Link
                            v-if="can('usuarios.crear')"
                            :href="route('agregar-usuario.create')"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-100"
                            @click="closeProfileMenu"
                        >
                            <span>➕</span>
                            <span>Agregar usuario</span>
                        </Link>

                        <div class="my-1 border-t border-gray-200" />

                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50"
                            @click="closeProfileMenu"
                        >
                            <span>🚪</span>
                            <span>Cerrar sesión</span>
                        </Link>
                    </div>
                </Transition>

                <!-- Botón del perfil -->
                <button
                    type="button"
                    class="flex w-full items-center rounded-xl py-2 text-left transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-red-500"
                    :class="
                        sidebarCollapsed
                            ? 'justify-center px-2'
                            : 'gap-3 px-3'
                    "
                    :title="
                        sidebarCollapsed
                            ? $page.props.auth.user.name
                            : null
                    "
                    @click="toggleProfileMenu"
                >
                    <!-- Avatar -->
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-600 font-bold text-white"
                    >
                        {{
                            $page.props.auth.user.name
                                .charAt(0)
                                .toUpperCase()
                        }}
                    </div>

                    <template v-if="!sidebarCollapsed">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-white">
                                {{ $page.props.auth.user.name }}
                            </div>

                            <div class="truncate text-xs text-gray-400">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <svg
                            class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200"
                            :class="{
                                'rotate-180': profileMenuOpen,
                            }"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </template>
                </button>
            </div>
        </aside>

        <!-- Contenido principal -->
        <div class="min-w-0 flex-1">
            <!-- Barra superior móvil -->
            <header
                class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm lg:hidden"
            >
                <button
                    type="button"
                    class="rounded-lg p-2 text-gray-600 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500"
                    @click="sidebarOpen = true"
                >
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>

                <Link
                    :href="route('notificaciones.index')"
                    class="rounded-lg p-2 text-gray-600 transition hover:bg-gray-100"
                >
                    🔔
                </Link>
            </header>

            <!-- Encabezado de la página -->
            <header
                v-if="$slots.header"
                class="border-b border-gray-200 bg-white shadow-sm"
            >
                <div
                    class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8"
                >
                    <slot name="header" />
                </div>
            </header>

            <!-- Contenido -->
            <main class="min-h-screen">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
/*
|--------------------------------------------------------------------------
| Scrollbar del menú lateral
|--------------------------------------------------------------------------
*/

.sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: #374151 #030712;
}

/* Chrome, Edge, Safari */
.sidebar-scroll::-webkit-scrollbar {
    width: 8px;
}

.sidebar-scroll::-webkit-scrollbar-track {
    background: #030712;
}

.sidebar-scroll::-webkit-scrollbar-thumb {
    background-color: #374151;
    border: 2px solid #030712;
    border-radius: 9999px;
}

.sidebar-scroll::-webkit-scrollbar-thumb:hover {
    background-color: #4b5563;
}
</style>