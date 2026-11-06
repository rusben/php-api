<?php

declare(strict_types=1);

/**
 * Front controller de la API. Todas las peticiones a /api/* terminan aquí
 * (ver .htaccess para Apache o "composer start" para el servidor integrado de PHP).
 */

use App\Application;
use App\Config\Config;
use App\Http\Request;

require __DIR__ . '/../../vendor/autoload.php';

Config::load(dirname(__DIR__, 2));

// La API responde siempre JSON: los errores de PHP no deben ensuciar la salida (se registran en el log).
ini_set('display_errors', '0');

$request = Request::fromGlobals((string) Config::get('BASE_PATH', '/api'));

(new Application())->handle($request)->send();
