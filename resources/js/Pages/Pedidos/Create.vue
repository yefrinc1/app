<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import JuegoCatalogoInput from '@/Components/JuegoCatalogoInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { confirmarOperacion, mostrarAdvertencia, mostrarCarga, mostrarErrores, mostrarRespuesta } from '@/Utils/alertas';
import FileInput from './Components/FileInput.vue';
import FormSelect from './Components/FormSelect.vue';
import FormTextarea from './Components/FormTextarea.vue';
import SectionCard from './Components/SectionCard.vue';

defineProps({ canales: Array, metodosPago: Array });
const modoCliente = ref('existente');
const busquedaCliente = ref('');
const clientes = ref([]);
const buscando = ref(false);
const clienteSeleccionado = ref(null);
let temporizador;
const detalleVacio = () => ({ juego: '', tipo_cuenta: '', consola: '', cantidad: 1, precio_unitario: '', descuento: 0, observaciones: '' });
const fechaHoraLocal = () => { const fecha = new Date(); fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset()); return fecha.toISOString().slice(0, 16); };
const pagoVacio = () => ({ metodo_pago: '', valor_bruto: '', fecha_pago: fechaHoraLocal(), referencia: '', comprobante: null, observaciones: '' });
const form = useForm({
    cliente_id: null, cliente_nuevo: { nombre: '', codigo_pais: '57', telefono: '', usuario: '', email: '', notas: '' },
    canal_venta: 'whatsapp', moneda: 'COP', descuento: 0, observaciones: '', detalles: [detalleVacio()], pagos: [],
});
watch(busquedaCliente, (valor) => {
    clearTimeout(temporizador);
    if (valor.trim().length < 2) { clientes.value = []; return; }
    temporizador = setTimeout(async () => {
        buscando.value = true;
        try { const { data } = await axios.get(route('clientes.buscar'), { params: { q: valor } }); clientes.value = data; }
        finally { buscando.value = false; }
    }, 350);
});
watch(modoCliente, (modo) => {
    if (modo === 'existente') form.cliente_nuevo = { nombre: '', codigo_pais: '57', telefono: '', usuario: '', email: '', notas: '' };
    else { form.cliente_id = null; clienteSeleccionado.value = null; }
});
const seleccionarCliente = (cliente) => { form.cliente_id = cliente.id; clienteSeleccionado.value = cliente; busquedaCliente.value = ''; clientes.value = []; };
const subtotalDetalles = computed(() => form.detalles.reduce((total, item) => total + Math.max(0, (Number(item.precio_unitario || 0) * Number(item.cantidad || 0)) - Number(item.descuento || 0)), 0));
const total = computed(() => Math.max(0, subtotalDetalles.value - Number(form.descuento || 0)));
const pagosRegistrados = computed(() => form.pagos.reduce((suma, pago) => suma + Number(pago.valor_bruto || 0), 0));
const agregarDetalle = () => form.detalles.push(detalleVacio());
const quitarDetalle = (indice) => form.detalles.length > 1 && form.detalles.splice(indice, 1);
const agregarPago = () => form.pagos.push(pagoVacio());
const quitarPago = (indice) => form.pagos.splice(indice, 1);
const cargarArchivo = (evento, indice) => { form.pagos[indice].comprobante = evento.target.files[0] ?? null; };
const dinero = (valor) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: form.moneda, maximumFractionDigits: 0 }).format(valor || 0);
const guardar = async () => {
    const pagoSinComprobante = form.pagos.findIndex((pago) => pago.metodo_pago && !pago.comprobante);
    if (pagoSinComprobante !== -1) {
        await mostrarAdvertencia('Falta el comprobante', `Adjunta el comprobante del pago ${pagoSinComprobante + 1} o elimina ese pago para crear el pedido sin método de pago.`);
        return;
    }
    const confirmado = await confirmarOperacion({ titulo: '¿Crear este pedido?', texto: `Se registrará un pedido por ${dinero(total.value)} con ${form.detalles.length} juego(s).`, confirmButtonText: 'Sí, crear pedido' });
    if (!confirmado) return;
    form.post(route('pedidos.store'), {
        forceFormData: true, preserveScroll: true,
        onStart: () => mostrarCarga('Creando pedido…', 'Guardando cliente, juegos, pagos y comprobantes.'),
        onSuccess: (page) => mostrarRespuesta(page, 'Pedido creado correctamente.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo crear el pedido'),
    });
};
</script>

