export const copiarTexto = async (texto) => {
    const valor = String(texto ?? '');

    if (!valor) {
        throw new Error('No hay contenido para copiar.');
    }

    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(valor);
            return;
        } catch (_) {
            // Algunos navegadores bloquean la API aunque se esté usando HTTPS.
        }
    }

    const area = document.createElement('textarea');
    area.value = valor;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.left = '-9999px';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.focus();
    area.select();

    const copiado = document.execCommand('copy');
    document.body.removeChild(area);

    if (!copiado) {
        throw new Error('El navegador no permitió copiar automáticamente.');
    }
};
