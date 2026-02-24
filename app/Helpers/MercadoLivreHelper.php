<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper para construção de URLs e formatação de IDs do Mercado Livre
 *
 * Centraliza a lógica de construção de URLs de itens/anúncios do ML Brasil.
 * Regras:
 *  - Item IDs (ex: MLB1234567890) usam domínio produto.mercadolivre.com.br
 *  - O dash é obrigatório entre o prefixo de site (3 letras) e a parte numérica
 *  - O path /p/ em www.mercadolivre.com.br é para fichas de catálogo, NÃO para anúncios
 *
 * @version 1.0.0
 */
class MercadoLivreHelper
{
    /**
     * Domínio base para URLs de itens/anúncios no ML Brasil
     */
    private const ITEM_BASE_URL = 'https://produto.mercadolivre.com.br';

    /**
     * Regex para validar e capturar partes de um ML item ID.
     * Captura: grupo 1 = prefixo de site (ex: MLB), grupo 2 = parte numérica
     */
    private const ITEM_ID_PATTERN = '/^([A-Z]{3})(\d+)$/';

    /**
     * Formata um item ID do Mercado Livre inserindo o dash canônico.
     *
     * MLB1234567890 → MLB-1234567890
     * MLA5678       → MLA-5678
     * MLB-1234      → MLB-1234 (já formatado, retorna inalterado)
     *
     * @param string $itemId  ID do item (com ou sem dash)
     * @return string         ID formatado com dash
     */
    public static function formatItemId(string $itemId): string
    {
        $itemId = trim($itemId);

        // Se já contém dash na posição correta, retorna como está
        if (preg_match('/^[A-Z]{3}-\d+$/', $itemId)) {
            return $itemId;
        }

        // Insere dash entre prefixo (3 letras) e parte numérica
        if (preg_match(self::ITEM_ID_PATTERN, $itemId, $matches)) {
            return $matches[1] . '-' . $matches[2];
        }

        // ID não reconhecido — retorna inalterado
        return $itemId;
    }

    /**
     * Constrói a URL completa de um anúncio no Mercado Livre Brasil.
     *
     * @param string $itemId  ID do item (ex: MLB1234567890 ou MLB-1234567890)
     * @return string         URL completa (ex: https://produto.mercadolivre.com.br/MLB-1234567890)
     */
    public static function itemUrl(string $itemId): string
    {
        return self::ITEM_BASE_URL . '/' . self::formatItemId($itemId);
    }

    /**
     * Extrai o item ID de uma URL do Mercado Livre.
     *
     * @param string $url  URL do anúncio
     * @return string|null Item ID extraído (com dash) ou null se não reconhecido
     */
    public static function extractItemId(string $url): ?string
    {
        // Suporta ambos os formatos de URL:
        // https://produto.mercadolivre.com.br/MLB-1234567890-titulo-do-anuncio
        // https://www.mercadolivre.com.br/p/MLB1234567890
        if (preg_match('/([A-Z]{3})-?(\d+)/', $url, $matches)) {
            return $matches[1] . '-' . $matches[2];
        }

        return null;
    }
}
