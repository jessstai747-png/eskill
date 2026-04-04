<?php

declare(strict_types=1);

namespace App\Core;

/**
 * EventBus — Barramento de eventos para comunicação desacoplada entre serviços.
 *
 * Uso:
 *   // Registrar listener
 *   $bus->listen('item.optimized', function(array $payload) {
 *       // reagir ao evento
 *   });
 *
 *   // Despachar evento
 *   $bus->dispatch('item.optimized', ['item_id' => 123, 'score' => 98]);
 *
 *   // Subscriber class (implementa getSubscribedEvents())
 *   $bus->subscribe(new AuditSubscriber());
 *
 * Listeners são chamados em ordem de prioridade (maior = primeiro).
 * Se um listener lançar exceção, ela é capturada e logada sem interromper
 * os demais listeners (fail-safe).
 */
class EventBus
{
    /**
     * @var array<string, array<int, array<int, callable>>>
     *   Structure: ['event.name' => [priority => [callable, callable, ...]]]
     */
    private array $listeners = [];

    /**
     * Registra um listener para um evento.
     *
     * @param string   $event    Nome do evento (ex: 'item.optimized')
     * @param callable $listener Callback que recebe array $payload
     * @param int      $priority Prioridade — maior número executa primeiro (default: 0)
     */
    public function listen(string $event, callable $listener, int $priority = 0): void
    {
        $this->listeners[$event][$priority][] = $listener;
    }

    /**
     * Despacha um evento para todos os listeners registrados.
     * Listeners são chamados do maior ao menor priority.
     * Exceptions são silenciadas e logadas (fail-safe).
     *
     * @param string $event   Nome do evento
     * @param array<string, mixed> $payload Dados do evento
     */
    public function dispatch(string $event, array $payload = []): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        // Sort by priority descending (higher number = first)
        $priorityGroups = $this->listeners[$event];
        krsort($priorityGroups);

        foreach ($priorityGroups as $listeners) {
            foreach ($listeners as $listener) {
                try {
                    $listener($payload);
                } catch (\Throwable $e) {
                    // Fail-safe: log but continue dispatching to remaining listeners
                    if (function_exists('log_error')) {
                        log_error('EventBus listener falhou', [
                            'event'     => $event,
                            'exception' => $e->getMessage(),
                            'file'      => $e->getFile(),
                            'line'      => $e->getLine(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Registra um subscriber — objeto com método getSubscribedEvents().
     *
     * getSubscribedEvents() deve retornar:
     *   ['event.name' => 'methodName']   // priority 0
     *   ['event.name' => ['methodName', 10]]  // with priority
     *
     * @param object $subscriber
     */
    public function subscribe(object $subscriber): void
    {
        if (!method_exists($subscriber, 'getSubscribedEvents')) {
            throw new \InvalidArgumentException(
                get_class($subscriber) . ' deve implementar getSubscribedEvents(): array'
            );
        }

        /** @var array<string, string|array{0: string, 1?: int}> $events */
        $events = $subscriber->getSubscribedEvents();

        foreach ($events as $event => $spec) {
            if (is_string($spec)) {
                $this->listen($event, [$subscriber, $spec]);
            } elseif (is_array($spec)) {
                $method   = $spec[0];
                $priority = (int) ($spec[1] ?? 0);
                $this->listen($event, [$subscriber, $method], $priority);
            }
        }
    }

    /**
     * Verifica se há listeners registrados para um evento.
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * Remove todos os listeners de um evento.
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Remove todos os listeners de todos os eventos.
     */
    public function forgetAll(): void
    {
        $this->listeners = [];
    }
}
