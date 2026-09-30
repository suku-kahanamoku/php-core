# CZ preset

České online zdroje: Spojenka a Golemio. Toto je stejný výchozí rozsah jako
`config/transport.cz-online.example.json`; neobsahuje celostátní záložní katalog.
Spojenka má povolené našeptávání, u Golemia je `places_enabled: false`.

Pro použití nastav `countries: ["CZ"]` v soukromé konfiguraci a spusť
`transport-configure.php`. Golemio potřebuje serverový `TRANSPORT_PID_TOKEN`;
jeho modul sdílí kvótu podle tokenu. Podmínky a limity produkčního použití
Spojenky popisuje hlavní README. OTP zálohu lze přidat explicitní konfigurací
z `config/transport.example.json` včetně feedu a ověřené aktivace grafu.

Přidání SK/DE nebo dalšího českého regionu nesmí vytvořit kopii těchto providerů.
Postup je v [ARCHITECTURE.md](../../ARCHITECTURE.md).
