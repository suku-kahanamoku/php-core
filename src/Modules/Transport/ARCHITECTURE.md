# Transport: modularita, rozšiřování a škálování

Tento dokument popisuje implementaci v tomto checkoutu. Je závazným výchozím
postupem pro další AI agenty a vývojáře. Před změnou čti také `AGENTS.md`,
`README.md` a pravidla php-core. Ukázka nové země není tvrzení, že máme její data.

## 1. Čtyři samostatné pojmy

| Pojem | Odpovědnost | Příklad |
| --- | --- | --- |
| Země | Konfigurační balíček zdrojů a pokrytí | `Countries/CZ`, `Countries/NO` |
| Integrace / adaptér | Kód kontraktu konkrétní služby | Golemio, Spojenka, Entur, OpenTripPlanner |
| Protokol / formát | Znovupoužitelná technická implementace | Transmodel GraphQL, GTFS import |
| Instance providera | Tenant + stabilní `code` + `adapter` + konfigurace | `pid`, `pid-otp`, další instance stejného adaptéru |

Provider je **zdroj dat**, ne nutně dopravce. Dopravce uvnitř jízdního řádu je
`operator`. Integrace může obsluhovat více zemí; země může zapnout více integrací.
`code` je součást veřejných ID. Přesun tříd neopravňuje přejmenovat existující kódy.

## 2. Adresáře a povolené závislosti

```text
Transport/
  TransportModule.php           explicitní seznam instalovaných modulů a importérů
  Api/                          HTTP routy, validace vstupu, veřejná obálka
  Contracts/                    rozhraní integrací a schopností
  Model/                        dotazy, definice, interní výsledky, ID, časový rozpočet
  Core/                         výběr, orchestrace, fallback, provádění volání
  Integrations/
    Golemio/                    PID API, omezené skládání cest, realtime enrichment
    Spojenka/                   Spojenka API a mapování odpovědí
    Entur/                      Entur metadata, hlavičky a geokodér
    OpenTripPlanner/            OTP provider, identity feedů, export/aktivace grafu
  Protocols/Transmodel/          sdílené GraphQL dotazy a mapování
  Import/
    ImporterRegistry.php        továrny na čerstvé importéry
    FeedSyncService.php         download, zámek, transakce, snapshot, stav běhu
    Gtfs/                       parser a import formátu GTFS
    ServiceTimeService.php      provozní dny, GTFS čas a DST
  Persistence/                  tenantová data, stav zdrojů, sdílené kvóty
  Countries/CZ/                 český online preset
  Countries/NO/                 norský preset
  tests/                        orchestrace, tenanty, migrace a kontrakty architektury
```

- `Core`, `Model`, `Persistence` a `Protocols` nesmějí importovat konkrétní třídy
  z `Integrations`. `tests/modularity.php` tuto hranici kontroluje.
- `TransportModule` je místo, které zná instalované integrační moduly. API i CLI
  používají tento seznam; druhý seznam povolených adaptérů se nezavádí.
- Integrace implementuje kontrakty, používá `Model` a případně společný protokol.
  Konstrukční modul může dostat repository kvůli načtení runtime stavu. Například
  OTP při konstrukci doplní aktivní graf; obecné repository nezná název adaptéru.
- Propojení identit probíhá přes `ResourceMappingProvider` a `ProviderRegistry`.
  Adaptér nemá přímo volat jinou integraci ani její síťový klient.
- Skutečnou HTTP komunikaci vždy provádí klient z `HttpModule::client()`, předaný
  z composition rootu. Žádný nový cURL, `file_get_contents(URL)` ani vlastní Guzzle.
- `ProviderHttpService` je plánovací fasáda implementující stávající `HttpClient`.
  Nemá vlastní síťový transport. Volání předává executorovi přes PHP Fibers.

## 3. Registrace integrace

Každá integrace má implementaci `Contracts/IntegrationModule`:

```php
public function adapter(): string;
public function validate(ProviderDefinition $definition): void;
public function create(
    ProviderDefinition $definition,
    array $env,
    ?TransportRepository $repository = null
): Provider;
```

