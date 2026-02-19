<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;

class OrdersController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    /**
     * Página principal de pedidos
     */
    public function index(): void
    {
        $currentUser = $this->userService->getCurrentUser();
        $pageTitle = 'Pedidos';
        $title = 'Central de Pedidos';
        $subtitle = 'Gerencie todos os pedidos de suas contas em um só lugar';
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => '/dashboard'],
            ['label' => 'Pedidos']
        ];
        $activePage = 'orders';

        ob_start();
        require __DIR__ . '/../Views/dashboard/orders-content.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layouts/modern/app.php';
    }
}
