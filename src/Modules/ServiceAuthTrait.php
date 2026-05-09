<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Auth\Auth;
use App\Modules\Router\Response;

/**
 * Trait pro opakujici se autorizacni vzory v Service tridach.
 *
 * Pouziti: `use ServiceAuthTrait;` v Service tride, ktera ma $this->auth (Auth).
 */
trait ServiceAuthTrait
{
    abstract protected function getAuth(): Auth;

    /**
     * Overi, ze entita existuje. Pokud je null, vola Response::notFound() (404).
     * Vraci entitu pro retezeni.
     *
     * @param  array<string, mixed>|null $entity
     * @param  string                    $message  Zprava pro 404 odpoved
     * @return array<string, mixed>
     */
    protected function requireEntity(?array $entity, string $message): array
    {
        if ($entity === null) {
            Response::notFound($message);
        }
        return $entity;
    }

    /**
     * Overi, ze aktualni uzivatel je vlastnik entity nebo ma roli admin.
     * Pokud ne, vola Response::forbidden() (403).
     *
     * @param  array<string, mixed> $entity      Entita s polem $ownerField
     * @param  string               $ownerField  Nazev pole s ID vlastnika (default 'user_id')
     */
    protected function requireOwnerOrAdmin(
        array $entity,
        string $ownerField = 'user_id'
    ): void {
        if (
            !$this->getAuth()->hasRole('admin') &&
            (int) $entity[$ownerField] !== $this->getAuth()->id()
        ) {
            Response::forbidden();
        }
    }
}
