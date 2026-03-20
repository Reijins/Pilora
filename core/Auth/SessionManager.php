<?php
declare(strict_types=1);

namespace Core\Auth;

use Core\Config;

final class SessionManager
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(Config::sessionName());

        $lifetime = Config::sessionLifetimeSeconds();
        $inactivity = Config::sessionInactivityTimeoutSeconds();

        // Durcissement minimal côté PHP (les règles “qui invalider quand” seront
        // gérées ensuite via UserSession en base).
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        // lifetime global du cookie (approx. sécurité + pratique).
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // Optionnel: on conserve l’info d’inactivité dans la session.
        if (!isset($_SESSION['last_activity_at'])) {
            $_SESSION['last_activity_at'] = time();
        }
        if (!isset($_SESSION['inactivity_timeout_seconds'])) {
            $_SESSION['inactivity_timeout_seconds'] = $inactivity;
        }
    }
}

