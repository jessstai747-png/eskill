-- Migration: Corrige a VIEW ml_items, que ficou incompleta apos a consolidacao
-- da antiga tabela `ml_items` na tabela `items` (ver 20260122_create_ml_items_table.php
-- para o desenho original da tabela, hoje substituida por esta view).
--
-- A view atual (CREATE VIEW ml_items AS SELECT ... FROM items) nao expõe
-- `sold_quantity`, `sku`, `thumbnail` e `permalink`, mas codigo de produção
-- ativo consulta essas colunas via ml_items:
--   - App\Services\ItemService::listItems() usa `sold_quantity > 0` no filtro
--     `high_sales` (WHERE) e faz `SELECT *` em getItem() — ambos falhavam com
--     "Unknown column 'sold_quantity'"/dados incompletos.
--   - App\Services\MercadoLivre\AdvancedPricingEngine e outros leem via
--     `SELECT * FROM ml_items` ou colunas especificas que dependem do
--     conjunto completo de colunas de `items`.
--
-- CREATE OR REPLACE VIEW é idempotente — seguro re-executar.

CREATE OR REPLACE VIEW `ml_items` AS
SELECT
    `items`.`id` AS `id`,
    `items`.`ml_item_id` AS `ml_item_id`,
    `items`.`account_id` AS `account_id`,
    `items`.`title` AS `title`,
    `items`.`sku` AS `sku`,
    `items`.`category_id` AS `category_id`,
    `items`.`price` AS `price`,
    `items`.`currency_id` AS `currency_id`,
    `items`.`available_quantity` AS `available_quantity`,
    `items`.`sold_quantity` AS `sold_quantity`,
    `items`.`status` AS `status`,
    `items`.`condition_type` AS `condition_type`,
    `items`.`catalog_product_id` AS `catalog_product_id`,
    `items`.`permalink` AS `permalink`,
    `items`.`thumbnail` AS `thumbnail`,
    `items`.`data` AS `data`,
    `items`.`created_at` AS `created_at`,
    `items`.`updated_at` AS `updated_at`
FROM `items`;
