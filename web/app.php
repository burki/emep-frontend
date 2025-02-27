<?php

use Symfony\Component\HttpFoundation\Request;

$loader = require __DIR__.'/../config/autoload.php';
require_once __DIR__.'/../src/MicroKernel.php';

$kernel = MicroKernel::fromEnvironment();

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
