<?php

// Sitio de mercadeo — páginas de error.
return [
    'help' => '¿Necesita ayuda? Llámenos al :phone.',
    'home' => 'Ir a la página de inicio',
    'jobs' => 'Ver ofertas de trabajo',
    'contact' => 'Contáctenos',
    'back' => 'Regresar',

    'expired_form' => 'Este formulario estuvo abierto por un tiempo y no pudimos recibirlo. Sus respuestas siguen aquí; por favor envíelo de nuevo.',

    403 => [
        'title' => 'Acceso no permitido',
        'message' => 'No tiene acceso a esta página.',
    ],
    404 => [
        'title' => 'Página no encontrada',
        'message' => 'No pudimos encontrar la página que busca. Es posible que se haya movido o que el enlace ya no esté vigente.',
    ],
    419 => [
        'title' => 'Página vencida',
        'message' => 'Esta página estuvo abierta por un tiempo. Regrese, actualice la página e inténtelo de nuevo.',
    ],
    429 => [
        'title' => 'Demasiados intentos',
        'message' => 'Recibimos varias solicitudes suyas en poco tiempo. Espere un minuto e inténtelo de nuevo.',
    ],
    500 => [
        'title' => 'Algo salió mal',
        'message' => 'Algo salió mal de nuestro lado. Inténtelo de nuevo en unos minutos.',
    ],
    503 => [
        'title' => 'Volvemos enseguida',
        'message' => 'Estamos haciendo mejoras en el sitio. Vuelva a visitarnos en unos minutos.',
    ],
];
