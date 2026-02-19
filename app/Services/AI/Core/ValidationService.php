<?php

namespace App\Services\AI\Core;

use App\Database;
use PDO;

/**
 * AI Validation Service
 * 
 * Production-grade validation for all AI inputs and outputs.
 * Ensures data integrity, security, and compliance.
 * 
 * @author AI Development Team
 * @version 1.0.0
 */
class ValidationService
{
    private PDO $db;
    
    // Mercado Livre limits
    private const ML_LIMITS = [
        'title' => [
            'min_length' => 10,
            'max_length' => 60,
            'forbidden_chars' => ['!', '@', '#', '$', '%', '&', '*'],
            'forbidden_words' => ['grátis', 'desconto', 'promoção', 'oferta'],
        ],
        'description' => [
            'min_length' => 100,
            'max_length' => 50000,
            'forbidden_patterns' => [
                '/\b(whatsapp|telegram|instagram|facebook)\b/i',
                '/\d{2}[\s\-]?\d{4,5}[\s\-]?\d{4}/', // Phone numbers
                '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', // Emails
            ],
        ],
        'attributes' => [
            'max_count' => 100,
            'max_value_length' => 255,
        ],
        'images' => [
            'min_count' => 1,
            'max_count' => 12,
            'min_resolution' => 500,
            'max_file_size' => 10485760, // 10MB
            'allowed_formats' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
    ];
    
    // XSS and injection patterns
    private const SECURITY_PATTERNS = [
        'xss' => [
            '/<script\b[^>]*>/i',
            '/<\/script>/i',
            '/on\w+\s*=/i',
            '/javascript:/i',
        ],
        'sql' => [
            '/\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|ALTER)\b/i',
            '/[\'\"]\s*OR\s*[\'\"]/i',
            '/--\s*$/m',
        ],
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Validate title for Mercado Livre
     * 
     * @param string $title
     * @return array Validation result
     */
    public function validateTitle(string $title): array
    {
        $errors = [];
        $warnings = [];
        $sanitized = $this->sanitizeText($title);
        
        $limits = self::ML_LIMITS['title'];
        $length = mb_strlen($sanitized);
        
        // Length validation
        if ($length < $limits['min_length']) {
            $errors[] = [
                'code' => 'TITLE_TOO_SHORT',
                'message' => "Título muito curto. Mínimo: {$limits['min_length']} caracteres.",
                'current' => $length,
                'required' => $limits['min_length']
            ];
        }
        
        if ($length > $limits['max_length']) {
            $errors[] = [
                'code' => 'TITLE_TOO_LONG',
                'message' => "Título muito longo. Máximo: {$limits['max_length']} caracteres.",
                'current' => $length,
                'max' => $limits['max_length']
            ];
        }
        
        // Forbidden characters
        foreach ($limits['forbidden_chars'] as $char) {
            if (mb_strpos($sanitized, $char) !== false) {
                $warnings[] = [
                    'code' => 'FORBIDDEN_CHAR',
                    'message' => "Caractere não recomendado: {$char}",
                    'char' => $char
                ];
            }
        }
        
        // Forbidden words
        foreach ($limits['forbidden_words'] as $word) {
            if (mb_stripos($sanitized, $word) !== false) {
                $errors[] = [
                    'code' => 'FORBIDDEN_WORD',
                    'message' => "Palavra proibida pelo ML: {$word}",
                    'word' => $word
                ];
            }
        }
        
        // Security check
        $securityIssues = $this->checkSecurity($sanitized);
        if (!empty($securityIssues)) {
            $errors = array_merge($errors, $securityIssues);
        }
        
        return [
            'valid' => empty($errors),
            'original' => $title,
            'sanitized' => $sanitized,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Validate description for Mercado Livre
     * 
     * @param string $description
     * @return array Validation result
     */
    public function validateDescription(string $description): array
    {
        $errors = [];
        $warnings = [];
        $sanitized = $this->sanitizeText($description, true);
        
        $limits = self::ML_LIMITS['description'];
        $length = mb_strlen($sanitized);
        
        // Length validation
        if ($length < $limits['min_length']) {
            $errors[] = [
                'code' => 'DESC_TOO_SHORT',
                'message' => "Descrição muito curta. Mínimo: {$limits['min_length']} caracteres.",
                'current' => $length,
                'required' => $limits['min_length']
            ];
        }
        
        if ($length > $limits['max_length']) {
            $errors[] = [
                'code' => 'DESC_TOO_LONG',
                'message' => "Descrição muito longa. Máximo: {$limits['max_length']} caracteres.",
                'current' => $length,
                'max' => $limits['max_length']
            ];
        }
        
        // Forbidden patterns (contact info, competitors)
        foreach ($limits['forbidden_patterns'] as $pattern) {
            if (preg_match($pattern, $sanitized)) {
                $errors[] = [
                    'code' => 'FORBIDDEN_PATTERN',
                    'message' => 'Descrição contém informações de contato ou conteúdo proibido.',
                    'pattern' => $pattern
                ];
            }
        }
        
        // Security check
        $securityIssues = $this->checkSecurity($sanitized);
        if (!empty($securityIssues)) {
            $errors = array_merge($errors, $securityIssues);
        }
        
        return [
            'valid' => empty($errors),
            'original' => $description,
            'sanitized' => $sanitized,
            'length' => $length,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Validate attributes
     * 
     * @param array $attributes
     * @return array
     */
    public function validateAttributes(array $attributes): array
    {
        $errors = [];
        $warnings = [];
        $sanitized = [];
        
        $limits = self::ML_LIMITS['attributes'];
        
        if (count($attributes) > $limits['max_count']) {
            $errors[] = [
                'code' => 'TOO_MANY_ATTRS',
                'message' => "Máximo de {$limits['max_count']} atributos permitidos.",
                'current' => count($attributes)
            ];
        }
        
        foreach ($attributes as $index => $attr) {
            $id = $attr['id'] ?? $attr['name'] ?? "attr_{$index}";
            $value = $attr['value_name'] ?? $attr['value'] ?? '';
            
            // Validate value length
            if (mb_strlen($value) > $limits['max_value_length']) {
                $warnings[] = [
                    'code' => 'ATTR_VALUE_TOO_LONG',
                    'message' => "Valor do atributo {$id} muito longo.",
                    'attribute' => $id
                ];
                $value = mb_substr($value, 0, $limits['max_value_length']);
            }
            
            $sanitized[] = [
                'id' => $this->sanitizeText($id),
                'value_name' => $this->sanitizeText($value)
            ];
        }
        
        return [
            'valid' => empty($errors),
            'original' => $attributes,
            'sanitized' => $sanitized,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Validate image URL or file
     * 
     * @param string $imageUrl
     * @return array
     */
    public function validateImage(string $imageUrl): array
    {
        $errors = [];
        $warnings = [];
        
        $limits = self::ML_LIMITS['images'];
        
        // Validate URL format
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $errors[] = [
                'code' => 'INVALID_URL',
                'message' => 'URL da imagem inválida.',
                'url' => $imageUrl
            ];
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }
        
        // Check extension
        $ext = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, $limits['allowed_formats'])) {
            $errors[] = [
                'code' => 'INVALID_FORMAT',
                'message' => "Formato não permitido: {$ext}. Permitidos: " . implode(', ', $limits['allowed_formats']),
                'format' => $ext
            ];
        }
        
        // Try to get image info (basic check)
        try {
            $headers = @get_headers($imageUrl, 1);
            if ($headers && isset($headers['Content-Length'])) {
                $size = intval($headers['Content-Length']);
                if ($size > $limits['max_file_size']) {
                    $errors[] = [
                        'code' => 'FILE_TOO_LARGE',
                        'message' => 'Arquivo muito grande. Máximo: 10MB.',
                        'size' => $size
                    ];
                }
            }
        } catch (\Exception $e) {
            $warnings[] = [
                'code' => 'SIZE_CHECK_FAILED',
                'message' => 'Não foi possível verificar o tamanho da imagem.'
            ];
        }
        
        return [
            'valid' => empty($errors),
            'url' => $imageUrl,
            'format' => $ext,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Validate AI API key
     * 
     * @param string $provider
     * @param string $key
     * @return array
     */
    public function validateApiKey(string $provider, string $key): array
    {
        $patterns = [
            'openai' => '/^sk-[a-zA-Z0-9]{32,}$/',
            'anthropic' => '/^sk-ant-[a-zA-Z0-9\-]{32,}$/',
            'gemini' => '/^AI[a-zA-Z0-9_\-]{30,}$/',
        ];
        
        $valid = isset($patterns[$provider]) && preg_match($patterns[$provider], $key);
        
        return [
            'valid' => $valid,
            'provider' => $provider,
            'message' => $valid ? 'API key válida.' : 'Formato de API key inválido.'
        ];
    }
    
    /**
     * Sanitize text input
     * 
     * @param string $text
     * @param bool $allowLineBreaks
     * @return string
     */
    public function sanitizeText(string $text, bool $allowLineBreaks = false): string
    {
        // Remove null bytes
        $text = str_replace("\0", '', $text);
        
        // Normalize whitespace
        if (!$allowLineBreaks) {
            $text = preg_replace('/\s+/', ' ', $text);
        } else {
            $text = preg_replace('/[^\S\n]+/', ' ', $text);
            $text = preg_replace('/\n{3,}/', "\n\n", $text);
        }
        
        // Trim
        $text = trim($text);
        
        // Remove control characters except newline and tab
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        return $text;
    }
    
    /**
     * Check for security issues
     * 
     * @param string $text
     * @return array
     */
    private function checkSecurity(string $text): array
    {
        $errors = [];
        
        foreach (self::SECURITY_PATTERNS as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    $errors[] = [
                        'code' => 'SECURITY_' . strtoupper($type),
                        'message' => "Potencial {$type} detectado no conteúdo.",
                        'type' => $type
                    ];
                    break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate complete listing before optimization
     * 
     * @param array $listing
     * @return array
     */
    public function validateListing(array $listing): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        // Validate title
        if (isset($listing['title'])) {
            $titleResult = $this->validateTitle($listing['title']);
            if (!$titleResult['valid']) {
                $results['valid'] = false;
                $results['errors']['title'] = $titleResult['errors'];
            }
            $results['warnings']['title'] = $titleResult['warnings'] ?? [];
        }
        
        // Validate description
        if (isset($listing['description'])) {
            $descResult = $this->validateDescription($listing['description']);
            if (!$descResult['valid']) {
                $results['valid'] = false;
                $results['errors']['description'] = $descResult['errors'];
            }
            $results['warnings']['description'] = $descResult['warnings'] ?? [];
        }
        
        // Validate attributes
        if (isset($listing['attributes']) && is_array($listing['attributes'])) {
            $attrResult = $this->validateAttributes($listing['attributes']);
            if (!$attrResult['valid']) {
                $results['valid'] = false;
                $results['errors']['attributes'] = $attrResult['errors'];
            }
            $results['warnings']['attributes'] = $attrResult['warnings'] ?? [];
        }
        
        return $results;
    }
}
