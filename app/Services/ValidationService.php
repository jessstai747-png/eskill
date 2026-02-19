<?php

namespace App\Services;

/**
 * ValidationService - Validação centralizada de dados
 * 
 * Fornece validação de campos com regras configuráveis,
 * mensagens personalizadas e suporte a validações customizadas.
 */
class ValidationService
{
    /** @var array Dados a serem validados */
    private array $data = [];

    /** @var array Regras de validação */
    private array $rules = [];

    /** @var array Mensagens de erro personalizadas */
    private array $messages = [];

    /** @var array Erros encontrados */
    private array $errors = [];

    /** @var array Validadores customizados registrados */
    private static array $customValidators = [];

    /**
     * @param array $data Dados a validar
     * @param array $rules Regras de validação
     * @param array $messages Mensagens personalizadas
     */
    public function __construct(array $data = [], array $rules = [], array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    /**
     * Executa validação
     */
    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;

            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Aplica uma regra a um campo
     */
    private function applyRule(string $field, string $rule): void
    {
        // Separar regra de parâmetros (ex: "min:3" => ["min", "3"])
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        $value = $this->getValue($field);

        // Pular outras validações se campo está vazio e não é required
        if ($ruleName !== 'required' && $this->isEmpty($value)) {
            return;
        }

        // Verificar se é validador customizado
        if (isset(self::$customValidators[$ruleName])) {
            if (!self::$customValidators[$ruleName]($value, $params, $this->data)) {
                $this->addError($field, $ruleName, $params);
            }
            return;
        }

        // Validadores built-in
        $method = 'validate' . ucfirst($ruleName);
        if (method_exists($this, $method)) {
            if (!$this->$method($value, $params, $field)) {
                $this->addError($field, $ruleName, $params);
            }
        }
    }

    // =========================================
    // VALIDADORES BUILT-IN
    // =========================================

    protected function validateRequired(mixed $value): bool
    {
        return !$this->isEmpty($value);
    }

    protected function validateEmail(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateUrl(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateNumeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    protected function validateInteger(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateMin(mixed $value, array $params): bool
    {
        $min = (int)($params[0] ?? 0);

        if (is_numeric($value)) {
            return (float)$value >= $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    protected function validateMax(mixed $value, array $params): bool
    {
        $max = (int)($params[0] ?? PHP_INT_MAX);

        if (is_numeric($value)) {
            return (float)$value <= $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    protected function validateBetween(mixed $value, array $params): bool
    {
        $min = (int)($params[0] ?? 0);
        $max = (int)($params[1] ?? PHP_INT_MAX);

        return $this->validateMin($value, [$min]) && $this->validateMax($value, [$max]);
    }

    protected function validateIn(mixed $value, array $params): bool
    {
        return in_array($value, $params, true);
    }

    protected function validateNotIn(mixed $value, array $params): bool
    {
        return !in_array($value, $params, true);
    }

    protected function validateRegex(mixed $value, array $params): bool
    {
        $pattern = $params[0] ?? '';
        return preg_match($pattern, (string)$value) === 1;
    }

    protected function validateAlpha(mixed $value): bool
    {
        return preg_match('/^[\pL]+$/u', (string)$value) === 1;
    }

    protected function validateAlphaNum(mixed $value): bool
    {
        return preg_match('/^[\pL\pN]+$/u', (string)$value) === 1;
    }

    protected function validateAlphaDash(mixed $value): bool
    {
        return preg_match('/^[\pL\pN_-]+$/u', (string)$value) === 1;
    }

    protected function validateDate(mixed $value): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $timestamp = strtotime((string)$value);
        return $timestamp !== false;
    }

    protected function validateDateFormat(mixed $value, array $params): bool
    {
        $format = $params[0] ?? 'Y-m-d';
        $date = \DateTime::createFromFormat($format, (string)$value);
        return $date && $date->format($format) === (string)$value;
    }

    protected function validateBefore(mixed $value, array $params): bool
    {
        $before = $params[0] ?? 'now';
        return strtotime((string)$value) < strtotime($before);
    }

    protected function validateAfter(mixed $value, array $params): bool
    {
        $after = $params[0] ?? 'now';
        return strtotime((string)$value) > strtotime($after);
    }

    protected function validateConfirmed(mixed $value, array $params, string $field): bool
    {
        $confirmField = $field . '_confirmation';
        return $value === $this->getValue($confirmField);
    }

    protected function validateSame(mixed $value, array $params): bool
    {
        $otherField = $params[0] ?? '';
        return $value === $this->getValue($otherField);
    }

    protected function validateDifferent(mixed $value, array $params): bool
    {
        $otherField = $params[0] ?? '';
        return $value !== $this->getValue($otherField);
    }

    protected function validateArray(mixed $value): bool
    {
        return is_array($value);
    }

    protected function validateBoolean(mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    protected function validateJson(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function validatePhone(mixed $value): bool
    {
        // Remove caracteres não numéricos
        $digits = preg_replace('/[^0-9]/', '', (string)$value);
        // Telefone brasileiro: 10-11 dígitos
        return strlen($digits) >= 10 && strlen($digits) <= 11;
    }

    protected function validateCpf(mixed $value): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', (string)$value);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // CPFs inválidos conhecidos
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Validação dos dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$t] != $d) {
                return false;
            }
        }

        return true;
    }

    protected function validateCnpj(mixed $value): bool
    {
        $cnpj = preg_replace('/[^0-9]/', '', (string)$value);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        // CNPJs inválidos conhecidos
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // Validação dos dígitos verificadores
        $multiplicadores1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $multiplicadores2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += (int)$cnpj[$i] * $multiplicadores1[$i];
        }
        $resto = $soma % 11;
        $digito1 = $resto < 2 ? 0 : 11 - $resto;

        if ((int)$cnpj[12] !== $digito1) {
            return false;
        }

        $soma = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma += (int)$cnpj[$i] * $multiplicadores2[$i];
        }
        $resto = $soma % 11;
        $digito2 = $resto < 2 ? 0 : 11 - $resto;

        return (int)$cnpj[13] === $digito2;
    }

    protected function validateCep(mixed $value): bool
    {
        $cep = preg_replace('/[^0-9]/', '', (string)$value);
        return strlen($cep) === 8;
    }

    // =========================================
    // MÉTODOS DE SUPORTE
    // =========================================

    /**
     * Obtém valor de um campo (suporta notação dot)
     */
    private function getValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Verifica se valor está vazio
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] ||
            (is_string($value) && trim($value) === '');
    }

    /**
     * Adiciona erro para um campo
     */
    private function addError(string $field, string $rule, array $params = []): void
    {
        // Verificar mensagem personalizada
        $messageKey = "{$field}.{$rule}";
        if (isset($this->messages[$messageKey])) {
            $message = $this->messages[$messageKey];
        } elseif (isset($this->messages[$field])) {
            $message = $this->messages[$field];
        } else {
            $message = $this->getDefaultMessage($field, $rule, $params);
        }

        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Mensagens padrão de erro
     */
    private function getDefaultMessage(string $field, string $rule, array $params): string
    {
        $param0 = $params[0] ?? '';
        $param1 = $params[1] ?? '';
        
        $messages = [
            'required' => "O campo {$field} é obrigatório.",
            'email' => "O campo {$field} deve ser um e-mail válido.",
            'url' => "O campo {$field} deve ser uma URL válida.",
            'numeric' => "O campo {$field} deve ser numérico.",
            'integer' => "O campo {$field} deve ser um número inteiro.",
            'min' => "O campo {$field} deve ter no mínimo {$param0} caracteres.",
            'max' => "O campo {$field} deve ter no máximo {$param0} caracteres.",
            'between' => "O campo {$field} deve estar entre {$param0} e {$param1}.",
            'in' => "O campo {$field} deve ser um dos valores permitidos.",
            'date' => "O campo {$field} deve ser uma data válida.",
            'confirmed' => "A confirmação do campo {$field} não confere.",
            'same' => "Os campos {$field} e {$param0} devem ser iguais.",
            'different' => "Os campos {$field} e {$param0} devem ser diferentes.",
            'array' => "O campo {$field} deve ser um array.",
            'boolean' => "O campo {$field} deve ser verdadeiro ou falso.",
            'json' => "O campo {$field} deve ser um JSON válido.",
            'phone' => "O campo {$field} deve ser um telefone válido.",
            'cpf' => "O campo {$field} deve ser um CPF válido.",
            'cnpj' => "O campo {$field} deve ser um CNPJ válido.",
            'cep' => "O campo {$field} deve ser um CEP válido.",
            'alpha' => "O campo {$field} deve conter apenas letras.",
            'alphaNum' => "O campo {$field} deve conter apenas letras e números.",
            'alphaDash' => "O campo {$field} deve conter apenas letras, números, traços e underscores.",
        ];

        return $messages[$rule] ?? "O campo {$field} é inválido.";
    }

    // =========================================
    // GETTERS E MÉTODOS ESTÁTICOS
    // =========================================

    /**
     * Retorna erros encontrados
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Retorna primeiro erro de cada campo
     */
    public function firstErrors(): array
    {
        $first = [];
        foreach ($this->errors as $field => $messages) {
            $first[$field] = $messages[0] ?? null;
        }
        return $first;
    }

    /**
     * Retorna dados validados (apenas campos com regras)
     */
    public function validated(): array
    {
        $validated = [];

        foreach (array_keys($this->rules) as $field) {
            $value = $this->getValue($field);
            if ($value !== null) {
                $validated[$field] = $value;
            }
        }

        return $validated;
    }

    /**
     * Validação rápida estática
     */
    public static function make(array $data, array $rules, array $messages = []): self
    {
        $validator = new self($data, $rules, $messages);
        $validator->validate();
        return $validator;
    }

    /**
     * Registra validador customizado
     */
    public static function extend(string $name, callable $callback): void
    {
        self::$customValidators[$name] = $callback;
    }

    /**
     * Verifica se validação passou
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Verifica se validação falhou
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }
}
