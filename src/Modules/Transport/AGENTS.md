# Transport: pravidla pro další změny

Před prací přečti `README.md` a `ARCHITECTURE.md`. Platí také root `AGENTS.md`.

Mise modulu: postupně propojit veřejnou dopravu co nejvíce zemí (nejprve EU)
do jednoho systému. Při zadání další země/providera/API pro vyhledávání spojů
platí vždy tento postup, bez ohledu na to, zda je v zadání výslovně rozepsaný:

1. Nejprve ověř licenci a podmínky použití podle licenčního gate v root
   `AGENTS.md`. Bez potvrzeného oprávnění se adaptér nepřidává.
2. Po potvrzení licence implementuj z reálných dat providera vše, co nabízí
   nebo co z jeho dat lze spolehlivě odvodit: vyhledání spojení z bodu A do
   bodu B v zadaném čase, časy odjezdů/příjezdů, zastávky a mezizastávkové
   detaily (trasa, pořadí a přestupy). Nikdy si nevymýšlej názvy zastávek,
   linek, spojů ani časy — používej výhradně to, co vrací API/feed/adaptér.
   Pokud provider danou operaci nenabízí a nelze ji odvodit z jeho dat,
   operaci vynech a rozsah jasně zdokumentuj, místo aby ses ji snažil nahradit
   vymyšlenými hodnotami.
3. Realtime rozšíření (zpoždění, aktuální poloha vozidla) je bonus, ne
   povinnost. Implementuj ho, pokud to provider nabízí a licence to dovoluje;
   chybějící realtime není důvod integraci zamítnout ani okleštit její rozsah
   z bodu 2.

- Rozšiřuj integrace v `Integrations/<Service>`; země skládej přes `Countries/<ISO2>`.
  Nová země sama nevyžaduje nový provider ani kopii protokolu.
- Instalované implementace registruje výhradně `TransportModule`. Nezaváděj další
  adapter allowlist ani načítání PHP tříd/cest z JSON, DB nebo uživatelského vstupu.
- `Core`, `Model`, `Persistence` a `Protocols` nesmějí importovat `Integrations`.
  Specifické realtime přípravy, mapování ID a enrichment patří integračním kontraktům.
- Síť jde přes injektovaný kontrakt HttpModule. Vícekrokové interaktivní operace
  používají klienta z `ProviderExecutionService`; nepřeskakuj jeho quota/deadline.
- Každý externí HTTP request spotřebuje kvótu, i v dávce nebo enrichmentu. Stejný
  klíč/pool sdílí quota scope. Místní throttle ani úspěšná prázdná odpověď nejsou výpadek.
- Zachovej stabilní provider codes a formát ID, tenantovou izolaci, provozní den,
  online-first pravidla a zákaz ukládání GPS. Identity spojuj pouze doloženou vazbou.
- Importér vzniká z nové tovární instance pro každý běh. Zápis jde do neaktivní verze;
  aktivaci/rollback plánovače řeš odděleně. GTFS není implicitní podpora jiných formátů.
- Testuj na dočasné DB pomocí `scripts/test-transport.sh` a `scripts/test-http.sh`,
  ověř PHP lint a diff. Nové integrační fixtures patří k integraci a musí být zapojené
  do test runneru. Dodržuj Graphify workflow z root pravidel.
- V dokumentaci přesně rozliš implementovaný kontrakt, fixture ověření, live ověření
  a budoucí práci. Nevydávej preset země nebo registraci adaptéru za produkční nasazení.
