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