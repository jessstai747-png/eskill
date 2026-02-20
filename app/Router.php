<?php

namespace App;

class Router
{
    private array $routes = [];

    /**
     * Adiciona uma rota GET
     */
    public function get(string $path, string $controller, string $method = 'index'): void
    {
        $this->addRoute('GET', $path, $controller, $method);
    }

    /**
     * Adiciona uma rota POST
     */
    public function post(string $path, string $controller, string $method = 'index'): void
    {
        $this->addRoute('POST', $path, $controller, $method);
    }

    /**
     * Adiciona uma rota PUT
     */
    public function put(string $path, string $controller, string $method = 'index'): void
    {
        $this->addRoute('PUT', $path, $controller, $method);
    }

    /**
     * Adiciona uma rota DELETE
     */
    public function delete(string $path, string $controller, string $method = 'index'): void
    {
        $this->addRoute('DELETE', $path, $controller, $method);
    }

    /**
     * Adiciona rota genérica
     */
    private function addRoute(string $method, string $path, string $controller, string $action): void
    {
        // Normalizar path da rota para garantir consistência
        $path = '/' . ltrim($path, '/');

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action,
        ];
    }

    private ?Core\Container $container = null;

    public function __construct(?Core\Container $container = null)
    {
        $this->container = $container;
    }

    /**
     * Resolve e executa a rota
     */
    public function dispatch(string $method, string $path): void
    {
        $path = $this->normalizePath($path);

        foreach ($this->routes as $route) {
            // HEAD requests should match GET routes
            $routeMethod = $route['method'];
            $requestMethod = $method;
            if ($requestMethod === 'HEAD' && $routeMethod === 'GET') {
                $requestMethod = 'GET';
            }

            if ($routeMethod !== $requestMethod) {
                continue;
            }

            $pattern = $this->pathToRegex($route['path']);

            if (preg_match($pattern, $path, $matches)) {
                // Rota encontrada - definir status 200 ANTES de qualquer output
                if ($this->canSendHeaders()) {
                    http_response_code(200);
                }

                // Remover primeira entrada (match completo)
                array_shift($matches);

                // Extrair apenas os valores nomeados dos matches
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[] = $value;
                    }
                }

                // Criar instância do controller
                $controllerClass = $route['controller'];
                $action = $route['action'];

                try {
                    // Verificar se o controller precisa de accountId no construtor
                    $reflector = new \ReflectionClass($controllerClass);
                    $constructor = $reflector->getConstructor();
                    $needsAccountId = false;
                    
                    if ($constructor) {
                        $constructorParams = $constructor->getParameters();
                        if (!empty($constructorParams)) {
                            $firstParam = $constructorParams[0];
                            $paramName = $firstParam->getName();
                            if ($paramName === 'accountId' && isset($matches['accountId'])) {
                                $needsAccountId = true;
                            }
                        }
                    }
                    
                    if ($needsAccountId && isset($matches['accountId'])) {
                        // Instanciar com accountId diretamente
                        $controller = new $controllerClass((int)$matches['accountId']);
                        // Remover accountId dos params que vão para a action
                        // Corrigido: construir array apenas com params que não sejam accountId
                        $filteredParams = [];
                        foreach ($matches as $key => $value) {
                            if (is_string($key) && $key !== 'accountId') {
                                $filteredParams[] = $value;
                            }
                        }
                        $params = $filteredParams;
                    } elseif ($this->container) {
                        $controller = $this->container->get($controllerClass);
                        if ($controller instanceof \App\Controllers\BaseController) {
                            $controller->setContainer($this->container);
                        }
                    } else {
                        if (!class_exists($controllerClass)) {
                            throw new \Exception("Controller {$controllerClass} não encontrado");
                        }
                        $controller = new $controllerClass();
                    }
                } catch (\Exception $e) {
                    if ($this->canSendHeaders()) {
                        http_response_code(500);
                    }
                    // Sempre registrar internamente a falha para diagnóstico de 500 em produção.
                    log_error('Router: controller instantiation failed', [
                        'controller' => $controllerClass,
                        'action' => $action,
                        'path' => $path,
                        'method' => $method,
                        'error' => $e->getMessage(),
                    ]);
                    // Security: não expor mensagens internas em produção (M2)
                    $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
                    $errorMsg = $isProduction ? 'Erro interno do servidor' : $e->getMessage();
                    echo json_encode(['error' => $errorMsg]);
                    return;
                }

                if (!method_exists($controller, $action)) {
                    if ($this->canSendHeaders()) {
                        http_response_code(500);
                    }
                    log_error('Router: action not found', [
                        'controller' => $controllerClass,
                        'action' => $action,
                        'path' => $path,
                        'method' => $method,
                    ]);
                    // Security: não expor nome do método em produção (M2)
                    $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
                    $msg = $isProduction ? 'Erro interno do servidor' : "Método {$action} não encontrado no controller";
                    echo json_encode(['error' => $msg]);
                    return;
                }

                // Chamar método com parâmetros
                call_user_func_array([$controller, $action], $params);

                // Log para debug
                log_debug('Route matched and executed', ['service' => 'Router', 'path' => $route['path']]);
                return;
            }
        }

        // Rota não encontrada
        if ($this->canSendHeaders()) {
            http_response_code(404);
        }

        // Se for requisição de API, retornar JSON
        if (strpos($path, 'api/') === 0) {
            if ($this->canSendHeaders()) {
                header('Content-Type: application/json');
            }
            // Security: não expor path/method no response 404 (A3)
            echo json_encode(['error' => 'Rota não encontrada']);
        } else {
            // Se for view, mostrar página 404
            $errorPage = __DIR__ . '/Views/errors/404.php';
            if (file_exists($errorPage)) {
                require $errorPage;
            } else {
                echo json_encode(['error' => 'Rota não encontrada']);
            }
        }
    }

    /**
     * Normaliza o caminho
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        return $path === '' ? '/' : '/' . $path;
    }

    /**
     * Converte padrão de rota para regex
     */
    private function pathToRegex(string $path): string
    {
        // Substituir {param} por regex
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        // Escapar barras e adicionar delimitadores
        $pattern = str_replace('/', '\/', $pattern);
        return '#^' . $pattern . '$#';
    }

    private function canSendHeaders(): bool
    {
        return PHP_SAPI !== 'cli' && !headers_sent();
    }
}
