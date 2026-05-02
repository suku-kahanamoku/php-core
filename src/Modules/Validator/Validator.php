<?php

declare(strict_types=1);

namespace App\Modules\Validator;

use App\Modules\Router\Response;

class Validator
{
    private array $errors = [];
    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    /** Field(s) must be non-empty (after trim). */
    public function required(string|array $fields): self
    {
        foreach ((array) $fields as $field) {
            $val = isset($this->data[$field]) ? trim((string) $this->data[$field]) : '';
            if ($val === '') {
                $this->errors[$field] = 'Required';
            }
        }
        return $this;
    }

    /** Field must be a valid e-mail address (skipped when empty). */
    public function email(string $field): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $val = (string) ($this->data[$field] ?? '');
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Invalid email';
        }
        return $this;
    }

    /** Field string length must be >= $min. */
    public function minLength(string $field, int $min): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        if (strlen((string) ($this->data[$field] ?? '')) < $min) {
            $this->errors[$field] = "Minimum {$min} characters";
        }
        return $this;
    }

    /** Field must be numeric and optionally >= $min. */
    public function numeric(string $field, ?float $min = null): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $val = $this->data[$field] ?? null;
        if ($val === null || !is_numeric($val)) {
            $this->errors[$field] = 'Must be a number';
        } elseif ($min !== null && (float) $val < $min) {
            $this->errors[$field] = "Must be >= {$min}";
        }
        return $this;
    }

    /** Field must match regex $pattern (skipped when empty). */
    public function pattern(string $field, string $pattern, string $message): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }
        $val = (string) ($this->data[$field] ?? '');
        if ($val !== '' && !preg_match($pattern, $val)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /** Halt with HTTP 422 if any validation rules failed. */
    public function validate(): void
    {
        if (!empty($this->errors)) {
            Response::validationError($this->errors);
        }
    }
}