`adapter()` vrací stabilní identifikátor implementace, např. `pid`.
`validate()` kontroluje endpointy, konkrétní klíče konfigurace a vazby vyžadované
protokolem. `create()` sestaví provider s tenantovými credentials pro jednotlivé
požadavky. Konstrukce a validace **nevolají externí API**. Konfigurátor může
`create()` zavolat s prázdným `$env` a bez repository, aby ověřil capabilities.

Nový modul přidej jednou do `TransportModule::integrations()`. `IntegrationRegistry`
odmítá neznámý adaptér a duplicitní registraci. Databáze/JSON obsahují identifikátor
adaptéru; nikdy jméno PHP třídy, cestu k souboru nebo spustitelný kód.

Produkční sestavení použije:

```php
$registry = TransportModule::registry($repository, $_ENV);
$api = TransportModule::api($repository, $_ENV, HttpModule::client());
```

`ProviderRegistry::build()` dostává tentýž `IntegrationRegistry` explicitně.
Přímé konstrukce providerů patří převážně do izolovaných testů. Obcházení modulu
může vynechat runtime nastavení, například aktivní graf nebo výchozí kvótu.

## 4. Kontrakty schopností

| Kontrakt | Kdy jej implementovat |
| --- | --- |
| `Provider` | Definice instance a seznam skutečně podporovaných operací |
| `JourneySearchProvider` | Jedno HTTP volání pro cesty: sestavení requestu a mapování response |
| `OnlineJourneySearchProvider` | Více navazujících online volání; `supportsQuery()` a `searchOnline()` |
| `ResourceProvider` | `places`, `nearby_stops`, `stop`, `trip`, `departures`, `realtime` dle skutečného API; OTP/Transmodel doplní `places` jen s nakonfigurovaným `geocoder_url` (OTP Geocoder API) |
| `ResourcePreparationProvider` | Přípravná online volání před detailem; např. ověření provozního dne vozidla |
| `JourneyEnrichmentProvider` | Volitelné doplnění již nalezených cest, bez persistence a změny jejich identity |
| `ResourceMappingProvider` | Ověřený převod zdrojových ID pro online detail nebo zálohu |
| `ScheduleProvider` | Plánovač nad aktivovaným snapshotem; výsledky mají původ `schedule` |
| `FeedImporter` | Import konkrétního formátu do nové neaktivní verze katalogu |

`capabilities()` popisuje skutečné schopnosti implementace. Konfigurace je může
vypnout; nemůže doplnit neimplementovanou operaci. `enrich_journeys` je interní
schopnost Golemia a nepublikuje se v seznamu veřejných capabilities.

Nepřenášej specifické requesty ani validaci odpovědí do `JourneyService` či
`ResourceService`. Příkladem hranice je Golemio: `prepareResource()` online ověří
instanci spoje před dotazem na polohu. `ResourceService` zná jen tento kontrakt.

Transmodel sdílí GraphQL tvar, nikoli data, identity ani pravidla konkrétní služby.
`EnturProvider` doplňuje geokodér a klientské hlavičky; `OtpProvider` doplňuje vazbu
na statický graf a feed namespace. Nevkládej `if country == ...` do protokolu.

## 5. Země a konfigurace tenantů

`Countries/<ISO2>/providers.example.json` je verzovaný preset obsahující `providers`
a volitelně `feeds`. Aktuálně jsou instalované AT, CZ, NO a SK. Rakouský preset
obsahuje neperzistentní Wiener Linien realtime odjezdy. Slovenský preset
obsahuje pouze komerčně použitelný plánovaný DPB GTFS feed; jeho OTP provider
zůstává vypnutý, dokud tenant neaktivuje ověřený graph. DE/DK nemají vymyšlené
endpointy nebo zástupné funkční providery. Složka země nepotřebuje PHP třídu.

Soukromá serverová konfigurace může obsahovat například:

```json
{
  "countries": ["CZ", "NO"],
  "providers": [
    {"code": "entur", "config": {"client_name": "moje-aplikace-transport"}}
  ],
  "feeds": []
}
```

`CountryConfigurationService::compose()` provede:

