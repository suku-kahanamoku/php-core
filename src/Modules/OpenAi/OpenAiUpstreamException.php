<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/**
 * Oznamuje chybu spojeni nebo neocekavanou odpoved vzdaleneho OpenAI API.
 *
 * Verejna API vrstva nesmi klientovi predavat puvodni telo odpovedi, proto tato
 * vyjimka nese pouze bezpecny provozni popis a volitelny HTTP status upstreamu.
 */
final class OpenAiUpstreamException extends \RuntimeException
{
}
