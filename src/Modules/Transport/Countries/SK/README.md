# SK preset

Preset přidává plánovaný GTFS feed Dopravného podniku Bratislava. Zdroj je
publikován pod CC BY 4.0; aplikace musí zachovat uvedení původu dat.

`dpb-otp` je záměrně `published: false`. Po synchronizaci feedu je potřeba
sestavit a aktivovat vlastní OTP graph, teprve potom lze provider zveřejnit
tenantovým přepisem:

```json
{
  "countries": ["SK"],
  "providers": [{"code": "dpb-otp", "published": true}]
}
```

Preset nepřidává online plánovač ani realtime data. ŽSR GTFS je samostatné
rozhodnutí: před zapnutím ukládání pro komerční použití je nutné ověřit jeho
licenci u poskytovatele.