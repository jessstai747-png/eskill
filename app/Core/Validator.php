<?php

declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

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
        $this->validate();
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $rules) {
            $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;

            foreach ($rulesArray as $rule) {
                // Parse rule params (e.g., min:5)
                $params = [];
                if (strpos($rule, ':') !== false) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
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

    private function validateRequired(string $field): bool
    {
        return isset($this->data[$field]) && $this->data[$field] !== '' && $this->data[$field] !== null;
    }

    private function validateNumeric(string $field): bool
    {
        if (!isset($this->data[$field])) return true; // Skip if optional
        return is_numeric($this->data[$field]);
    }

    private function validateArray(string $field): bool
    {
        if (!isset($this->data[$field])) return true;
        return is_array($this->data[$field]);
    }

    private function validateEmail(string $field): bool
    {
        if (!isset($this->data[$field])) return true;
        return filter_var($this->data[$field], FILTER_VALIDATE_EMAIL) !== false;
    }

    private function addError(string $field, string $rule, array $params): void
    {
        $messages = [
            'required' => "The field '{$field}' is required.",
            'numeric' => "The field '{$field}' must be numeric.",
            'array' => "The field '{$field}' must be an array.",
            'email' => "The field '{$field}' must be a valid email.",
        ];

        $this->errors[$field][] = $messages[$rule] ?? "Field '{$field}' failed validation '{$rule}'.";
    }
}