<template>
    <Head title="➕ Crear pedido" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">➕ Crear pedido</h2>
                <Link :href="route('pedidos.index')"><SecondaryButton type="button" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-arrow-left mr-2"></i>Volver</SecondaryButton></Link>
            </div>
        </template>
        <template #contenido-pagina>
            <form class="space-y-6" @submit.prevent="guardar">
                <SectionCard title="1. Información del cliente" description="Busca un cliente existente o registra sus datos sin salir del pedido." icon="fa-solid fa-user text-blue-500">
                    <template #actions>
                        <div class="grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50 p-2">
                            <button type="button" class="rounded-lg border p-3 text-left transition hover:shadow" :class="modoCliente === 'existente' ? 'border-red-500 bg-red-50 ring-2 ring-red-500/20' : 'border-gray-200 bg-white'" @click="modoCliente = 'existente'"><i class="fa-solid fa-magnifying-glass mr-2 text-red-600"></i><span class="text-sm font-bold">Existente</span></button>
                            <button type="button" class="rounded-lg border p-3 text-left transition hover:shadow" :class="modoCliente === 'nuevo' ? 'border-red-500 bg-red-50 ring-2 ring-red-500/20' : 'border-gray-200 bg-white'" @click="modoCliente = 'nuevo'"><i class="fa-solid fa-user-plus mr-2 text-red-600"></i><span class="text-sm font-bold">Nuevo</span></button>
                        </div>
                    </template>
                    <div v-if="modoCliente === 'existente'">
                        <div v-if="clienteSeleccionado" class="flex flex-col gap-3 rounded-xl border border-green-200 bg-green-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="font-bold text-green-900"><i class="fa-solid fa-circle-check mr-2"></i>{{ clienteSeleccionado.nombre || clienteSeleccionado.usuario || clienteSeleccionado.telefono }}</p><p class="mt-1 text-sm text-green-700">{{ clienteSeleccionado.telefono }} · {{ clienteSeleccionado.email || clienteSeleccionado.usuario }}</p></div>
                            <SecondaryButton type="button" @click="form.cliente_id = null; clienteSeleccionado = null">Cambiar cliente</SecondaryButton>
                        </div>
                        <div v-else class="relative">
                            <InputLabel for="buscar-cliente" value="Buscar cliente" />
                            <TextInput id="buscar-cliente" v-model="busquedaCliente" class="mt-1 block w-full" placeholder="Nombre, teléfono, Instagram o correo..." autocomplete="off" />
                            <p v-if="buscando" class="mt-2 text-sm font-semibold text-gray-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Buscando...</p>
                            <div v-if="clientes.length" class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-gray-300 bg-white shadow-md">
                                <button v-for="cliente in clientes" :key="cliente.id" type="button" class="block w-full border-b p-3 text-left text-sm hover:bg-gray-100" @click="seleccionarCliente(cliente)"><span class="block font-bold text-gray-900"><i class="fa-solid fa-user mr-2 text-blue-500"></i>{{ cliente.nombre || cliente.usuario || cliente.telefono }}</span><span class="mt-1 block text-gray-500">{{ cliente.telefono }} · {{ cliente.email || cliente.usuario }}</span></button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.cliente_id" />
                        </div>
                    </div>
                    <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div><InputLabel for="cliente-nombre" value="Nombre" /><TextInput id="cliente-nombre" v-model="form.cliente_nuevo.nombre" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors['cliente_nuevo.nombre']" /></div>
                        <div><InputLabel for="cliente-telefono" value="Teléfono" /><div class="mt-1 grid grid-cols-[80px_minmax(0,1fr)]"><TextInput id="cliente-pais" v-model="form.cliente_nuevo.codigo_pais" class="block w-full rounded-r-none text-center" maxlength="5" placeholder="57" /><TextInput id="cliente-telefono" v-model="form.cliente_nuevo.telefono" class="block w-full rounded-l-none" placeholder="3001234567" /></div><InputError class="mt-2" :message="form.errors['cliente_nuevo.telefono']" /></div>
                        <div><InputLabel for="cliente-instagram" value="Instagram" /><TextInput id="cliente-instagram" v-model="form.cliente_nuevo.usuario" class="mt-1 block w-full" placeholder="@usuario" /></div>
                        <div><InputLabel for="cliente-email" value="Correo" /><TextInput id="cliente-email" v-model="form.cliente_nuevo.email" type="email" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors['cliente_nuevo.email']" /></div>
                        <div class="md:col-span-2"><InputLabel for="cliente-notas" value="Notas" /><TextInput id="cliente-notas" v-model="form.cliente_nuevo.notas" class="mt-1 block w-full" placeholder="Información adicional del cliente" /></div>
                    </div>
                </SectionCard>

                <SectionCard title="2. Juegos solicitados" description="Agrega cada juego con su licencia, consola, cantidad y precio." icon="fa-solid fa-gamepad text-blue-500">
                    <template #actions><PrimaryButton type="button" class="w-full justify-center sm:w-auto" @click="agregarDetalle"><i class="fa-solid fa-plus mr-2"></i>Agregar juego</PrimaryButton></template>
                    <div class="space-y-4">
                        <article v-for="(detalle, indice) in form.detalles" :key="indice" class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <div class="mb-4 flex items-center justify-between"><h3 class="font-bold text-gray-900"><span class="mr-2 rounded-full bg-gray-200 px-2 py-1 text-xs">#{{ indice + 1 }}</span>{{ detalle.juego || 'Nuevo juego' }}</h3><DangerButton v-if="form.detalles.length > 1" type="button" class="h-8 w-8 justify-center" title="Quitar juego" @click="quitarDetalle(indice)"><i class="fa-solid fa-trash"></i></DangerButton></div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                                <div class="xl:col-span-2"><InputLabel :for="`juego-${indice}`" value="Juego" /><JuegoCatalogoInput v-model="detalle.juego" :input-id="`juego-${indice}`" :error="form.errors[`detalles.${indice}.juego`]" /></div>
                                <FormSelect :id="`cuenta-${indice}`" v-model="detalle.tipo_cuenta" label="Tipo de cuenta" :error="form.errors[`detalles.${indice}.tipo_cuenta`]" icon="fa-solid fa-key" required><option value="">Seleccionar</option><option value="Primaria">👑 Cuenta Primaria</option><option value="Secundaria">🎮 Cuenta Secundaria</option></FormSelect>
                                <FormSelect :id="`consola-${indice}`" v-model="detalle.consola" label="Consola" :error="form.errors[`detalles.${indice}.consola`]" icon="fa-brands fa-playstation" required><option value="">Seleccionar</option><option value="PS4">PlayStation 4</option><option value="PS5">PlayStation 5</option></FormSelect>
                                <div><InputLabel :for="`cantidad-${indice}`" value="Cantidad" /><TextInput :id="`cantidad-${indice}`" v-model="detalle.cantidad" type="number" min="1" max="20" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors[`detalles.${indice}.cantidad`]" /></div>
                                <div><InputLabel :for="`precio-${indice}`" value="Precio unitario" /><TextInput :id="`precio-${indice}`" v-model="detalle.precio_unitario" type="number" min="0" step="0.01" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors[`detalles.${indice}.precio_unitario`]" /></div>
                                <div><InputLabel :for="`descuento-${indice}`" value="Descuento" /><TextInput :id="`descuento-${indice}`" v-model="detalle.descuento" type="number" min="0" step="0.01" class="mt-1 block w-full" /></div>
                                <div class="md:col-span-2 xl:col-span-5"><InputLabel :for="`observacion-juego-${indice}`" value="Observaciones" /><TextInput :id="`observacion-juego-${indice}`" v-model="detalle.observaciones" class="mt-1 block w-full" placeholder="Opcional" /></div>
                            </div>
                        </article>
                    </div>
                </SectionCard>

                <SectionCard title="3. Pagos iniciales" description="Son opcionales. Cada método registrado debe incluir su comprobante." icon="fa-solid fa-credit-card text-purple-500">
                    <template #actions><PrimaryButton type="button" class="w-full justify-center sm:w-auto" @click="agregarPago"><i class="fa-solid fa-plus mr-2"></i>Agregar pago</PrimaryButton></template>
                    <div v-if="!form.pagos.length" class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center"><i class="fa-solid fa-receipt text-3xl text-gray-400"></i><h3 class="mt-3 font-bold text-gray-900">Sin pagos iniciales</h3><p class="mt-1 text-sm text-gray-500">Puedes crear el pedido ahora y registrar el método después.</p></div>
                    <div class="space-y-4">
                        <article v-for="(pago, indice) in form.pagos" :key="indice" class="rounded-2xl border border-red-100 bg-gradient-to-br from-white via-red-50 to-amber-50 p-4 shadow-md">
                            <div class="mb-4 flex items-center justify-between"><h3 class="font-bold text-gray-900"><i class="fa-solid fa-receipt mr-2 text-red-600"></i>Pago {{ indice + 1 }}</h3><DangerButton type="button" class="h-8 w-8 justify-center" @click="quitarPago(indice)"><i class="fa-solid fa-trash"></i></DangerButton></div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                                <FormSelect :id="`metodo-${indice}`" v-model="pago.metodo_pago" label="Método" :error="form.errors[`pagos.${indice}.metodo_pago`]" icon="fa-solid fa-credit-card" required><option value="">Seleccionar</option><option v-for="metodo in metodosPago" :key="metodo" :value="metodo">{{ metodo }}</option></FormSelect>
                                <div><InputLabel :for="`valor-pago-${indice}`" value="Valor bruto" /><TextInput :id="`valor-pago-${indice}`" v-model="pago.valor_bruto" type="number" min="1" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors[`pagos.${indice}.valor_bruto`]" /></div>
                                <div><InputLabel :for="`fecha-pago-${indice}`" value="Fecha y hora" /><TextInput :id="`fecha-pago-${indice}`" v-model="pago.fecha_pago" type="datetime-local" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors[`pagos.${indice}.fecha_pago`]" /></div>
                                <div><InputLabel :for="`referencia-${indice}`" value="Referencia" /><TextInput :id="`referencia-${indice}`" v-model="pago.referencia" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors[`pagos.${indice}.referencia`]" /></div>
                                <div class="xl:col-span-2"><FileInput :id="`comprobante-${indice}`" label="Comprobante obligatorio" :file-name="pago.comprobante?.name" :error="form.errors[`pagos.${indice}.comprobante`]" required @change="cargarArchivo($event, indice)" /></div>
                                <div class="md:col-span-2 xl:col-span-6"><InputLabel :for="`observacion-pago-${indice}`" value="Observaciones" /><TextInput :id="`observacion-pago-${indice}`" v-model="pago.observaciones" class="mt-1 block w-full" placeholder="Opcional" /></div>
                            </div>
                        </article>
                    </div>
                </SectionCard>

                <div class="grid gap-6 lg:grid-cols-3">
                    <SectionCard class="lg:col-span-2" title="4. Información del pedido" description="Define el canal, moneda y observaciones generales." icon="fa-solid fa-clipboard-list text-amber-500">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormSelect id="canal-venta" v-model="form.canal_venta" label="Canal de venta" icon="fa-solid fa-store"><option v-for="canal in canales" :key="canal" :value="canal">{{ canal }}</option></FormSelect>
                            <FormSelect id="moneda" v-model="form.moneda" label="Moneda" icon="fa-solid fa-coins"><option value="COP">COP</option><option value="USD">USD</option></FormSelect>
                            <div><InputLabel for="descuento-general" value="Descuento general" /><TextInput id="descuento-general" v-model="form.descuento" type="number" min="0" class="mt-1 block w-full" /></div>
                            <FormTextarea id="observaciones-pedido" v-model="form.observaciones" label="Observaciones" class="md:col-span-3" />
                        </div>
                    </SectionCard>
                    <aside class="rounded-2xl border border-red-100 bg-gradient-to-br from-white via-red-50 to-amber-50 p-5 shadow-md">
                        <h2 class="text-lg font-bold text-gray-900"><i class="fa-solid fa-calculator mr-2 text-red-600"></i>Resumen</h2>
                        <div class="mt-5 space-y-3 text-sm"><div class="flex justify-between"><span class="text-gray-600">Subtotal</span><strong>{{ dinero(subtotalDetalles) }}</strong></div><div class="flex justify-between"><span class="text-gray-600">Descuento</span><strong class="text-red-600">-{{ dinero(form.descuento) }}</strong></div><div class="flex justify-between border-t border-red-100 pt-4 text-xl"><span class="font-bold">Total</span><strong class="text-green-700">{{ dinero(total) }}</strong></div><div class="flex justify-between rounded-lg bg-white p-3"><span class="text-gray-600">Pagos registrados</span><strong>{{ dinero(pagosRegistrados) }}</strong></div></div>
                        <PrimaryButton class="mt-5 w-full justify-center py-3" :disabled="form.processing"><i class="fa-solid fa-floppy-disk mr-2"></i>{{ form.processing ? 'Guardando...' : 'Crear pedido' }}</PrimaryButton>
                    </aside>
                </div>
            </form>
        </template>
    </LayoutPageHeader>
</template>
