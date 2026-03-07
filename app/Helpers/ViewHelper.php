<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper para renderização de views
 *
 * Fornece a função global view() e métodos utilitários para renderizar
 * templates PHP com dados e layout.
 */
class ViewHelper
{
    /**
     * Renderiza uma view com dados opcionais e layout padrão
     *
     * @param string $viewPath  Caminho relativo à pasta Views (ex: 'dashboard/openspec/index')
     * @param array  $data      Variáveis disponíveis na view via extract()
     * @param string|null $layout  Layout a usar (null = layout padrão modern/app)
     */
    public static function render(string $viewPath, array $data = [], ?string $layout = 'layouts/modern/app'): void
    {
        $basePath = dirname(__DIR__) . '/Views/';
        $viewFile = $basePath . str_replace('.', '/', $viewPath) . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(404);
            echo "View not found: {$viewPath}";
            return;
        }

        // Make CSP nonce available in all views (using GLOBALS as authoritative source)
        $cspNonce = $GLOBALS['cspNonce'] ?? $_SESSION['csp_nonce'] ?? '';

        // Extrai dados como variáveis locais para a view
        extract($data, EXTR_SKIP);

        // Captura conteúdo da view
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Se há layout, renderiza dentro dele
        if ($layout) {
            $layoutFile = $basePath . $layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
            } else {
                // Sem layout, apenas exibe o conteúdo
                echo $content;
            }
        } else {
            echo $content;
        }
    }

    /**
     * Renderiza uma view sem layout (apenas o conteúdo)
     */
    public static function partial(string $viewPath, array $data = []): void
    {
        self::render($viewPath, $data, null);
    }
}

// ============================================================================
// Função global view() — usada por controllers como OpenSpecController
// ============================================================================
if (!function_exists('view')) {
    /**
     * Renderiza uma view com layout padrão
     *
     * @param string $viewPath  Caminho relativo à pasta Views
     * @param array  $data      Variáveis disponíveis na view
     */
    function view(string $viewPath, array $data = []): void
    {
        \App\Helpers\ViewHelper::render($viewPath, $data);
    }
}
