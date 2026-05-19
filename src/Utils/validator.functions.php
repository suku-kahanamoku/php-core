<?php

declare(strict_types=1);

use App\Modules\Router\Response;

/**
 * Creates a fluent validation builder for the given data array.
 *
 * Usage:
 *   VALIDATOR($data)->required(['email','password'])->email('email')->validate();
 *
 * Halts with HTTP 422 on ->validate() when any rule fails.
 */
function VALIDATOR(array $data): object
{
    return new class($data) {
        private array $_errors = [];
        private array $_data;

        public function __construct(array $data)
        {
            $this->_data = $data;
        }

        /** Field(s) must be non-empty (after trim). */
        public function required(string|array $fields): static
        {
            foreach ((array) $fields as $field) {
                $val = $this->_data[$field] ?? null;
                $empty = $val === null
                    || $val === ''
                    || (is_string($val) && trim($val) === '')
                    || (is_array($val) && empty($val));
                if ($empty) {
                    $this->_errors[$field] = 'Required';
                }
            }
            return $this;
        }

        /** Field must be a valid e-mail address (skipped when empty). */
        public function email(string $field): static
        {
            if (isset($this->_errors[$field])) {
                return $this;
            }
            $val = (string) ($this->_data[$field] ?? '');
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $this->_errors[$field] = 'Invalid email';
            }
            return $this;
        }

        /** Field string length must be >= $min. */
        public function minLength(string $field, int $min): static
        {
            if (isset($this->_errors[$field])) {
                return $this;
            }
            if (strlen((string) ($this->_data[$field] ?? '')) < $min) {
                $this->_errors[$field] = "Minimum {$min} characters";
            }
            return $this;
        }

        /** Field must be numeric and optionally >= $min. */
        public function numeric(string $field, ?float $min = null): static
        {
            if (isset($this->_errors[$field])) {
                return $this;
            }
            $val = $this->_data[$field] ?? null;
            if ($val === null || !is_numeric($val)) {
                $this->_errors[$field] = 'Must be a number';
            } elseif ($min !== null && (float) $val < $min) {
                $this->_errors[$field] = "Must be >= {$min}";
            }
            return $this;
        }

        /** Field must match regex $pattern (skipped when empty). */
        public function pattern(
            string $field,
            string $pattern,
            string $message
        ): static {
            if (isset($this->_errors[$field])) {
                return $this;
            }
            $val = (string) ($this->_data[$field] ?? '');
            if ($val !== '' && !preg_match($pattern, $val)) {
                $this->_errors[$field] = $message;
            }
            return $this;
        }

        /** Field must be numeric and <= $max. */
        public function max(string $field, float $max): static
        {
            if (isset($this->_errors[$field])) {
                return $this;
            }
            $val = $this->_data[$field] ?? null;
            if ($val !== null && is_numeric($val) && (float) $val > $max) {
                $this->_errors[$field] = "Must be <= {$max}";
            }
            return $this;
        }

        /** Field value must be one of the allowed values (skipped when empty). */
        public function in(string $field, array $allowed): static
        {
            if (isset($this->_errors[$field])) {
                return $this;
            }
            $val = $this->_data[$field] ?? null;
            if ($val !== null && $val !== '' && !in_array($val, $allowed, true)) {
                $this->_errors[$field] = 'Invalid value';
            }
            return $this;
        }

        /** Field must be a valid URL (skipped when empty). */
        public function url(string $field): static
        {
            if (isset($this->_errors[$field])) {
                return $this;
            }
            $val = (string) ($this->_data[$field] ?? '');
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                $this->_errors[$field] = 'Invalid URL';
            }
            return $this;
        }

        /** Field must be a valid date parseable by strtotime (skipped when empty). */
        public function date(string $field): static
        {
            if (isset($this->_errors[$field])) {
                return $this;
            }
            $val = (string) ($this->_data[$field] ?? '');
            if ($val !== '' && strtotime($val) === false) {
                $this->_errors[$field] = 'Invalid date';
            }
            return $this;
        }

        /** Field must be a boolean-like value: true/false/1/0/"1"/"0" (skipped when empty). */
        public function boolean(string $field): static
        {
            if (isset($this->_errors[$field])) {
                return $this;
            }
            $val = $this->_data[$field] ?? null;
            if ($val !== null && $val !== '' && !in_array($val, [true, false, 1, 0, '1', '0'], true)) {
                $this->_errors[$field] = 'Must be a boolean';
            }
            return $this;
        }

        /** Get all validation errors. */
        public function errors(): array
        {
            return $this->_errors;
        }

        /** Check if any validation rules failed. */
        public function fails(): bool
        {
            return !empty($this->_errors);
        }

        /** Halt with HTTP 422 if any validation rules failed. */
        public function validate(): void
        {
            if (!empty($this->_errors)) {
                Response::validationError($this->_errors);
            }
        }
    };
}
