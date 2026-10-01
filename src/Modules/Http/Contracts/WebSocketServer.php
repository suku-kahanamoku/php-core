<?php

declare(strict_types=1);

namespace App\Modules\Http\Contracts;

/**
 * Kontrakt dlouho běžícího JSON WebSocket serveru.
 *
 * Produkční implementaci vytváří HttpModule::websocket(). Doména dostává
 * pouze ID spojení, dekódované zprávy a callbacky; nezná Workerman ani sockety.
 * Implementace vlastní event loop, kontrolu originů, limity zpráv a spojení.
 */
interface WebSocketServer
{
    /**
     * Spustí obsluhu serveru; v produkci blokuje po dobu běhu event loopu.
     *
     * Callback message dostane ID spojení, JSON pole, funkci pro odeslání JSON
     * a funkci pro uzavření spojení. ID je unikátní po dobu života spojení.
     * Closed oznamuje uzavření a umožňuje uvolnit doménové odběry. Tick se volá
     * přibližně jednou za sekundu; callbacky nesmějí blokovat event loop.
     * Návratová hodnota odesílací funkce není potvrzením doručení klientovi.
     *
     * @param string $listen Loopback adresa websocket://127.0.0.1:PORT.
     * @param list<string> $origins Neprázdný seznam přesně povolených originů.
     * @param callable(string, array, callable(array): mixed, callable(): void): void $message Příchozí zpráva.
     * @param callable(string): void $closed Uzavření spojení.
     * @param callable(): void $tick Pravidelná údržba domény.
     * @param string $name Název workeru pro provozní identifikaci.
     * @param string|null $runtimeDirectory Soukromý adresář provozních souborů, null pro výchozí.
     * @throws \InvalidArgumentException Při neplatné konfiguraci.
     * @throws \RuntimeException Pokud server nelze spustit.
     */
    public function run(
        string $listen,
        array $origins,
        callable $message,
        callable $closed,
        callable $tick,
        string $name = 'websocket',
        ?string $runtimeDirectory = null,
    ): void;
}
