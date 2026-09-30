<?php

declare(strict_types=1);

use App\Modules\Router\Response;

/**
 * Vytvoří fluent validátor pro zadané pole dat.
 *
 * Použití:
 *   VALIDATOR($data)->required(['email','password'])->email('email')->validate();
 *
 * Volání `->validate()` při jakémkoli neúspěšném pravidle ukončí request s HTTP 422
 * a tělem `{ success: false, errors: { pole: zpráva } }`. Jednotlivá pravidla se
 * přeskakují, pokud už na poli vznikla chyba, aby se nehlásily duplicity.
 */
function VALIDATOR(array $data): object
{
    return new class($data) {
        private array $_errors = [];
        private array $_data;

        /**
         * Uloží data k validaci do validátoru.
         *
         * @param  array<string, mixed> $data Vstupní data, obvykle tělo požadavku.
         * @return void
         */
        public function __construct(array $data)
        {
            $this->_data = $data;
        }

        /**
         * Pole (nebo seznam polí) musí být vyplněné; prázdný řetězec se trimuje.
         *
         * @param  string|array $fields Název pole nebo seznam názvů polí.
         * @return static               Tentýž validátor pro další řetězení.
         */
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

        /**
         * Pole musí obsahovat platnou e-mailovou adresu (prázdné pole se přeskočí).
         *
         * @param  string $field Název kontrolovaného pole.
         * @return static        Tentýž validátor pro další řetězení.
         */
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

        /**
         * Délka řetězce v poli musí být alespoň $min znaků.
         *
         * @param  string $field Název kontrolovaného pole.
         * @param  int    $min   Minimální požadovaná délka.
         * @return static        Tentýž validátor pro další řetězení.
         */
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

        /**
         * Pole musí být číselné a volitelně nejméně $min.
         *
         * @param  string     $field Název kontrolovaného pole.
         * @param  float|null $min   Volitelná dolní mez.
         * @return static           Tentýž validátor pro další řetězení.
         */
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

        /**
         * Pole musí odpovídat regulárnímu výrazu (prázdné pole se přeskočí).
         *
         * @param  string $field   Název kontrolovaného pole.
         * @param  string $pattern  Regulární výraz (bez oddělovačů).
         * @param  string $message  Chybová zpráva, pokud regulárnímu výrazu neodpovídá.
         * @return static           Tentýž validátor pro další řetězení.
         */
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

        /**
         * Pole musí být číselné a nejvýše $max.
         *
         * @param  string $field Název kontrolovaného pole.
         * @param  float  $max   Maximální povolená hodnota.
         * @return static        Tentýž validátor pro další řetězení.
         */
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

        /**
         * Hodnota pole musí být jednou z povolených hodnot (prázdné pole se přeskočí).
         *
         * @param  string $field   Název kontrolovaného pole.
         * @param  array  $allowed Povolené hodnoty; porovnáváno striktně.
         * @return static           Tentýž validátor pro další řetězení.
         */
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

        /**
         * Pole musí obsahovat platnou URL (prázdné pole se přeskočí).
         *
         * @param  string $field Název kontrolovaného pole.
         * @return static        Tentýž validátor pro další řetězení.
         */
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

        /**
         * Pole musí obsahovat datum zpracovatelné funkcí strtotime (prázdné pole se přeskočí).
         *
         * @param  string $field Název kontrolovaného pole.
         * @return static        Tentýž validátor pro další řetězení.
         */
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

        /**
         * Pole musí obsahovat hodnotu podobnou booleanu: true/false/1/0/"1"/"0" (prázdné pole se přeskočí).
         *
         * @param  string $field Název kontrolovaného pole.
         * @return static        Tentýž validátor pro další řetězení.
         */
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

        /**
         * @return array<string, string> Mapa chybných polí na chybové zprávy.
         */
        public function errors(): array
        {
            return $this->_errors;
        }

        /**
         * @return bool true, pokud alespoň jedno pravilo selhalo.
         */
        public function fails(): bool
        {
            return !empty($this->_errors);
        }

        /**
         * Ukončí request s HTTP 422, pokud selhalo alespoň jedno pravidlo.
         *
         * @return void           Vedlejší efekt: při chybách ukončí request přes Response::validationError().
         */
        public function validate(): void
        {
            if (!empty($this->_errors)) {
                Response::validationError($this->_errors);
            }
        }
    };
}
