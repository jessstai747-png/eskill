<?php
declare(strict_types=1);

/** @var \App\Router $router */

// Carregar sub-arquivos de rotas
require __DIR__ . '/Routes/auth.php';
require __DIR__ . '/Routes/web.php';
require __DIR__ . '/Routes/api.php';
require __DIR__ . '/Routes/webhooks.php';
require __DIR__ . '/Routes/fase8_routes.php';
