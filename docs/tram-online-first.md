# TRAM: současné rozdělení odpovědností

Od 3. 10. 2026 byl na zadání uživatele odstraněn původní PHP online-first
Transport a GTFS modul. PHP dnes pouze zabezpečuje a předává požadavky do
Java API; synchronizaci, katalog a hledání zajišťují Java GTFS, OSM a OTP.

Aktuální Java režim používá vlastní aktivní GTFS/OSM graf. Katalog grafu se
používá také při zdravém provozu; nejde o původní federaci online API se SQL
zálohou pouze při výpadku. Java country router vybírá vlastní worker podle
země. Nepropojuje automaticky Spojenku/Entur ani SQL snapshot. Tento dokument
není pokynem k obnovení odstraněných PHP adaptérů.

- [PHP gateway a konfigurace](../src/Modules/Transport/README.md)
- [Java architektura](../../../java/ARCHITECTURE.md)
- [Skutečné mezery oproti historickému PHP](../../../java/PARITY.md)
- [Lokální země a ověřené pokrytí](../../../java/OTP/INTERNATIONAL-LOCAL.md)

GTFS a OSM publikují statické verzované artefakty, OTP z nich sestavuje graf.
Realtime zpoždění a GPS používají Java adaptéry v RAM; polohy uživatelů a
vozidel nejsou trvale ukládány. Veřejné souřadnice zastávek jsou statická data.
Staré TRAM SQL skripty byly odstraněny z projektu; existující DB data zůstávají.
Původní PHP cron a graph/tracking procesy se již nespouštějí.
