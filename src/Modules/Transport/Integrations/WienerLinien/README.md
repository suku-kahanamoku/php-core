# Wiener Linien

Tato integrace čte veřejný `monitor` endpoint Wiener Linien pro známé RBL ID
zastávky. Poskytuje pouze `stop` a `departures`; neumí hledání zastávek, routing
ani vozidlovou polohu.

## Žádná persistence

Adapter nevolá repository ani importér a odpovědi neukládá do databáze, souborů
nebo cache. Data jsou určena pro bezprostřední realtime informaci cestujícím a
Wiener Linien výslovně uvádějí, že nejsou vhodná pro dlouhodobé statistické
vyhodnocování. Tento návrh není obcházením licence: data jsou CC BY a každé
veřejné použití musí zachovat atribuci `Wiener Linien Open Data (CC BY)`.

## Konfigurace

```json
{
  "countries": ["AT"]
}
```

Preset používá `https://www.wienerlinien.at/ogd_realtime` bez API klíče. Do
`GET /api/transport/v1/stops/{id}/departures` se předává opaque ID, jehož externí
hodnota je RBL číslo monitoru. RBL ID musí dodat klient z důvěryhodného zdroje;
adapter je sám nevyhledává.

## Čas a výstup

Monitor poskytuje nejbližších 70 minut, není to historický ani libovolný budoucí
jízdní řád. `at` mimo tento interval (s minutovou tolerancí směrem do minulosti)
vrací `422 unsupported_time` před odchozím HTTP požadavkem. Nepovažuje se za
výpadek: nezvyšuje failure counter a neaktivuje databázovou zálohu.
V podporovaném intervalu se odjezdy filtrují od `at`, řadí podle skutečného
očekávaného času, případně plánovaného času, a teprve potom omezí na `limit`.

Údaje `vehicle` mají přednost před údaji celé linky: název, směr, typ dopravy,
bezbariérovost a vybavení. `metadata` zachovává `wheelchair_accessible`,
`folding_ramp`, `air_conditioning` jako `true`/`false`/`null` podle zdroje.
Klimatizace má také společný feature kód `AIR_CONDITIONING`. Stav vozidla u
zastávky je `at_stop`, dopravní zácpa `traffic_jam`. Nejde o GPS vozidla.

`alerts` obsahuje pouze výluky a oznámení explicitně propojené monitorem přes
`refTrafficInfoNames`, včetně textu, stavu, platnosti a souvisejících linek.
HTML poskytovatele se nepřenáší jako vykreslitelný obsah. Souřadnice zastávky
jsou ověřené na číselný tvar a rozsah. Nesouvisející monitory se nezpracují.

Kontrakt je ověřen podle [oficiální dokumentace v1.5](https://www.wienerlinien.at/ogd_realtime/doku/ogd/wienerlinien-echtzeitdaten-dokumentation.pdf).
Fixture testy pokrývají čas, limit, řazení, přepsání údajů vozidlem, vybavení,
propojení výluk a chybné souřadnice. To neprokazuje národní routing ani GPS.
