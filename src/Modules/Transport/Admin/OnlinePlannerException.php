<?php

declare(strict_types=1);

namespace App\Modules\Transport\Admin;

/** Safe error from the authenticated online planner authorization bridge. */
final class OnlinePlannerException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
