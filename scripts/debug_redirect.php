<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/core/Autoloader.php';

(new Core\Autoloader())->register();

use Core\Config;
use Core\Http\Response;

function runCase(string $scriptName, string $requestUri): void
{
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    $_SERVER['REQUEST_URI'] = $requestUri;
    $_SERVER['HTTP_HOST'] = 'localhost';

    $resp = Response::redirect('login');

    // Capturer le contenu des headers sans envoyer de réponse HTTP.
    // (On ne peut pas lire directement la propriété privée, donc on hack via reflection)
    $ref = new ReflectionClass($resp);
    $prop = $ref->getProperty('headers');
    $prop->setAccessible(true);
    $headers = $prop->getValue($resp);

    echo "CASE: SCRIPT_NAME={$scriptName} | REQUEST_URI={$requestUri}\n";
    echo "Base/Target headers:\n";
    foreach ($headers as $k => $v) {
        echo "  {$k}: {$v}\n";
    }
    echo "\n";
}

echo "DEBUG MODE: " . (Config::isDebug() ? "yes" : "no") . "\n\n";

runCase(scriptName: '/pilora/index.php', requestUri: '/pilora/login');
runCase(scriptName: '/index.php', requestUri: '/pilora/login');
runCase(scriptName: '/pilora/index.php', requestUri: '/login');
runCase(scriptName: '', requestUri: '/pilora/login');

