<?php

namespace App\Services\ListingBuilder;

use App\Database;
use PDO;

/**
 * Template Manager Service - Gerencia templates de descrição
 * 
 * Funcionalidades:
 * - Templates personalizáveis por categoria
 * - Variáveis dinâmicas
 * - Estilos pré-formatados
 * - Biblioteca de blocos reutilizáveis
 */
class TemplateManagerService
{
    private array $templates = [];
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadDefaultTemplates();
        $this->loadCustomTemplatesFromDb();
    }

    /**
     * Retorna templates disponíveis para uma categoria
     */
    public function getTemplatesByCategory(string $categoryId): array
    {
        $allTemplates = $this->getAllTemplates();
        
        // Filtrar templates aplicáveis à categoria
        $applicable = array_filter($allTemplates, function($template) use ($categoryId) {
            return empty($template['categories']) || 
                   in_array($categoryId, $template['categories']) ||
                   in_array('all', $template['categories']);
        });

        return array_values($applicable);
    }

    /**
     * Retorna template específico
     */
    public function getTemplate(string $templateId): ?array
    {
        return $this->templates[$templateId] ?? null;
    }

    /**
     * Renderiza template com dados fornecidos
     */
    public function renderTemplate(string $templateId, array $data): string
    {
        $template = $this->getTemplate($templateId);
        
        if (!$template) {
            return '';
        }

        $html = $template['content'];

        // Substituir variáveis
        foreach ($template['variables'] ?? [] as $var) {
            $placeholder = "{{" . $var . "}}";
            $value = $data[$var] ?? '';
            
            // Processar valor
            if (is_array($value)) {
                $value = $this->processArrayVariable($var, $value);
            }
            
            $html = str_replace($placeholder, $value, $html);
        }

        // Remover variáveis não preenchidas
        $html = preg_replace('/\{\{[^}]+\}\}/', '', $html);

        return $html;
    }

    /**
     * Cria template personalizado
     */
    public function createCustomTemplate(array $templateData): array
    {
        $templateId = 'custom_' . uniqid();
        
        $this->templates[$templateId] = [
            'id' => $templateId,
            'name' => $templateData['name'] ?? 'Template Personalizado',
            'description' => $templateData['description'] ?? '',
            'categories' => $templateData['categories'] ?? ['all'],
            'content' => $templateData['content'] ?? '',
            'variables' => $this->extractVariables($templateData['content'] ?? ''),
            'preview_image' => $templateData['preview_image'] ?? null,
            'custom' => true,
        ];

        // Persist to database
        try {
            $this->ensureCustomTemplatesTable();
            $stmt = $this->db->prepare("
                INSERT INTO listing_custom_templates (template_id, name, description, categories, content, variables, preview_image, created_at)
                VALUES (:id, :name, :description, :categories, :content, :variables, :preview_image, NOW())
            ");
            $stmt->execute([
                'id'            => $templateId,
                'name'          => $this->templates[$templateId]['name'],
                'description'   => $this->templates[$templateId]['description'],
                'categories'    => json_encode($this->templates[$templateId]['categories']),
                'content'       => $this->templates[$templateId]['content'],
                'variables'     => json_encode($this->templates[$templateId]['variables']),
                'preview_image' => $this->templates[$templateId]['preview_image'],
            ]);
        } catch (\Exception $e) {
            // Log but don't fail — the in-memory template still works
            log_warning('Falha ao persistir template customizado', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'template_id' => $templateId,
            'template' => $this->templates[$templateId],
        ];
    }

    /**
     * Retorna todos os templates
     */
    public function getAllTemplates(): array
    {
        return array_values($this->templates);
    }

    /**
     * Retorna blocos reutilizáveis
     */
    public function getBlocks(): array
    {
        return [
            'specs_table' => [
                'name' => 'Tabela de Especificações',
                'content' => $this->getSpecsTableBlock(),
            ],
            'features_list' => [
                'name' => 'Lista de Características',
                'content' => $this->getFeaturesListBlock(),
            ],
            'warranty' => [
                'name' => 'Garantia',
                'content' => $this->getWarrantyBlock(),
            ],
            'shipping_info' => [
                'name' => 'Informações de Envio',
                'content' => $this->getShippingInfoBlock(),
            ],
            'contact' => [
                'name' => 'Contato',
                'content' => $this->getContactBlock(),
            ],
        ];
    }

    // ===================================
    // TEMPLATES PADRÃO
    // ===================================

    private function loadDefaultTemplates(): void
    {
        $this->templates = [
            'modern' => $this->getModernTemplate(),
            'classic' => $this->getClassicTemplate(),
            'minimal' => $this->getMinimalTemplate(),
            'professional' => $this->getProfessionalTemplate(),
            'ecommerce' => $this->getEcommerceTemplate(),
        ];
    }

    private function getModernTemplate(): array
    {
        return [
            'id' => 'modern',
            'name' => 'Moderno',
            'description' => 'Design moderno e limpo com ênfase visual',
            'categories' => ['all'],
            'variables' => ['product_name', 'brand', 'features', 'specs', 'warranty', 'includes'],
            'preview_image' => '/templates/modern-preview.jpg',
            'content' => <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px;">
        <h1 style="margin: 0; font-size: 32px; font-weight: bold;">{{product_name}}</h1>
        <p style="margin: 10px 0 0 0; font-size: 18px; opacity: 0.9;">{{brand}}</p>
    </div>

    <div style="background: #f8f9fa; padding: 25px; border-radius: 10px; margin-bottom: 25px;">
        <h2 style="color: #667eea; font-size: 24px; margin-top: 0;">✨ Características Principais</h2>
        {{features}}
    </div>

    <div style="margin-bottom: 25px;">
        <h2 style="color: #667eea; font-size: 24px;">📋 Especificações Técnicas</h2>
        {{specs}}
    </div>

    <div style="background: #e8f5e9; padding: 20px; border-radius: 10px; border-left: 4px solid #4caf50; margin-bottom: 25px;">
        <h2 style="color: #2e7d32; font-size: 20px; margin-top: 0;">✅ Garantia</h2>
        <p>{{warranty}}</p>
    </div>

    <div style="background: #fff3e0; padding: 20px; border-radius: 10px; border-left: 4px solid #ff9800;">
        <h2 style="color: #e65100; font-size: 20px; margin-top: 0;">📦 O Que Está Incluído</h2>
        {{includes}}
    </div>
</div>
HTML
        ];
    }

    private function getClassicTemplate(): array
    {
        return [
            'id' => 'classic',
            'name' => 'Clássico',
            'description' => 'Template tradicional e profissional',
            'categories' => ['all'],
            'variables' => ['product_name', 'brand', 'description', 'features', 'specs', 'warranty'],
            'preview_image' => '/templates/classic-preview.jpg',
            'content' => <<<HTML
<div style="font-family: Georgia, serif; max-width: 900px; margin: 0 auto; padding: 20px; background: #fff;">
    <h1 style="text-align: center; color: #333; font-size: 36px; border-bottom: 3px solid #333; padding-bottom: 15px; margin-bottom: 30px;">
        {{product_name}}
    </h1>

    <div style="margin-bottom: 30px; line-height: 1.8; color: #555;">
        {{description}}
    </div>

    <h2 style="color: #333; font-size: 28px; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin: 30px 0 20px 0;">
        Características Principais
    </h2>
    {{features}}

    <h2 style="color: #333; font-size: 28px; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin: 30px 0 20px 0;">
        Especificações Técnicas
    </h2>
    {{specs}}

    <div style="border: 2px solid #4CAF50; padding: 20px; margin: 30px 0; background: #f1f8f4;">
        <h3 style="color: #2e7d32; margin-top: 0;">Garantia do Produto</h3>
        <p style="margin: 0;">{{warranty}}</p>
    </div>
</div>
HTML
        ];
    }

    private function getMinimalTemplate(): array
    {
        return [
            'id' => 'minimal',
            'name' => 'Minimalista',
            'description' => 'Design simples e direto ao ponto',
            'categories' => ['all'],
            'variables' => ['product_name', 'features', 'specs'],
            'preview_image' => '/templates/minimal-preview.jpg',
            'content' => <<<HTML
<div style="font-family: 'Helvetica Neue', Arial, sans-serif; max-width: 700px; margin: 0 auto; padding: 40px 20px;">
    <h1 style="font-size: 32px; font-weight: 300; color: #000; margin-bottom: 40px; letter-spacing: -0.5px;">
        {{product_name}}
    </h1>

    <div style="margin-bottom: 40px;">
        {{features}}
    </div>

    <div style="border-top: 1px solid #e0e0e0; padding-top: 40px;">
        {{specs}}
    </div>
</div>
HTML
        ];
    }

    private function getProfessionalTemplate(): array
    {
        return [
            'id' => 'professional',
            'name' => 'Profissional',
            'description' => 'Template corporativo com tabelas e organização clara',
            'categories' => ['all'],
            'variables' => ['product_name', 'brand', 'model', 'short_description', 'features', 'specs', 'applications', 'warranty', 'certifications'],
            'preview_image' => '/templates/professional-preview.jpg',
            'content' => <<<HTML
<div style="font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 1000px; margin: 0 auto; color: #333;">
    <div style="background: #2c3e50; color: white; padding: 40px; margin-bottom: 0;">
        <div style="max-width: 800px; margin: 0 auto;">
            <p style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; opacity: 0.8;">{{brand}}</p>
            <h1 style="margin: 0 0 15px 0; font-size: 42px; font-weight: 600;">{{product_name}}</h1>
            <p style="margin: 0; font-size: 16px; opacity: 0.9;">Modelo: {{model}}</p>
        </div>
    </div>

    <div style="max-width: 800px; margin: 0 auto; padding: 40px 20px;">
        <div style="background: #ecf0f1; padding: 25px; border-left: 5px solid #3498db; margin-bottom: 40px;">
            <p style="margin: 0; font-size: 16px; line-height: 1.7;">{{short_description}}</p>
        </div>

        <h2 style="font-size: 28px; color: #2c3e50; margin: 40px 0 25px 0; padding-bottom: 15px; border-bottom: 3px solid #3498db;">
            Características e Benefícios
        </h2>
        {{features}}

        <h2 style="font-size: 28px; color: #2c3e50; margin: 40px 0 25px 0; padding-bottom: 15px; border-bottom: 3px solid #3498db;">
            Especificações Técnicas Completas
        </h2>
        {{specs}}

        <h2 style="font-size: 28px; color: #2c3e50; margin: 40px 0 25px 0; padding-bottom: 15px; border-bottom: 3px solid #3498db;">
            Aplicações
        </h2>
        {{applications}}

        <div style="background: #d5f4e6; padding: 25px; border-radius: 5px; margin: 40px 0;">
            <h3 style="color: #27ae60; margin: 0 0 15px 0; font-size: 22px;">✓ Garantia e Certificações</h3>
            <p style="margin: 0 0 15px 0;"><strong>Garantia:</strong> {{warranty}}</p>
            <p style="margin: 0;"><strong>Certificações:</strong> {{certifications}}</p>
        </div>
    </div>
</div>
HTML
        ];
    }

    private function getEcommerceTemplate(): array
    {
        return [
            'id' => 'ecommerce',
            'name' => 'E-commerce',
            'description' => 'Otimizado para conversão em vendas online',
            'categories' => ['all'],
            'variables' => ['product_name', 'brand', 'key_benefit', 'features', 'specs', 'why_buy', 'includes', 'shipping_info', 'warranty'],
            'preview_image' => '/templates/ecommerce-preview.jpg',
            'content' => <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 850px; margin: 0 auto;">
    <div style="background: linear-gradient(to right, #ff6b6b, #ee5a6f); color: white; padding: 35px; text-align: center;">
        <h1 style="margin: 0 0 10px 0; font-size: 38px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">
            {{product_name}}
        </h1>
        <p style="margin: 0; font-size: 20px; font-weight: 300;">{{brand}}</p>
    </div>

    <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px; text-align: center;">
        <p style="margin: 0; font-size: 24px; font-weight: bold; color: #856404;">
            🎯 {{key_benefit}}
        </p>
    </div>

    <div style="padding: 30px 20px;">
        <h2 style="background: #007bff; color: white; padding: 15px; font-size: 26px; margin: 0 0 25px 0;">
            ⭐ Por Que Comprar?
        </h2>
        {{why_buy}}

        <h2 style="background: #28a745; color: white; padding: 15px; font-size: 26px; margin: 30px 0 25px 0;">
            ✨ Características que Você Vai Amar
        </h2>
        {{features}}

        <h2 style="background: #17a2b8; color: white; padding: 15px; font-size: 26px; margin: 30px 0 25px 0;">
            📊 Especificações Completas
        </h2>
        {{specs}}

        <div style="background: #f8f9fa; border: 3px solid #28a745; padding: 25px; margin: 30px 0;">
            <h3 style="color: #28a745; font-size: 24px; margin: 0 0 20px 0;">📦 O Que Você Recebe</h3>
            {{includes}}
        </div>

        <div style="background: #e7f3ff; border-left: 5px solid #007bff; padding: 20px; margin: 30px 0;">
            <h3 style="color: #007bff; margin: 0 0 15px 0;">🚚 Informações de Envio</h3>
            {{shipping_info}}
        </div>

        <div style="background: #d4edda; border-left: 5px solid #28a745; padding: 20px; margin: 30px 0;">
            <h3 style="color: #28a745; margin: 0 0 15px 0;">✅ Garantia</h3>
            {{warranty}}
        </div>

        <div style="background: #dc3545; color: white; padding: 30px; text-align: center; margin: 40px 0 0 0;">
            <h3 style="margin: 0 0 15px 0; font-size: 28px;">🔥 Não Perca Esta Oferta!</h3>
            <p style="margin: 0; font-size: 18px;">Compre agora e receba com frete grátis!</p>
        </div>
    </div>
</div>
HTML
        ];
    }

    // ===================================
    // BLOCOS REUTILIZÁVEIS
    // ===================================

    private function getSpecsTableBlock(): string
    {
        return <<<HTML
<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
    <tbody>
        <tr style="background: #f5f5f5;">
            <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; width: 30%;">Especificação</td>
            <td style="padding: 12px; border: 1px solid #ddd;">Valor</td>
        </tr>
        <!-- Adicionar linhas conforme necessário -->
    </tbody>
</table>
HTML;
    }

    private function getFeaturesListBlock(): string
    {
        return <<<HTML
<ul style="list-style: none; padding: 0; margin: 20px 0;">
    <li style="padding: 12px; margin: 8px 0; background: #f8f9fa; border-left: 4px solid #007bff;">
        ✓ <strong>Característica 1:</strong> Descrição da característica
    </li>
    <!-- Adicionar mais itens conforme necessário -->
</ul>
HTML;
    }

    private function getWarrantyBlock(): string
    {
        return <<<HTML
<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px;">
    <h3 style="color: #155724; margin-top: 0;">✓ Garantia do Fabricante</h3>
    <p style="margin: 0; color: #155724;">12 meses de garantia contra defeitos de fabricação.</p>
</div>
HTML;
    }

    private function getShippingInfoBlock(): string
    {
        return <<<HTML
<div style="background: #e7f3ff; padding: 20px; border-radius: 5px;">
    <h3 style="color: #004085; margin-top: 0;">🚚 Envio Rápido e Seguro</h3>
    <ul style="margin: 10px 0; padding-left: 20px; color: #004085;">
        <li>Frete Grátis para todo o Brasil</li>
        <li>Envio em até 24 horas após confirmação de pagamento</li>
        <li>Embalagem reforçada para máxima proteção</li>
        <li>Rastreamento incluído</li>
    </ul>
</div>
HTML;
    }

    private function getContactBlock(): string
    {
        return <<<HTML
<div style="background: #f8f9fa; border-top: 3px solid #007bff; padding: 25px; text-align: center;">
    <h3 style="margin: 0 0 15px 0; color: #333;">📞 Dúvidas? Entre em Contato!</h3>
    <p style="margin: 0; color: #666;">Estamos à disposição para te ajudar. Respondemos rapidamente!</p>
</div>
HTML;
    }

    // ===================================
    // MÉTODOS AUXILIARES
    // ===================================

    private function processArrayVariable(string $varName, array $value): string
    {
        switch ($varName) {
            case 'features':
                return $this->renderFeaturesList($value);
            
            case 'specs':
                return $this->renderSpecsTable($value);
            
            case 'includes':
                return $this->renderIncludesList($value);
            
            default:
                return implode(', ', $value);
        }
    }

    private function renderFeaturesList(array $features): string
    {
        $html = '<ul style="list-style: none; padding: 0; margin: 15px 0;">';
        
        foreach ($features as $feature) {
            if (is_array($feature)) {
                $title = $feature['title'] ?? '';
                $desc = $feature['description'] ?? '';
                $html .= '<li style="padding: 10px; margin: 8px 0; background: #f8f9fa; border-left: 4px solid #007bff;">';
                $html .= "✓ <strong>{$title}:</strong> {$desc}";
                $html .= '</li>';
            } else {
                $html .= '<li style="padding: 10px; margin: 8px 0; background: #f8f9fa; border-left: 4px solid #007bff;">';
                $html .= "✓ {$feature}";
                $html .= '</li>';
            }
        }
        
        $html .= '</ul>';
        return $html;
    }

    private function renderSpecsTable(array $specs): string
    {
        $html = '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;"><tbody>';
        
        $bgColor = true;
        foreach ($specs as $key => $value) {
            $bg = $bgColor ? '#f5f5f5' : '#ffffff';
            $html .= "<tr style=\"background: {$bg};\">";
            $html .= "<td style=\"padding: 12px; border: 1px solid #ddd; font-weight: bold; width: 35%;\">{$key}</td>";
            $html .= "<td style=\"padding: 12px; border: 1px solid #ddd;\">{$value}</td>";
            $html .= "</tr>";
            $bgColor = !$bgColor;
        }
        
        $html .= '</tbody></table>';
        return $html;
    }

    private function renderIncludesList(array $includes): string
    {
        $html = '<ul style="margin: 15px 0; padding-left: 20px;">';
        
        foreach ($includes as $item) {
            $html .= "<li style=\"margin: 8px 0; line-height: 1.6;\">{$item}</li>";
        }
        
        $html .= '</ul>';
        return $html;
    }

    private function extractVariables(string $content): array
    {
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Load custom templates from database
     */
    private function loadCustomTemplatesFromDb(): void
    {
        try {
            $this->ensureCustomTemplatesTable();
            $stmt = $this->db->query("SELECT * FROM listing_custom_templates ORDER BY created_at DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $this->templates[$row['template_id']] = [
                    'id'            => $row['template_id'],
                    'name'          => $row['name'],
                    'description'   => $row['description'] ?? '',
                    'categories'    => json_decode($row['categories'] ?? '["all"]', true) ?: ['all'],
                    'content'       => $row['content'] ?? '',
                    'variables'     => json_decode($row['variables'] ?? '[]', true) ?: [],
                    'preview_image' => $row['preview_image'],
                    'custom'        => true,
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist yet — silently continue with defaults
        }
    }

    /**
     * Ensure the custom templates table exists
     */
    private function ensureCustomTemplatesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS listing_custom_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                template_id VARCHAR(100) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                categories JSON,
                content LONGTEXT,
                variables JSON,
                preview_image VARCHAR(500),
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_template_id (template_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
