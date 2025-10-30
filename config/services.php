<?php

/**
 * config/services.php
 *
 * Limpio y listo para hosting compartido (Hostinger):
 * - Solo define credenciales por ENV (sin dependencias externas).
 * - Valores por defecto seguros y sin activar nada si no hay claves.
 * - Compatible con los mailers opcionales de Laravel (Postmark / Resend / SES).
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Postmark
    |--------------------------------------------------------------------------
    |
    | Usado si configurás el mailer "postmark" en config/mail.php.
    | POSTMARK_TOKEN debe estar presente en el .env para habilitarlo.
    |
    */
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'), // null si no está definido → no se usa
    ],

    /*
    |--------------------------------------------------------------------------
    | Resend
    |--------------------------------------------------------------------------
    |
    | Usado si configurás el mailer "resend" en config/mail.php.
    | RESEND_KEY debe estar presente en el .env para habilitarlo.
    |
    */
    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Amazon SES
    |--------------------------------------------------------------------------
    |
    | Usado si configurás el mailer "ses" en config/mail.php.
    | Requiere credenciales AWS válidas por ENV; si están vacías no se usa.
    |
    */
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack (notificaciones)
    |--------------------------------------------------------------------------
    |
    | Si usás notificaciones a Slack, definí el token y canal por ENV.
    | Si no, dejalo vacío y no se activará nada.
    |
    */
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