1. načtení instalovaných presetů; ISO kód nesmí obsahovat cestu;
2. sloučení podle stabilního `code`; rozdílné definice stejného kódu v presetech
   jsou chyba, identické definice společného zdroje jsou povolené;
3. aplikaci výslovných tenantových přepisů `providers` a `feeds` podle kódu;
4. validaci výsledného celku před konfigurací databáze.

Položky providerů/feedů se slučují podle kódu. U `config` se slučuje první úroveň
klíčů. Hodnoty `coverage`, `fallback_for`, `config.operations` a `config.quota` se
při přepisu nahrazují **celé**. Žádné indexové slučování seznamů. Duplicitní kód
v explicitních přepisech nebo duplicitní země jsou chyba.

Starší kompletní JSON bez `countries` funguje dál. Konfigurátor provádí upsert
pouze pro uvedené kódy. Odebrání země z JSON nemaže dříve uložené providery;
pro vypnutí použij `published: false`. Po změně presetu/JSON musí proběhnout
`transport-configure.php`; úprava souboru sama nepřepne uloženou konfiguraci.

Credentials patří do prostředí/soukromé konfigurace. Země neurčuje automaticky
jazyk uživatele, měnu ani časové pásmo každé zastávky. Časy a provozní dny se
mapují z kontraktu zdroje; nepřepočítávají se odhadem podle názvu země.

### Politika jednotlivých operací

Globální `role` a `fallback_for` zůstávají výchozí hodnotou. Přepis je v `config`:

```json
{
  "operations": {
    "places": {"enabled": false},
    "departures": {"role": "primary", "priority": 10},
    "journeys": {"role": "fallback", "fallback_for": ["main-planner"], "priority": 20}
  }
}
```

Ukázka platí jen pro adaptér, který všechny uvedené operace umí, a konfiguraci,
kde `main-planner` existuje. Nižší `priority` má přednost při plánování volání;
při shodě se zachovává pořadí registru. Priorita neznamená, že se ostatní vhodné
primární zdroje automaticky přeskočí. Kontrakt zatím nemá strategii „první úspěch“.

`places`/`nearby_stops` vybírají podle státu, města a GPS. Vyhledání cesty vybírá
podle pokrytí **obou konců** a omezení adaptéru; stát/město z UI nejsou zákaz
přeshraniční cesty. Bbox je hrubý filtr, nikoli přesná státní hranice nebo důkaz
existence spojení. Detaily se směrují podle zdrojového ID.

## 6. Provádění, souběžnost a časový rozpočet

`RequestBudget` používá monotónní čas. `JourneyService` jej vytváří na začátku
hledání a předává rozlišení koncových míst, nejbližším zastávkám, primárním zdrojům,
zálohám a enrichmentu. Výchozí rozpočet odchozího I/O je 8 sekund. Primární fáze
má nejvýše 5 sekund, záloha 3 sekundy a jednotlivý enrichment 1,5 sekundy; každá
fáze je navíc omezena skutečně zbývajícím rozpočtem. Samostatné resource dotazy
mají vlastní rozpočet. Není to tvrdé SLA celého PHP procesu: SQL, mapování a
serializaci nelze tímto mechanismem preemptivně přerušit.

`ProviderExecutionService::run($providers, $operation, $budget)`:

1. ověří tenant a circuit admission;
2. spustí operaci každého providera ve Fiberu;
3. `ProviderHttpService::sendAll()` odevzdá dávku executorovi;
4. executor rezervuje kvótu **každému HTTP requestu**, spojí připravené requesty
   různých providerů a zavolá injektovaný `HttpClient::sendAll()`;
5. odpovědi vrátí správným Fiberům, které mohou vytvořit další navazující dávku.

Provider tedy může napsat čitelné navazující kroky a další zdroje přitom běží
v týchž síťových kolech. Klíče requestů jsou lokální providerovi; executor řeší
kolize. Současně jsou nejvýše 4 síťové requesty. Limit souběhu jednotlivé dávky platí
pro daného providera; další poskytovatele neserializuje. Executor dělí místa
mezi čekající zdroje, po každém kole je střídá a dávce rezervuje jen requesty
odesílané v daném kole. Jeden provider v jednom `run()` může sestavit nejvýše
128 requestů.
Algoritmy adaptérů musí mít i vlastní limity kandidátů, řádků a přestupů.

