<?php

namespace App\Controllers;

use App\Services\UserService;

class ProfileController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function index(): void
    {
        $currentUser = $this->userService->getCurrentUser();
        $pageTitle = 'Meu Perfil';
        $activePage = 'profile';
        
        ob_start();
        require __DIR__ . '/../Views/dashboard/profile-content.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }
}
