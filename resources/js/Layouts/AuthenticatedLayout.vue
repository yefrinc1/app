<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import DropDownMenu from '@/Components/DropDownMenu.vue';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white border-b border-gray-100">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo class="block h-20 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    📊 {{ $t('Dashboard') }}
                                </NavLink>
                                <DropDownMenu   align="right" width="48">
                                    <template #trigger>
                                        ✉️ Correos
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('correo-principal.index')"> 📩 Principales </DropdownLink>
                                        <DropdownLink :href="route('correo-madre.index')"> 👤 Madres </DropdownLink>
                                        <DropdownLink :href="route('correo-globales.index')"> 🌐 Globales </DropdownLink>
                                        <DropdownLink :href="route('correo-juegos.index')"> 🎮 Juegos </DropdownLink>
                                        <DropdownLink :href="route('correo-juegos.crear-juego-manual')"> 🎮 Crear Juegos Manual </DropdownLink>
                                    </template>
                                </DropDownMenu>
                                <DropDownMenu align="right" width="48">
                                    <template #trigger>
                                        🔤 Códigos
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('codigo-verificacion.generar')"> 🪄 Generar </DropdownLink>
                                        <DropdownLink :href="route('codigo-verificacion.create')"> 🔨 Crear </DropdownLink>
                                    </template>
                                </DropDownMenu>
                                <DropDownMenu align="right" width="48">
                                    <template #trigger>
                                        🛒 Ventas
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('ventas.create')"> 💸 Venta de Juego </DropdownLink>
                                        <DropdownLink :href="route('movimientos.index')"> 🔄 Movimientos </DropdownLink>
                                        <DropdownLink :href="route('cierre-caja.create')">🎰 Cerrar Caja </DropdownLink>
                                    </template>
                                </DropDownMenu>
                                <DropDownMenu align="right" width="48">
                                    <template #trigger>
                                        🔍 Consultar
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('codigo-verificacion.index')"> 🔒 Codigos VF </DropdownLink>
                                        <DropdownLink :href="route('cierre-caja.index')">🎰 Cierres de Cajas </DropdownLink>
                                        <DropdownLink :href="route('pagos.create')">💰 Pagos </DropdownLink>
                                        <DropdownLink :href="route('ventas.index')">🛍️ Consultar Ventas </DropdownLink>
                                        <DropdownLink :href="route('consultar-inventario')">🧰 Consultar Inventario </DropdownLink>
                                    </template>
                                </DropDownMenu>
                                <DropDownMenu align="right" width="48">
                                    <template #trigger>
                                        📈 Estadisticas
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('estadistica-juegos')"> 💱 Estadistica Juegos </DropdownLink>
                                        <DropdownLink :href="route('resumen-mensual')">📝 Resumen Mensual </DropdownLink>
                                    </template>
                                </DropDownMenu>
                                <NavLink :href="route('notificaciones.index')" :active="route().current('notificaciones.index')">
                                    🔔
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <!-- Settings Dropdown -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button"
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')"> {{ $t('Profile') }} </DropdownLink>
                                        <DropdownLink :href="route('agregar-usuario.create')"> Agregar Usuario </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            {{ $t('Log Out') }}
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            📊 {{ $t('Dashboard') }}
                        </ResponsiveNavLink>
                    </div>

                    <!-- 📬 Correos -->
                    <div class="pt-2 pb-3 space-y-1">
                        <div class="px-4 text-sm text-gray-500">✉️ Correos</div>
                        <ResponsiveNavLink :href="route('correo-principal.index')" class="pl-4">📩 Principales</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('correo-madre.index')" class="pl-4">👤 Madres</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('correo-globales.index')" class="pl-4">🌐 Globales</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('correo-juegos.index')" class="pl-4">🎮 Juegos</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('correo-juegos.crear-juego-manual')" class="pl-4">🎮 Crear Juegos Manual</ResponsiveNavLink>
                    </div>

                    <!-- 🔤 Códigos -->
                    <div class="pt-2 pb-3 space-y-1">
                        <div class="px-4 text-sm text-gray-500">🔤 Códigos</div>
                        <ResponsiveNavLink :href="route('codigo-verificacion.generar')" class="pl-4">🪄 Generar</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('codigo-verificacion.create')" class="pl-4">🔨 Crear</ResponsiveNavLink>
                    </div>

                    <!-- 🛒 Ventas -->
                    <div class="pt-2 pb-3 space-y-1">
                        <div class="px-4 text-sm text-gray-500">🛒 Ventas</div>
                        <ResponsiveNavLink :href="route('ventas.create')" class="pl-4">💸 Venta de Juego</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('movimientos.index')" class="pl-4">🔄 Movimientos</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('cierre-caja.create')" class="pl-4">🎰 Cerrar Caja</ResponsiveNavLink>
                    </div>

                    <!-- 🔍 Consultar -->
                    <div class="pt-2 pb-3 space-y-1">
                        <div class="px-4 text-sm text-gray-500">🔍 Consultar</div>
                        <ResponsiveNavLink :href="route('codigo-verificacion.index')" class="pl-4">🔒 Códigos VF</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('cierre-caja.index')" class="pl-4">🎰 Cierres de Cajas</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('pagos.create')" class="pl-4">💰 Pagos</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('ventas.index')" class="pl-4">🛍️ Consultar Ventas</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('consultar-inventario')" class="pl-4">🧰 Consultar Inventario</ResponsiveNavLink>
                    </div>

                    <!-- 📈 Estadisticas -->
                    <div class="pt-2 pb-3 space-y-1">
                        <div class="px-4 text-sm text-gray-500">📈 Estadisticas</div>
                        <ResponsiveNavLink :href="route('estadistica-juegos')" class="pl-4">💱 Estadistica Juegos</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('resumen-mensual')" class="pl-4">📝 Resumen Mensual</ResponsiveNavLink>
                    </div>

                    <!-- 🔔 Notificaciones -->
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('notificaciones.index')" :active="route().current('notificaciones.index')">
                            🔔 Notificaciones
                        </ResponsiveNavLink>
                    </div>

                    <!-- Usuario y logout -->
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="px-4">
                            <div class="font-medium text-base text-gray-800">{{ $page.props.auth.user.name }}</div>
                            <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">{{ $t('Profile') }}</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('agregar-usuario.create')">Agregar Usuario</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                                {{ $t('Log Out') }}
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>

            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow" v-if="$slots.header">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