Provider v běhu používá výhradně obdržený `HttpClient`. Nesmí si vytvořit další
klient, volat `HttpModule` ani zapisovat PDO transakci přes přerušení Fiberu.
Fibers zde koordinují I/O; nejde o paralelní PHP výpočty nebo o worker procesy.
Další krok začíná po dokončení celého síťového kola. Pomalý request tedy může
zpozdit navazující krok jiného zdroje v témže kole; nejde o průběžný asynchronní
stream jednotlivých odpovědí.
Enrichmenty se aplikují postupně na výsledek předchozího enrichmentu, aby se
vzájemně nepřepisovaly. I jejich síťová volání mají kvótu a společný deadline.

## 7. Kvóty, chyby a fallback

`ProviderQuotaRepository` používá MySQL transakci a zamčený řádek scope. Hlídá
minimální interval a volitelnou skutečnou klouzavou kvótu:

```json
{
  "min_interval_ms": 100,
  "quota": {"scope": "my-shared-credential", "limit": 20, "window_ms": 8000}
}
```

Bez explicitního `scope` je kvóta obvykle tenant/provider. Golemio při konstrukci
přidá výchozí 20/8000 a při dostupném tokenu odvodí sdílený scope z jeho otisku;
stejný klíč proto spotřebovává jednu kvótu i napříč tenanty. Token není v DB,
veřejném coverage ani ve sdíleném HTTP klientu. Explicitní scope může určit
provozovatel, například pro více klíčů se společným smluvním limitem.

Všechny instance stejného scope musí mít shodný interval/limit/okno. Konflikt
vyvolá `invalid_quota_configuration`, nemá se skrýt přechodem na zálohu.
Změnu politiky sdíleného scope koordinuj při odstávce jeho uživatelů; nejdříve
nech doběhnout staré rezervace/cooldown a odstraň neaktivní stav úklidem. Za běhu
nepřejmenovávej scope jen kvůli obejití vyčerpané kvóty.

Tabulky `transport_provider_quota` a `transport_provider_quota_usage` ukládají
hash scope a časové rezervace. Neobsahují payloady, URL, identifikátory zastávek,
polohy ani credentials. Limity platí napříč PHP procesy sdílejícími tuto DB.
Pro více nezávislých DB je nutný společný admission backend; dnešní implementace
není mezi takovými instalacemi distribuovaná. Rezervace se po timeoutu nevrací,
protože nelze prokázat, zda externí API request započítalo.

| Stav | Význam | Spustí zálohu |
| --- | --- | --- |
| `ok`, včetně prázdného výsledku | Zdroj odpověděl podle kontraktu | Ne |
| `unavailable` | Síť/API/kontrakt zdroje selhal nebo je otevřený circuit | Ano |
| `schedule_unavailable` | Graf není připravený/platný pro hledaný den | Dle vazby záloh |
| `throttled` | Místní kvóta nedovolila další request | Ne |
| `deadline_exceeded` | Lokální rozpočet či limit počtu requestů byl vyčerpán | Ne |
| `not_found` | Zdroj výslovně nemá daný objekt | Ne |
| `unsupported_capability` | Zdroj neumí požadovanou operaci | Ne |
| `configuration_error` | Například konflikt sdílené kvóty | Vyhodí chybu konfigurace |

Skutečný timeout síťového volání je výpadek zdroje; pouhé vyčerpání lokálního
rozpočtu před odesláním se za něj nevydává. `Retry-After` blokuje sdílený scope
(max. hodinu). Existující circuit breaker zůstává oddělený podle tenant/provider;
není zatím samostatný pro každou operaci. Úspěšná operace se skutečným HTTP
voláním jej obnoví; enrichment bez odpovídajících identit jej neresetuje. Obyčejné
omezení kvóty nezvyšuje počítadlo selhání zdroje.

