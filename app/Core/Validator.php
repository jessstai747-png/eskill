<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validator — validação de inputs HTTP com regras encadeadas via pipe.
 *
 * Uso:
 *   $v = Validator::make($data, [
 *       'email'    => 'required|email',
 *       'age'      => 'required|integer|min:18|max:120',
 *       'name'     => 'required|string|minLength:2|maxLength:100',
 *       'role'     => 'required|in:admin,user,viewer',
 *       'website'  => 'nullable|url',
 *       'discount' => 'nullable|numeric|min:0|max:100',
 *   ]);
 *   if ($v->fails()) { ... $v->errors() ... }
 *   $validated = $v->validated(); // somente campos definidos nas regras
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    /** @var array<string, bool> */
    private array $nullable = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        $this->errors = [];
        $this->nullable = [];
        $this->validate();
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Returns only the fields listed in the rules array (safe subset).
     */
    public function validated(): array
    {
        $result = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $result[$field] = $this->data[$field];
            }
        }
        return $result;
    }

    // ─── Core ────────────────────────────────────────────────────────────────

    private function validate(): void
    {
        foreach ($this->rules as $field => $rules) {
            $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;

            // First pass: detect nullable so we can skip other rules when empty
            $isNullable = in_array('nullable', $rulesArray, true);
            if ($isNullable) {
                $this->nullable[$field] = true;
            }

            foreach ($rulesArray as $rule) {
                // Parse rule:params (e.g., min:5 or in:a,b,c)
                $params = [];
                if (str_contains((string) $rule, ':')) {
                    [$rule, $paramStr] = explode(':', (string) $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $rule = (string) $rule;

                // nullable meta-rule — no validation callback needed
                if ($rule === 'nullable') {
                    continue;
                }

                // Skip all rules for nullable fields when value is absent/null/empty
                if ($isNullable && !$this->hasValue($field)) {
                    break;
                }

                $method = 'validate' . ucfirst($rule);
                if (method_exists($this, $method)) {
                    if (!$this->$method($field, $params)) {
                        $this->addError($field, $rule, $params);
                    }
                }
            }
        }
    }

    private function hasValue(string $field): bool
    {
        $v = $this->data[$field] ?? null;
        return $v !== null && $v !== '';
    }

    // ─── Rule implementations ─────────────────────────────────────────────────

    /** @param array<string> $params */
    private function validateRequired(string $field, array $params = []): bool
    {
        return $this->hasValue($field);
    }

    /** @param array<string> $params */
    private function validateString(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        return is_string($this->data[$field]);
    }

    /** @param array<string> $params */
    private function validateNumeric(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        return is_numeric($this->data[$field]);
    }

    /** @param array<string> $params */
    private function validateInteger(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        $v = $this->data[$field];
        return is_int($v) || (is_string($v) && ctype_digit(ltrim((string) $v, '-')));
    }

    /** @param array<string> $params */
    private function validateBoolean(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        return in_array($this->data[$field], [true, false, 1, 0, '1', '0', 'true', 'false'], true);
    }

    /** @param array<string> $params */
    private function validateArray(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        return is_array($this->data[$field]);
    }

    /** @param array<string> $params */
    private function validateEmail(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        return filter_var($this->data[$field], FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @param array<string> $params */
    private function validateUrl(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        return filter_var($this->data[$field], FILTER_VALIDATE_URL) !== false;
    }

    /**
     * min:N — for numbers: value >= N; for strings: strlen >= N
     * @param array<string> $params
     */
    private function validateMin(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        $limit = (float) ($params[0] ?? 0);
        $v = $this->data[$field];
        if (is_numeric($v)) {
            return (float) $v >= $limit;
        }
        return mb_strlen((string) $v) >= (int) $limit;
    }

    /**
     * max:N — for numbers: value <= N; for strings: strlen <= N
     * @param array<string> $params
     */
    private function validateMax(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        $limit = (float) ($params[0] ?? PHP_INT_MAX);
        $v = $this->data[$field];
        if (is_numeric($v)) {
            return (float) $v <= $limit;
        }
        return mb_strlen((string) $v) <= (int) $limit;
    }

    /** @param array<string> $params */
    private function validateMinLength(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        return mb_strlen((string) $this->data[$field]) >= (int) ($params[0] ?? 0);
    }

    /** @param array<string> $params */
    private function validateMaxLength(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        return mb_strlen((string) $this->data[$field]) <= (int) ($params[0] ?? PHP_INT_MAX);
    }

    /**
     * in:a,b,c — value must be one of the listed options
     * @param array<string> $params
     */
    private function validateIn(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        return in_array((string) $this->data[$field], $params, true);
    }

    /**
     * regex:pattern — value must match the pattern (without delimiters)
     * @param array<string> $params
     */
    private function validateRegex(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        $pattern = $params[0] ?? '';
        if ($pattern === '') {
            return true;
        }
        return preg_match('/' . $pattern . '/', (string) $this->data[$field]) === 1;
    }

    /**
     * date — value must be a parseable date string
     * @param array<string> $params
     */
    private function validateDate(string $field, array $params = []): bool
    {
        if (!$this->hasValue($field)) {
            return true;
        }
        $v = $this->data[$field];
        if ($v instanceof \DateTimeInterface) {
            return true;
        }
        return strtotime((string) $v) !== false;
    }

    // ─── Error messages ───────────────────────────────────────────────────────

    /** @param array<string> $params */
    private function addError(string $field, string $rule, array $params): void
    {
        $p0 = $params[0] ?? '';
        $list = implode(', ', $params);

        $messages = [
            'required'  => "O campo '{$field}' é obrigatório.",
            'string'    => "O campo '{$field}' deve ser uma string.",
            'numeric'   => "O campo '{$field}' deve ser numérico.",
            'integer'   => "O campo '{$field}' deve ser um número inteiro.",
            'boolean'   => "O campo '{$field}' deve ser verdadeiro ou falso.",
            'array'     => "O campo '{$field}' deve ser um array.",
            'email'     => "O campo '{$field}' deve ser um e-mail válido.",
            'url'       => "O campo '{$field}' deve ser uma URL válida.",
            'min'       => "O campo '{$field}' deve ser no mínimo {$p0}.",
            'max'       => "O campo '{$field}' deve ser no máximo {$p0}.",
            'minLength' => "O campo '{$field}' deve ter pelo menos {$p0} caracteres.",
            'maxLength' => "O campo '{$field}' deve ter no máximo {$p0} caracteres.",
            'in'        => "O campo '{$field}' deve ser um dos valores: {$list}.",
            'regex'     => "O campo '{$field}' possui formato inválido.",
            'date'      => "O campo '{$field}' deve ser uma data válida.",
        ];

        $this->errors[$field][] = $messages[$rule] ?? "O campo '{$field}' falhou na validação '{$rule}'.";
    }
}
