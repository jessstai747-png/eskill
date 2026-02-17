<?php

namespace App\Controllers;

use App\Core\Container;
use App\Core\Request;

abstract class BaseController
{
    protected ?Container $container = null;
    protected Request $request;

    public function __construct()
    {
        $this->request = new Request();
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    protected function get(string $key)
    {
        if ($this->container) {
            return $this->container->get($key);
        }
        throw new \RuntimeException("Container not set in controller");
    }

    /**
     * Helper para JSON response
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Helper para JSON success response
     */
    protected function jsonSuccess(array $data = [], string $message = ''): void
    {
        $response = ['success' => true];
        if ($message) {
            $response['message'] = $message;
        }
        $this->json(array_merge($response, $data));
    }

    /**
     * Helper para JSON error response
     */
    protected function jsonError(string $message, int $status = 500, array $extra = []): void
    {
        $this->json(array_merge(['success' => false, 'error' => $message], $extra), $status);
    }

    /**
     * Obtém account_id da sessão (tipado como int ou null)
     */
    protected function getAccountId(): ?int
    {
        return isset($_SESSION['account_id']) ? (int) $_SESSION['account_id'] : null;
    }

    /**
     * Obtém account_id com fallback para active_ml_account_id
     */
    protected function getActiveAccountId(): ?int
    {
        // 1) Sessão (fluxo web)
        $id = $_SESSION['active_ml_account_id'] ?? ($_SESSION['account_id'] ?? null);
        if ($id !== null) {
            $id = (int)$id;
            return $id > 0 ? $id : null;
        }

        // 2) Header (fluxo API/CLI): X-ML-Account-Id
        $header = $this->request->header('X-ML-Account-Id');
        if (is_string($header) && $header !== '') {
            $candidate = (int)$header;
            if ($candidate > 0) {
                return $candidate;
            }
        }

        // 3) Query/Body/JSON: ml_account_id (fallback compat: account_id)
        $candidate = $this->request->inputInt('ml_account_id', 0);
        if ($candidate > 0) {
            return $candidate;
        }

        $candidate = $this->request->inputInt('account_id', 0);
        if ($candidate > 0) {
            return $candidate;
        }

        return null;
    }

    /**
     * Obtém user_id da sessão
     */
    protected function getUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Obtém user_role da sessão
     */
    protected function getUserRole(): string
    {
        return (string) ($_SESSION['user_role'] ?? 'user');
    }

    /**
     * Verifica se o usuário é admin
     */
    protected function isAdmin(): bool
    {
        return !empty($_SESSION['is_admin']) || ($this->getUserRole() === 'admin');
    }

    /**
     * Obtém valor da sessão
     */
    protected function session(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Exige account_id ou retorna erro 401
     */
    protected function requireAccountId(): int
    {
        $id = $this->getAccountId();
        if (!$id) {
            $this->jsonError('Não autorizado', 401);
        }
        return $id;
    }

    /**
     * Exige user_id ou retorna erro 401
     */
    protected function requireUserId(): int
    {
        $id = $this->getUserId();
        if (!$id) {
            $this->jsonError('Autenticação necessária', 401);
        }
        return $id;
    }

    /**
     * Executa a lógica do controller dentro de try/catch padronizado
     * com logging automático de erros e respostas consistentes.
     * 
     * Uso:
     *   $this->withErrorHandling(function() {
     *       $data = $this->service->getData();
     *       $this->jsonSuccess(['items' => $data]);
     *   }, 'MyController::myMethod');
     */
    protected function withErrorHandling(callable $callback, string $context = ''): void
    {
        try {
            $callback();
        } catch (\PDOException $e) {
            $this->logError($e, $context);
            $this->jsonError('Erro de banco de dados', 500);
        } catch (\InvalidArgumentException $e) {
            $this->logError($e, $context);
            $this->jsonError($e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logError($e, $context);
            $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
            $msg = $isProduction ? 'Erro interno do servidor' : 'Erro interno: ' . $e->getMessage();
            $this->jsonError($msg, 500);
        }
    }

    /**
     * Log de erro consistente com contexto
     */
    protected function logError(\Throwable $e, string $context = ''): void
    {
        log_error($e->getMessage(), [
            'context' => $context ?: get_class($this),
            'exception_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    /**
     * Renderiza uma view com layout padrão
     *
     * @param string $viewPath  Caminho relativo à pasta Views (ex: 'dashboard/quality')
     * @param array  $data      Variáveis disponíveis na view via extract()
     * @param string|null $layout  Layout a usar (null = layout padrão)
     */
    protected function renderView(string $viewPath, array $data = [], ?string $layout = 'layouts/modern/app'): void
    {
        \App\Helpers\ViewHelper::render($viewPath, $data, $layout);
    }
}
