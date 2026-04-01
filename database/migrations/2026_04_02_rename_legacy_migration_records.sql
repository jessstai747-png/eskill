-- =============================================================================
-- Reconcilia nomes de migrations legacy na tabela de rastreio.
--
-- Contexto: Arquivos antigos sem prefixo de data foram renomeados para seguir
-- o padrão YYYY_MM_DD_nome.ext. Esta migration atualiza os registros existentes
-- na tabela `migrations` para refletir os novos nomes dos arquivos.
--
-- Executar ANTES de aplicar qualquer nova migration com os nomes renomeados.
-- É idempotente: UPDATE apenas se o registro antigo ainda existir.
-- =============================================================================

UPDATE migrations
    SET migration = '2024_12_22_create_ean_tables.sql'
    WHERE migration = 'create_ean_tables.sql';

UPDATE migrations
    SET migration = '2025_06_01_create_clone_schedules_table.sql'
    WHERE migration = 'create_clone_schedules_table.sql';

UPDATE migrations
    SET migration = '2025_06_02_create_missing_tables.sql'
    WHERE migration = 'create_missing_tables.sql';

UPDATE migrations
    SET migration = '2025_06_03_create_notification_tables.php'
    WHERE migration = 'create_notification_tables.php';

UPDATE migrations
    SET migration = '2025_06_04_create_competitor_watchlist_table.php'
    WHERE migration = 'create_competitor_watchlist_table.php';

UPDATE migrations
    SET migration = '2026_02_24_add_performance_indexes.sql'
    WHERE migration = 'add_performance_indexes.sql';

UPDATE migrations
    SET migration = '2026_02_25_performance_indexes_v2.sql'
    WHERE migration = 'performance_indexes_v2.sql';
