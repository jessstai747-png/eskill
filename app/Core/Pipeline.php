<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pipeline — Encadeia callables em ordem, passando um subject por todos eles.
 *
 * Padrão: cada estágio recebe o subject e um $next callable, podendo
 * transformar ou interromper o fluxo.
 *
 * Uso típico com middlewares:
 *   $result = (new Pipeline($request))
 *       ->pipe(new RateLimitMiddleware())
 *       ->pipe(new AuthMiddleware())
 *       ->pipe(new CsrfMiddleware())
 *       ->then(fn($req) => $router->dispatch($req->method(), $req->uri()));
 *
 * Uso com transformações de dados:
 *   $html = (new Pipeline($markdownString))
 *       ->pipe(fn($text, $next) => $next(trim($text)))
 *       ->pipe(fn($text, $next) => $next(markdown_to_html($text)))
 *       ->pipe(fn($text, $next) => $next(htmlspecialchars_decode($text)))
 *       ->thenReturn();
 *
 * Cada estágio pode ser:
 *   - callable(mixed $subject, callable $next): mixed
 *   - object com método handle(mixed $subject, callable $next): mixed
 *   - object com método process(mixed $subject, callable $next): mixed
 */
class Pipeline
{
    private mixed $subject;
    /** @var array<int, callable|object> */
    private array $stages = [];

    public function __construct(mixed $subject = null)
    {
        $this->subject = $subject;
    }

    /**
     * Define (ou substitui) o subject que será passado pelo pipeline.
     */
    public function send(mixed $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Adiciona um estágio ao pipeline.
     *
     * @param callable|object $stage
     */
    public function pipe(callable|object $stage): self
    {
        $this->stages[] = $stage;
        return $this;
    }

    /**
     * Executa o pipeline e passa o resultado final para $destination.
     *
     * @param callable $destination  Callable final que receberá o subject processado.
     * @return mixed  O que $destination retornar.
     */
    public function then(callable $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->stages),
            $this->buildCarry(),
            $destination
        );

        return $pipeline($this->subject);
    }

    /**
     * Executa o pipeline e retorna o subject transformado.
     * Equivale a then(fn($s) => $s).
     */
    public function thenReturn(): mixed
    {
        return $this->then(fn(mixed $subject) => $subject);
    }

    /**
     * Fábrica de carrys — constrói a cadeia de chamadas invertida.
     */
    private function buildCarry(): callable
    {
        return static function (callable $next, callable|object $stage): callable {
            return static function (mixed $subject) use ($next, $stage): mixed {
                // Aceita callables puros
                if (is_callable($stage)) {
                    return $stage($subject, $next);
                }

                // Aceita objetos com handle() ou process()
                if (method_exists($stage, 'handle')) {
                    return $stage->handle($subject, $next);
                }

                if (method_exists($stage, 'process')) {
                    return $stage->process($subject, $next);
                }

                throw new \InvalidArgumentException(
                    'Pipeline stage ' . get_class($stage) . ' must be callable or have handle()/process().'
                );
            };
        };
    }
}