Online fallback pro hledání míst/cest má roli `fallback` a vazbu `fallback_for`.
Importovaný katalog se čte pouze pro selhané relevantní zdroje. Přesměrování
resource detailu navíc vyžaduje ověřenou identitu; samotná konfigurace priority
nemůže prohlásit cizí stop ID za shodné.

## 8. Identity a časové údaje

Veřejné ID nadále nese tenant, provider code, druh objektu, externí ID a případně
provozní den. Nejde o autentizační token. Tenant a druh ověřuje `ResourceIdCodec`;
provider musí být dostupný v tenantovém registru. Při refaktoru se formát ID nemění.

`ResourceMappingProvider::sourceReference()` popisuje ověřenou vazbu ze statického
zdroje na online zdroj. `fallbackReference()` opačný přechod při skutečném výpadku.
Mapování zachovává provozní den a typ objektu. `canonicalReference()` odhaluje cyklus.
Při explicitním přechodu na schedule fallback se online přesměrování znovu
neaplikuje; jinak by vznikla smyčka OTP → Golemio → OTP.

Příklad: OTP `pid:T1` lze převést na PID `T1` pouze pro explicitní `otp_feed_id`
a `source_provider`. Podobný název zastávky nebo blízkost bodů není dostatečný důkaz.
Stejně tak vozidlo patří ke konkrétní instanci spoje v provozním dni.

Zachovej pravidla čerstvosti GPS, null pro nedostupné polohy, oddělení plánovaných
a očekávaných časů, GTFS časů nad 24 hodin a DST. Nezaváděj ukládání poloh uživatelů
ani vozidel, surových realtime odpovědí nebo historii jejich pohybu.

## 9. Import a provoz na pozadí

`TransportModule::importers()` registruje továrny na `FeedImporter`. Každé
`ImporterRegistry::get()` vrací novou instanci; po rollbacku nesmí zůstat dávky
předchozí verze v paměti. `config.format` feedu je volitelné, výchozí `gtfs`.
Neznámý formát je odmítnut před downloadem. NeTEx/JDF nejsou implementované.

`FeedSyncService` řeší zámek tenant/feed, download přes injektovaný HTTP klient,
checksum, verzovaný snapshot a transakci. Importér řeší formát, validaci a zápis
do kanonických tabulek. Import sám neaktivuje graf. `storage_allowed` se kontroluje
při konfiguraci i při běhu. Síťové limity interaktivního executoru se na velký
feed download nepoužívají; ten má vlastní omezený download kontrakt HttpModule.

Spouštěj `transport-sync.php` jako samostatnou úlohu na pozadí pro konkrétní
`--tenant` a `--feed`. Dvěma běhům stejného feedu zabrání MySQL zámek; různé feedy mohou
zpracovávat různí workeři, podle kapacity DB/CPU/disku. Plánování cron/queue je
provozní konfigurace, tento refaktor nezakládá nový démon ani produkční cron.
Build OTP běží zvlášť od PHP requestů a musí mít vlastní limity paměti a souběhu.

Počet zemí nevytváří nové sady tabulek. Katalog používá tenant/feed/verzi.
Stav kvót sdílí jen provozní scope, nikoli oprávnění k datům. Globální deduplikace
feedů mezi tenanty není součást implementace.

## 10. Postup pro další AI: nová země / integrace / formát

### Nová země se známým API

1. Ověř skutečný kontrakt a pokrytí zdroje. Stejný název protokolu nezaručuje
   kompatibilitu všech polí, autentizace, verzí a významu ID.
2. Pokud stávající integrace vyhovuje, přidej preset do `Countries/XX` a jeho README.
   Nový provider `code` potřebuje jen skutečně nová instance zdroje.
3. Nastav pokrytí, attribution, časové údaje, credentials reference, kvótu a role
   jednotlivých operací. Sdílený provider může mít více položek `coverage`.
4. Otestuj složení presetů a tenantových přepisů. Nepřepisuj jiné tenanty.
5. Zdokumentuj ověřený rozsah dat a zbývající omezení. Přidání presetu není nasazení.

### Nové API

1. Vytvoř `Integrations/<Service>/`, provider, mappery a `IntegrationModule`.
2. Implementuj jen reálné capabilities. Vícekrokové volání musí používat klienta
   předaného executorovým callbackem a mít omezený počet kroků/kandidátů.
