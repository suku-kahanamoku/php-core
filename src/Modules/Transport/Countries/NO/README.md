# NO preset

Entur pro nakonfigurované norské pokrytí. Používá `EnturProvider` nad sdíleným
Transmodelem a vlastní Entur geokodér. V tenantové konfiguraci přepiš `client_name`
na identifikaci své aplikace. Preset neobsahuje lokální záložní feed.

Zapnutí: `countries: ["NO"]`, pak `transport-configure.php`. Více zemí může používat
stejnou instanci zdroje pouze pokud je ověřené její reálné pokrytí; hranice bbox
nejsou důkazem dostupnosti mezinárodního spojení.

Rozšiřování popisuje [ARCHITECTURE.md](../../ARCHITECTURE.md).
