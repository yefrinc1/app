export const mensajeActivacionPortal = (url) => [
    '🎮 Vas a ingresar a nuestro portal MRJUEGOZ, donde encontrarás los datos de tus juegos.',
    '🔐 Como es tu primera vez, completa tus datos, crea tu contraseña y verifica tu correo con el enlace que te llegará.',
    'Una vez dentro, abre el juego que necesitas:',
    '✅ Si lo vas a instalar ahora, sigue el video paso a paso y sube la foto o el video que se indique para activar la garantía.',
    '🔒 Si el juego no te funciona o tiene candado, entra a esa opción de ayuda. Ahí verás el video y, si aplica, podrás generar el código.',
    'Quedamos atentos para acompañarte. 🙌',
    '👇 Activa tu cuenta aquí:\n' + url,
].join('\n\n');

export const mensajeIngresoPortal = (url, email) => [
    '🎮 ¡Gracias por volver a comprar en MRJUEGOZ!',
    'Tu cuenta del portal ya está registrada. Ingresa con tu correo y contraseña. No necesitas crear otra cuenta. 🔐',
    email ? '📧 Inicia sesión con ' + email + ' y tu contraseña habitual.' : '📧 Inicia sesión con tu correo y contraseña habituales.',
    'Ahí verás tus juegos. Si es una instalación nueva, sigue el video y sube la evidencia. Si el juego tiene candado, usa la ayuda de esa pantalla.',
    'Si necesitas ayuda, estamos atentos. 🙌',
    '👇 Ingresa a tu portal aquí:\n' + url,
].join('\n\n');
