export const mensajeActivacionPortal = (url) => [
    '🎮 Vas a ingresar a nuestro portal MRJUEGOZ, donde encontrarás los datos de acceso de tu juego.',
    '🔐 Como es tu primera vez, completa tus datos, crea tu contraseña y verifica tu correo mediante el enlace que recibirás.',
    'Una vez dentro, sigue paso a paso las instrucciones del video que encontrarás allí para realizar correctamente la instalación y activación del juego. ✅',
    '📸 Al finalizar, sube al portal la foto o el video que se indique según tu tipo de cuenta. Esta evidencia será necesaria para verificar la instalación y dejar registrada y activa la garantía de tu juego.',
    'Quedamos atentos para acompañarte durante todo el proceso. 🙌',
    '👇 Activa tu cuenta aquí:\n' + url,
].join('\n\n');

export const mensajeIngresoPortal = (url, email) => [
    '🎮 ¡Gracias por volver a comprar en MRJUEGOZ!',
    'Tu cuenta del portal ya está registrada. Ingresa para consultar tus compras y los datos de tus juegos disponibles. No necesitas crear otra cuenta. 🔐',
    email ? '📧 Inicia sesión con ' + email + ' y tu contraseña habitual.' : '📧 Inicia sesión con tu correo y contraseña habituales.',
    'Para instalar tu juego, sigue paso a paso el video y las instrucciones que encontrarás en el portal. ✅',
    '📸 Al finalizar, sube la foto o el video solicitado según tu tipo de cuenta para que podamos verificar la instalación y activar la garantía.',
    'Si necesitas ayuda, estamos atentos para acompañarte. 🙌',
    '👇 Ingresa a tu portal aquí:\n' + url,
].join('\n\n');
