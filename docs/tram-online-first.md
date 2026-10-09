# TRAM po odstranění PHP dopravy

Dopravní komunikace Astro vede přímo na Java API. PHP gateway, adaptéry,
SQL doprava i tracking byly odstraněny. V PHP zůstává přihlášení a ověření
oprávnění administrátora pro online přepínač; jeho stav ukládá Java.

- [PHP autorizace](../src/Modules/Transport/README.md)
- [Odstranění historické SQL databáze](../migrations/README.md#tram-po-přesunu-do-javy)
- [Java API a realtime](../../../java-tram/OTP/API.md)
- [Java VPS provoz](../../../java-tram/deployment/vps/README.md)

Polohy lidí/vozidel se v PHP neukládají. Obnova grafů a indexů je Java/VPS
úloha. PHP nemá sync/build/deploy endpoint ani dopravní cron/worker.
