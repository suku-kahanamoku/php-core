# SK preset

Preset přidává plánovaný GTFS feed Dopravného podniku Bratislava. Zdroj je
publikován pod CC BY 4.0; aplikace musí zachovat uvedení původu dat.

`dpb-otp` je záměrně `published: false` a `role: fallback`. Synchronizace a
aktivace grafu samy nezapnou vyhledávání. Tento preset neobsahuje primární
online službu pro SK. Je nutné nejprve přidat ověřený online zdroj, který
pokrývá Bratislavu, a nastavit `fallback_for` na jeho stabilní kód pro každou
povolenou záložní operaci. Teprve potom lze `dpb-otp` zveřejnit. Konfigurátor
odmítá zveřejněné zálohy bez aktivního primárního zdroje dané operace.

Samostatná příprava importu používá:

```json
{
  "countries": ["SK"]
}
```

Preset konfiguruje i `geocoder_url` pro fulltextové hledání zastávky podle
jména (operace `places`). Vyžaduje zapnutý `SandboxAPIGeocoder` v `otp-config.json`
nasazeného OTP — bez této volby OTP samotný endpoint `/otp/geocode/stopClusters`
neobsluhuje. Adresa geokodéru se za běhu odvodí z aktivního `graph_url`, včetně
portu a prefixu cesty; nesmí směrovat do jiné verze grafu. Ostatní operace OTP
tuto volbu nepotřebují, stále však vyžadují připravený graf a platnou vazbu
na primární online zdroj. Preset nepřidává online plánovač ani realtime
data. ŽSR GTFS je samostatné rozhodnutí: před zapnutím ukládání pro komerční
použití je nutné ověřit jeho licenci u poskytovatele.
