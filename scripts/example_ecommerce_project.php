<?php

/**
 * Exemplo: Criação de projeto E-commerce completo
 * 
 * Demonstra a capacidade do sistema de gerar e gerenciar
 * projetos complexos com muitas features
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Services\Agent\AgentService;

echo "🛒 Criando Projeto E-commerce Completo\n";
echo "=====================================\n\n";

try {
    $agentService = new AgentService();
    
    // Definir requisitos de um e-commerce real
    $requirements = [
        // Catálogo
        'Catálogo de produtos com imagens e descrições',
        'Busca avançada com filtros (preço, categoria, marca)',
        'Ordenação por relevância, preço, novidades',
        'Navegação por categorias e subcategorias',
        
        // Produto
        'Página de produto com galeria de imagens',
        'Avaliações e comentários de clientes',
        'Produtos relacionados e sugestões',
        'Variações de produto (tamanho, cor)',
        'Disponibilidade de estoque em tempo real',
        
        // Carrinho
        'Adicionar/remover produtos do carrinho',
        'Atualizar quantidades',
        'Calcular frete por CEP',
        'Cupons de desconto',
        'Salvar carrinho para depois',
        
        // Checkout
        'Múltiplos endereços de entrega',
        'Opções de frete (PAC, SEDEX, etc)',
        'Pagamento via cartão de crédito',
        'Pagamento via PIX',
        'Pagamento via boleto',
        'Cálculo de parcelas',
        
        // Usuário
        'Cadastro e login de usuário',
        'Recuperação de senha',
        'Perfil do usuário',
        'Histórico de pedidos',
        'Lista de desejos',
        'Endereços salvos',
        
        // Admin
        'Dashboard administrativo',
        'CRUD de produtos',
        'Gerenciamento de estoque',
        'Gerenciamento de pedidos',
        'Relatórios de vendas',
        'Gestão de cupons',
        
        // Notificações
        'Email de confirmação de pedido',
        'Email de envio',
        'Notificações de promoções',
        
        // Segurança
        'Autenticação JWT',
        'Validação de pagamentos',
        'Proteção contra fraude',
        'HTTPS obrigatório',
    ];
    
    echo "📋 Requisitos definidos: " . count($requirements) . " itens\n\n";
    
    // Criar projeto
    echo "🚀 Iniciando criação do projeto...\n";
    $result = $agentService->startProject([
        'name' => 'E-commerce Platform Complete',
        'description' => 'A complete e-commerce platform with product catalog, shopping cart, checkout, user management, admin dashboard, and payment integration',
        'category' => 'ecommerce',
        'requirements' => $requirements,
    ]);
    
    $projectId = $result['project_id'];
    
    echo "\n✅ Projeto criado com sucesso!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📦 ID do Projeto: {$projectId}\n";
    echo "📊 Features Geradas: {$result['features_count']}\n";
    echo "📁 Status: {$result['status']}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Executar algumas sessões iniciais
    echo "🔄 Executando sessões iniciais de desenvolvimento...\n\n";
    
    $sessionsToRun = 5;
    $completedFeatures = [];
    
    for ($i = 1; $i <= $sessionsToRun; $i++) {
        echo "   Sessão #{$i}: ";
        
        $sessionResult = $agentService->runCodingSession($projectId);
        
        $featureId = $sessionResult['feature_worked_on'];
        $completed = $sessionResult['feature_completed'];
        $tests = $sessionResult['tests_passed'];
        
        if ($completed && $tests) {
            echo "✓ Feature {$featureId} completa\n";
            $completedFeatures[] = $featureId;
        } else {
            echo "⏳ Feature {$featureId} em progresso\n";
        }
        
        usleep(200000); // 200ms delay
    }
    
    echo "\n";
    
    // Status final
    echo "📊 Status do Projeto\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $status = $agentService->getProjectStatus($projectId);
    
    echo "Total de Features: {$status['total_features']}\n";
    echo "Features Completas: {$status['completed_features']} (" . number_format($status['completion_percentage'], 1) . "%)\n";
    echo "Features Pendentes: {$status['pending_features']}\n";
    echo "Sessões Executadas: {$status['sessions_count']}\n\n";
    
    echo "Breakdown por Categoria:\n";
    foreach ($status['features_breakdown'] as $category => $count) {
        if ($count > 0) {
            $icon = match($category) {
                'functional' => '⚙️',
                'ui' => '🎨',
                'performance' => '⚡',
                'security' => '🔒',
                default => '📌'
            };
            echo "  {$icon} " . ucfirst($category) . ": {$count}\n";
        }
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Próximos passos
    echo "💡 Próximos Passos\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "1. Continuar desenvolvimento:\n";
    echo "   curl -X POST http://localhost/api/agent/projects/{$projectId}/session\n\n";
    
    echo "2. Ver status completo:\n";
    echo "   curl http://localhost/api/agent/projects/{$projectId}/status\n\n";
    
    echo "3. Executar múltiplas sessões (loop):\n";
    echo "   for i in {1..50}; do\n";
    echo "     curl -X POST http://localhost/api/agent/projects/{$projectId}/session\n";
    echo "     sleep 2\n";
    echo "   done\n\n";
    
    echo "4. Verificar arquivos do projeto:\n";
    echo "   ls -la storage/agent_projects/{$projectId}/\n\n";
    
    echo "5. Ver feature list completa:\n";
    echo "   cat storage/agent_projects/{$projectId}/feature_list.json | jq '.'\n\n";
    
    echo "6. Ver progresso detalhado:\n";
    echo "   cat storage/agent_projects/{$projectId}/claude-progress.txt\n\n";
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    echo "\n📈 Estimativa de Conclusão\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $remainingFeatures = $status['pending_features'];
    $avgTimePerFeature = 2; // minutos (estimativa)
    $estimatedMinutes = $remainingFeatures * $avgTimePerFeature;
    $estimatedHours = round($estimatedMinutes / 60, 1);
    
    echo "Features Restantes: {$remainingFeatures}\n";
    echo "Tempo Estimado: ~{$estimatedHours} horas\n";
    echo "  (considerando {$avgTimePerFeature} min/feature em média)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "✅ E-commerce project setup completo!\n";
    echo "🚀 Sistema pronto para desenvolvimento autônomo!\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
