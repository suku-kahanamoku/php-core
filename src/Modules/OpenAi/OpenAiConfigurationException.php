<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/**
 * Oznamuje chybejici nebo neplatnou serverovou konfiguraci OpenAI integrace.
 *
 * Vyjimka oddeluje provozni konfiguraci od chyb vzdaleneho OpenAI API, aby API
 * vrstva mohla vratit bezpecny stav 503 bez zverejneni tajnych hodnot.
 */
final class OpenAiConfigurationException extends \RuntimeException
{
}
