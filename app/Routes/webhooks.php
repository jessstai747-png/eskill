<?php

use App\Controllers\MercadoLivreWebhookController;

/** @var \App\Router $router */

// Rotas de webhooks
$router->post('webhook/ml', MercadoLivreWebhookController::class, 'receive');
$router->post('webhook/mercadolivre', MercadoLivreWebhookController::class, 'receive');
$router->post('dashboard', MercadoLivreWebhookController::class, 'receive');
