<?php
declare(strict_types=1);

use Core\Bootstrap;

require dirname(__DIR__) . '/core/Bootstrap.php';

$bootstrap = new Bootstrap();
$bootstrap->handle();

