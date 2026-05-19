<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Auth\Auth;
use App\Modules\Router\Response;

/**
 * Zakladni abstraktni trida pro vsechny service tridy.
 *
 * Poskytuje sdilene pomocne metody:
 *   - requireEntity() — overi existenci entity, jinak ukonci s 404
 */
abstract class BaseService
{
    protected Auth $_auth;

    /**
     * Overi, ze entita existuje. Pokud je null, vola Response::notFound() (404).
     *
     * @param  array<string, mixed>|null $entity
     * @param  string                    $message  Zprava pro 404 odpoved
     * @return void
     */
    protected function _requireEntity(?array $entity, string $message): void
    {
        if ($entity === null) {
            Response::notFound($message);
        }
    }
}