3. Přidej jednu registraci do `TransportModule::integrations()`.
4. V integrační složce přidej uložené bezpečné fixtures a kontraktní testy;
   zapoj je do `tests/integration.php` nebo `tests/modularity.php`.
5. Otestuj minimálně úspěch, úspěšný prázdný výsledek, vadnou odpověď, timeout,
   capability omezení, ID/provozní den a použitelný fallback. Živé ověření uváděj
   zvlášť; fixture test není důkaz dostupnosti produkční služby.
6. Přidej konfiguraci/preset a aktualizuj README integrace i tento dokument při
   zavedení nového obecného kontraktu. Nový zdroj běžně nevyžaduje změnu Core/API.

### Nový importní formát

1. Implementuj `FeedImporter` ve `Import/<Format>`.
2. Přidej továrnu do `TransportModule::importers()` a nastav `config.format` feedu.
3. Zachovej tenantové FK, oddělení verzí, rollback, limity objemu a validaci časů.
4. Otestuj opakovaný běh po úspěchu i po chybě. Napůl importovaná data se nesmí číst.
5. Ověř kompatibilitu plánovače s výsledkem. Dnešní OTP export umí GTFS;
   registrace jiného importéru sama nezavádí podporu exportu jeho archivu do OTP.

### Povinné ověření změny

```bash
bash scripts/test-transport.sh
bash scripts/test-http.sh
# PHP lint změněných souborů a git diff --check
# Podle root AGENTS.md také graphify update .
```

Transport testuje v nové dočasné MySQL přes UNIX socket. Nepoužívej aplikační DB.
Testy architektury mají ověřit chování a hranice: samostatně registrovat nový
adaptér bez úpravy jádra, izolaci tenantů, vícekrokový souběh, společný deadline,
kvótu každého requestu a rozdíl výpadku/prázdné odpovědi/omezení kvóty.

## 11. Migrace a aktuální hranice

Před nasazením této verze aplikuj `migrations/tram_modularity.sql` vedle existujícího
`schema.sql`, `tram_schema.sql` a případného `tram_seed.sql`. Nová migrace je
aditivní a opakovatelná. Nevynucuje přepis uložených providerů; staré kódy, JSON a
veřejné URL zůstávají použitelné. PHP namespaces/cesty byly změněny a interní
volající musí používat nové třídy. Není poskytovaná vrstva aliasů starých namespaces.

`transport-cleanup.php` kromě tenantové cache/sync logů čistí globální admission
časové záznamy starší než maximální hodinové okno a již neaktivní scope. Tyto
záznamy neobsahují dopravní ani osobní data. Pro změnu kvót neruš aktivní rezervace.

Dosud platí tyto konkrétní hranice:

- Hledání vyžaduje plánovač pokrývající oba konce cesty. Neskládá libovolné
  mezinárodní itineráře z oddělených API bez ověřených přestupů a identit.
- Jeden aktivovaný OTP graf je stále svázaný s jedním feed snapshotem. Pro graf
  nad více feedy je potřeba samostatná verze grafu s manifestem všech verzí vstupů,
  jejich společná validace, atomická aktivace a rollback. Nevkládej druhý feed
  do existujícího adresáře aktivního grafu. Tento budoucí kontrakt není hotový.
- Bbox není přesný geografický resolver. Další geografickou přesnost přidávej
  do společného výběru pokrytí a jeho dat, nikoli jako podmínky pro konkrétní země.
- Detail cesty stále používá sanitizovanou patnáctiminutovou MySQL cache popsanou
  v README. Refaktor nezavedl ukládání GPS ani nevyřešil samostatný cílový kontrakt
  online detailů. Přesun cache na jiný backend nesmí změnit pravidla soukromí.
- Výkon celé země ani horizontální kapacita nejsou prokázané fixture testy.
  Před rozšířením provozu měř počty externích volání, odezvu, odmítnuté kvóty,
  zdrojové chyby, stáří snapshotů a nároky importů; logy nesmějí obsahovat tokeny,
  těla požadavků nebo souřadnice uživatelů/vozidel.
