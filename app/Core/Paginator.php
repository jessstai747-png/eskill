<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Paginator — Encapsula lógica de paginação de listas.
 *
 * Substitui o boilerplate duplicado em 10+ controllers:
 *   $page    = max(1, (int) ($request->getInt('page', 1)));
 *   $perPage = min(100, max(1, (int) ($request->getInt('per_page', 20))));
 *   $offset  = ($page - 1) * $perPage;
 *   'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => ceil($total/$perPage)]
 *
 * Uso básico:
 *   $p = Paginator::fromRequest($request, total: $total);
 *   $results = $model->findAll($p->limit(), $p->offset());
 *   $this->jsonSuccess(['items' => $results, 'pagination' => $p->meta()]);
 *
 * Uso com array já carregado (paginação em memória):
 *   $p = Paginator::fromRequest($request);
 *   $slice = $p->slice($items);  // corta o array e atualiza $total automaticamente
 *   $this->jsonSuccess(['items' => $slice, 'pagination' => $p->meta()]);
 *
 * Construção manual (sem request):
 *   $p = new Paginator(page: 2, perPage: 50, total: 120);
 */
class Paginator
{
    private int $page;
    private int $perPage;
    private int $total;

    /**
     * @param int $page    Página atual (≥ 1)
     * @param int $perPage Itens por página (1–500)
     * @param int $total   Total de registros (pode ser atualizado depois via setTotal)
     */
    public function __construct(int $page = 1, int $perPage = 20, int $total = 0)
    {
        $this->page    = max(1, $page);
        $this->perPage = max(1, min(500, $perPage));
        $this->total   = max(0, $total);
    }

    /**
     * Cria Paginator a partir de um objeto Request, lendo os parâmetros
     * 'page' e 'per_page' (ou 'limit') de qualquer fonte (GET/POST/JSON).
     *
     * @param Request $request
     * @param int     $total        Total de registros já conhecido (opcional)
     * @param int     $defaultPerPage  Valor padrão para per_page (default: 20)
     * @param int     $maxPerPage      Limite máximo de per_page (default: 100)
     */
    public static function fromRequest(
        Request $request,
        int $total = 0,
        int $defaultPerPage = 20,
        int $maxPerPage = 100
    ): self {
        $page    = max(1, $request->inputInt('page', 1));
        $perPage = $request->inputInt('per_page',
                     $request->inputInt('limit', $defaultPerPage));
        $perPage = max(1, min($maxPerPage, $perPage));

        return new self($page, $perPage, $total);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /** Número da página atual (base 1) */
    public function page(): int
    {
        return $this->page;
    }

    /** Número de itens por página */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /** Total de registros */
    public function total(): int
    {
        return $this->total;
    }

    /** Número total de páginas */
    public function lastPage(): int
    {
        if ($this->total === 0) {
            return 1;
        }
        return (int) ceil($this->total / $this->perPage);
    }

    /** SQL LIMIT */
    public function limit(): int
    {
        return $this->perPage;
    }

    /** SQL OFFSET */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** Verdadeiro se houver uma página anterior */
    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    /** Verdadeiro se houver uma próxima página */
    public function hasNext(): bool
    {
        return $this->page < $this->lastPage();
    }

    // ─── Mutations ────────────────────────────────────────────────────────────

    /**
     * Define o total de registros (útil quando o total é calculado após
     * a construção do Paginator).
     */
    public function setTotal(int $total): self
    {
        $this->total = max(0, $total);
        return $this;
    }

    // ─── Convenience ──────────────────────────────────────────────────────────

    /**
     * Recorta um array em memória na fatia correta e atualiza o total.
     * Ideal para listas pequenas que já foram carregadas inteiras.
     *
     * @param array<mixed> $items
     * @return array<mixed>
     */
    public function slice(array $items): array
    {
        $this->total = count($items);
        return array_slice($items, $this->offset(), $this->perPage);
    }

    /**
     * Retorna o envelope de metadata de paginação compatível com o formato
     * já utilizado nos controllers existentes.
     *
     * Formato:
     * {
     *   "page":      1,
     *   "per_page":  20,
     *   "total":     253,
     *   "pages":     13,
     *   "has_prev":  false,
     *   "has_next":  true
     * }
     *
     * @return array<string, int|bool>
     */
    public function meta(): array
    {
        return [
            'page'     => $this->page,
            'per_page' => $this->perPage,
            'total'    => $this->total,
            'pages'    => $this->lastPage(),
            'has_prev' => $this->hasPrev(),
            'has_next' => $this->hasNext(),
        ];
    }
}
