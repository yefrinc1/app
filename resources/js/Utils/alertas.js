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

export const verComprobante = (url, titulo = 'Comprobante') => {
    let objectUrl = null;
    let cerrado = false;
    const controlador = new AbortController();

    return Swal.fire({
        title: titulo,
        html: '<div id="swal-visor-comprobante"><div style="padding:2rem;color:#6b7280">Cargando comprobante…</div></div>',
        width: 'min(1100px, calc(100vw - 16px))',
        showCloseButton: true,
        showConfirmButton: false,
        didOpen: async () => {
            const contenedor = document.getElementById('swal-visor-comprobante');
            const popup = Swal.getPopup();
            const contenido = Swal.getHtmlContainer();
            const esCelular = window.matchMedia('(max-width: 640px)').matches;
            const altoCelular = window.CSS?.supports?.('height', '100dvh')
                ? 'calc(100dvh - 150px)'
                : 'calc(100vh - 150px)';

            if (!contenedor) return;

            if (popup) {
                popup.style.maxWidth = '1100px';
                popup.style.padding = esCelular ? '0.75rem' : '1.25rem';
                popup.style.overflow = 'hidden';
            }
            if (contenido) {
                contenido.style.margin = esCelular ? '0.5rem 0 0' : '1rem 0 0';
                contenido.style.overflow = 'hidden';
            }

            Object.assign(contenedor.style, {
                width: '100%',
                height: esCelular ? altoCelular : '70vh',
                minHeight: esCelular ? '260px' : '420px',
                maxHeight: esCelular ? altoCelular : '820px',
                overflow: 'auto',
                borderRadius: '12px',
                background: '#f3f4f6',
                overscrollBehavior: 'contain',
                WebkitOverflowScrolling: 'touch',
                touchAction: 'pan-x pan-y pinch-zoom',
            });

            try {
                const respuesta = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'image/*,application/pdf' },
                    signal: controlador.signal,
                });
                if (!respuesta.ok) throw new Error('No fue posible cargar el comprobante.');

                const archivo = await respuesta.blob();
                if (cerrado) return;
                const tipo = (archivo.type || respuesta.headers.get('content-type') || '').toLowerCase();
                objectUrl = URL.createObjectURL(archivo);

                // Las rutas solo entregan imagenes o PDF. Algunos hosting
                // responden JPG/PNG como application/octet-stream, por eso
                // cualquier archivo que no sea PDF se intenta como imagen.
                if (!tipo.includes('pdf')) {
                    const imagen = document.createElement('img');
                    imagen.src = objectUrl;
                    imagen.alt = titulo;
                    imagen.style.display = 'block';
                    imagen.style.height = 'auto';
                    imagen.style.margin = '0 auto';
                    imagen.style.objectFit = 'contain';
                    imagen.style.background = '#ffffff';

                    if (esCelular) {
                        // En celular la captura ocupa el ancho disponible y se
                        // recorre verticalmente para poder leerla completa.
                        imagen.style.width = '100%';
                        imagen.style.maxWidth = '100%';
                    } else {
                        // En computador una captura vertical se ajusta primero
                        // al alto del visor. No se fuerza al ancho completo ni
                        // se amplia por encima de su resolucion natural.
                        contenedor.style.display = 'flex';
                        contenedor.style.alignItems = 'center';
                        contenedor.style.justifyContent = 'center';
                        imagen.style.width = 'auto';
                        imagen.style.maxWidth = '100%';
                        imagen.style.maxHeight = '100%';
                        imagen.style.flexShrink = '0';
                        imagen.style.cursor = 'zoom-in';
                        imagen.title = 'Haz clic para ampliar la imagen';

                        let ampliada = false;
                        imagen.addEventListener('click', () => {
                            ampliada = !ampliada;

                            if (ampliada) {
                                contenedor.style.alignItems = 'flex-start';
                                contenedor.style.justifyContent = 'flex-start';
                                imagen.style.maxWidth = 'none';
                                imagen.style.maxHeight = 'none';
                                imagen.style.cursor = 'zoom-out';
                                imagen.title = 'Haz clic para volver a ajustar';
                            } else {
                                contenedor.style.alignItems = 'center';
                                contenedor.style.justifyContent = 'center';
                                imagen.style.maxWidth = '100%';
                                imagen.style.maxHeight = '100%';
                                imagen.style.cursor = 'zoom-in';
                                imagen.title = 'Haz clic para ampliar la imagen';
                                contenedor.scrollTo({ top: 0, left: 0 });
                            }
                        });
                    }

                    contenedor.replaceChildren(imagen);
                    return;
                }

                const visor = document.createElement('iframe');
                visor.src = objectUrl;
                visor.title = titulo;
                visor.style.width = '100%';
                visor.style.height = '100%';
                visor.style.border = '0';
                visor.style.borderRadius = '12px';
                visor.style.background = '#ffffff';
                contenedor.replaceChildren(visor);
            } catch (error) {
                if (cerrado || error?.name === 'AbortError') return;
                const mensaje = document.createElement('div');
                mensaje.style.padding = '2rem 1rem';
                mensaje.style.color = '#b91c1c';
                mensaje.style.fontWeight = '600';
                mensaje.textContent = error?.message || 'No fue posible visualizar el comprobante.';
                contenedor.replaceChildren(mensaje);
            }
        },
        willClose: () => {
            cerrado = true;
            controlador.abort();
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        },
    });
};

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
