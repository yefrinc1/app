import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

export const confirmarOperacion = async ({
    titulo = '¿Confirmar operación?',
    texto = '',
    confirmButtonText = 'Sí, continuar',
    icon = 'question',
} = {}) => {
    const resultado = await Swal.fire({
        title: titulo,
        text: texto,
        icon,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        reverseButtons: true,
    });

    return resultado.isConfirmed;
};

export const pedirTexto = async ({
    titulo,
    etiqueta = 'Escribe el motivo',
    confirmButtonText = 'Confirmar',
    minimo = 3,
} = {}) => {
    const resultado = await Swal.fire({
        title: titulo,
        input: 'textarea',
        inputLabel: etiqueta,
        inputPlaceholder: etiqueta,
        inputAttributes: { maxlength: '1000' },
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        reverseButtons: true,
        inputValidator: (valor) => {
            if (!valor || valor.trim().length < minimo) {
                return `Escribe al menos ${minimo} caracteres.`;
            }
            return null;
        },
    });

    return resultado.isConfirmed ? resultado.value.trim() : null;
};

export const mostrarCarga = (titulo = 'Procesando…', texto = 'Espera un momento.') => {
    Swal.fire({
        title: titulo,
        text: texto,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
    });
};

export const mostrarExito = (titulo, texto = '') => Swal.fire({
    title: titulo,
    text: texto,
    icon: 'success',
    confirmButtonColor: '#16a34a',
});

export const mostrarAdvertencia = (titulo, texto = '') => Swal.fire({
    title: titulo,
    text: texto,
    icon: 'warning',
    confirmButtonColor: '#d97706',
});

export const mostrarErrores = (errores, titulo = 'No se pudo completar') => {
    const mensajes = Object.values(errores ?? {})
        .flat(Infinity)
        .filter(Boolean)
        .join('\n');

    return Swal.fire({
        title: titulo,
        text: mensajes || 'Revisa la información e inténtalo nuevamente.',
        icon: 'error',
        confirmButtonColor: '#dc2626',
    });
};

export const mostrarRespuesta = (page, mensajeExito = 'Operación completada correctamente.') => {
    const flash = page?.props?.flash ?? {};
    const mensaje = flash.warning || flash.success || mensajeExito;

    if (flash.warning || /falta inventario|sin inventario|sin códigos/i.test(mensaje)) {
        return mostrarAdvertencia('Inventario incompleto', mensaje);
    }

    return mostrarExito('Listo', mensaje);
};

export const verComprobante = (url, titulo = 'Comprobante') => Swal.fire({
    title: titulo,
    html: '<div id="swal-visor-comprobante" style="height:70vh"></div>',
    width: 'min(1100px, 96vw)',
    showCloseButton: true,
    showConfirmButton: false,
    didOpen: () => {
        const contenedor = document.getElementById('swal-visor-comprobante');
        const visor = document.createElement('iframe');
        visor.src = url;
        visor.title = titulo;
        visor.style.width = '100%';
        visor.style.height = '100%';
        visor.style.border = '0';
        visor.style.borderRadius = '12px';
        contenedor?.appendChild(visor);
    },
});

export const seleccionarJuegoReembolso = async (opciones) => {
    const disponibles = opciones.filter((opcion) => Number(opcion.disponible) > 0);

    if (!disponibles.length) {
        await mostrarAdvertencia('Sin juegos disponibles', 'No hay unidades sin venta que puedan relacionarse con este reembolso.');
        return null;
    }

    const seleccion = await Swal.fire({
        title: 'Relacionar juego reembolsado',
        input: 'select',
        inputOptions: Object.fromEntries(disponibles.map((opcion) => [opcion.id, `${opcion.nombre} · máximo ${opcion.disponible}`])),
        inputPlaceholder: 'Selecciona el juego',
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#7e22ce',
        inputValidator: (valor) => valor ? null : 'Selecciona un juego.',
    });

    if (!seleccion.isConfirmed) return null;

    const opcion = disponibles.find((item) => String(item.id) === String(seleccion.value));
    const cantidad = await Swal.fire({
        title: 'Cantidad reembolsada',
        input: 'number',
        inputValue: 1,
        inputAttributes: { min: '1', max: String(opcion.disponible), step: '1' },
        showCancelButton: true,
        confirmButtonText: 'Relacionar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#7e22ce',
        inputValidator: (valor) => {
            const numero = Number(valor);
            return Number.isInteger(numero) && numero >= 1 && numero <= Number(opcion.disponible)
                ? null
                : `Ingresa una cantidad entre 1 y ${opcion.disponible}.`;
        },
    });

    return cantidad.isConfirmed
        ? { pedido_detalle_id: seleccion.value, cantidad: Number(cantidad.value) }
        : null;
};
