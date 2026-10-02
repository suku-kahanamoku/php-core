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
3. Novou zemi integruj jako úplný celek se dvěma oblastmi schopností:
   - **Primární online katalog a plánovač (vzor Spojenka):** seznam a hledání
     měst/jízdních řádů, našeptávání a hledání zastávek, nejbližší zastávky podle
     aktuální GPS, detail zastávky, vyhledávání spojení odkud–kam pro odjezd i
     příjezd, přestupy, detail spoje a všechny jeho zastávky včetně časů a pořadí.
   - **Doplňující online provozní data (vzor Golemio/PID):** aktuální odjezdy,
     zpoždění a očekávané časy, výluky a zrušení, aktuální polohy vozidel,
     vybavení a přístupnost, další dostupné detaily a omezené lokální plánování
     tam, kde jej API poskytuje nebo lze spolehlivě sestavit z jeho online dat.
   Obě oblasti může poskytovat jeden adaptér nebo více spolupracujících
   adaptérů. Není povinné kopírovat české implementace ani vytvářet dvě třídy;
   povinné je propojit všechny dostupné schopnosti do společné logiky TRAM.
4. Pokud první API některé údaje neumí, prověř další zdroje pro danou zemi
   a města. Dostupné a licenčně povolené realtime schopnosti se musí
   implementovat, nesmějí se odložit jako nepovinný bonus. Chybějící zdroj,
   oprávnění, credentials nebo nedostupná schopnost jsou konkrétní mezera,
   kterou zaznamenej v README země spolu s pokrytím a potřebným dalším krokem.
   Pouhý preset, GTFS import nebo adaptér jen pro odjezdy není hotová integrace
   země. Částečnou integraci lze připravit, ale nesmí se vydávat za kompletní
   podporu ani deklarovat neimplementované capabilities.
5. Ověř celý tok: výběr zdrojů podle země/města/GPS, katalog měst a zastávek,
   vyhledávání, načtení detailů, propojení provozních dat a frontend. Identity
   mezi zdroji propojuj pouze doloženými ID a provozním dnem. Zpoždění promítni
   do očekávaných časů, proveditelnosti přestupů, řazení, délky cesty a zobrazení;
   nepřičítej je slepě ke všem úsekům. Poloha vozidla je vždy živý údaj ze zdroje,
   nikdy odhad podle jízdního řádu. Polohy vozidel ani uživatelů se neukládají.
6. Zachovej online-first: primární hledání a data běží přes online služby;
   synchronizovaná DB a vlastní OTP slouží pouze jako nakonfigurovaná záloha při
   výpadku odpovídající online služby. Chybějící primární API není výpadek.
   Připravenost pro hledání v `coverage` není potvrzení, že jsou dokončeny všechny
   doplňující schopnosti země; jejich skutečný stav dokumentuj samostatně.

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
