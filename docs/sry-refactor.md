# Sry: sjednocení se sdílenými moduly

## Dokončeno a ověřeno

- `SryService` již nepřijímá `SrySqlRepository` a neobsahuje SQL.
- Rodiny, úkoly, média, oznámení a chat používají `FamilyRepository`, `TaskRepository`, `MediaRepository`, `NotificationRepository` a `ChatRepository`.
- `SryRepository` dědí společnou `BaseRepository`; přidává pouze transakční hranici se savepointy. Nezpřístupňuje obecné metody pro SQL.
- Existující uživatelské a katalogové tabulky obsluhují `UserRepository`, `RoleRepository`, `CategoryRepository` a `EnumerationRepository`.
- Pomocná validace vstupů service je oddělená do `SryInput`; service není závislá na `SryAuth`.
- API a integrační testy používají nový konstruktor service. Způsob přihlašování se v této části nemění.
- `bash scripts/test-sry.sh`: 44 kontrol prošlo nad dočasným MySQL bez TCP. Kontroly zahrnují izolaci rodin, QR, reset, historii úkolů, oprávnění k médiím a souběžné schválení bez dvojích bodů.

## Zbývá: autentizace — implementace čeká na schválení

Automatická kontrola oprávnění zamítla širší změnu sdíleného Auth i následnou užší variantu v Sry. Následující návrh proto zatím není aplikovaný:

1. `SessionService` bude pouze adaptérem mobilní odpovědi a členství v rodině. Přihlášení, registraci a reset hesla deleguje na existující `AuthService`, tokeny na `Auth` a `UserTokenRepository`. Sdílené autentizační třídy se v užší variantě nemění.
2. QR pozvánky přesunout do `PairingService` a `InvitationRepository`; jednorázové spotřebování a zámek zachovat.
3. Dítě bez přihlašovacích údajů dostane vlastní `user` se standardní rolí user, prázdným e-mailem ve formě NULL a náhodným neznámým heslem. Rodičovský token se dítěti nevydává. To vyžaduje změnit `user.email` na nullable a doplnit vazby existujícím dětským profilům, při zachování všech současných uživatelů a ID.
4. Nové relace bude vydávat společný Auth. Staré 30denní mobilní relace zachovat pouze pro čtení/odvolání do jejich expirace; nevytvářet další. Reset/odhlášení musí rušit i odpovídající staré relace.
5. Vyřešit kompatibilitu resetovacích odkazů a chybových odpovědí mobilu, aby přechod neobcházel validaci ani nerušil ostatní tenanty.
6. Po přepojení API a doručovací služby odstranit `SryAuth` a `SrySqlRepository`. Historické tabulky odstranit až samostatně po skončení kompatibility, nikoli automaticky při refaktoru.

## Zbývá: překlady push — implementace čeká na schválení

Automatická kontrola zamítla i lokální úpravu push/outbox kontraktu a přípravu migrace:

1. Překlady držet pouze v `sorry-jako/modules/Notify/locales`.
2. Mobil při registraci push zařízení odešle texty pro známé události v aktuálním jazyce; při změně jazyka a obnovení aplikace je aktualizuje.
3. Aditivní migrace přidá nullable JSON `messages` do `sry_push_device`. Backend přijme jen známé klíče a omezené délky. Texty se použijí pouze pro příslušné zařízení.
4. Doručovací služba odešle uložené texty jako běžné viditelné push notifikace. Pro staré klienty bez textů bude dočasně používat pouze název značky, obsah události zůstane dostupný v aplikaci.
5. SQL doručování přesunout do `NotificationRepository`, odstranit serverovou složku `Sry/locales`. Obnovu hesla sjednotit se stávajícím Mailer/Auth tokem; e-mailové šablony se nevykonávají v mobilu.

## Podmínky dokončení

- Nové migrace připravit a aplikovat pouze na izolované testovací DB, včetně testu opakované aplikace.
- Ověřit staré i nové relace, účty dítěte a rodiče, reset/QR replay, přístup jiného tenantu a rodiny a regresi společného Auth.
- Ověřit jazyk push payloadu a přeregistraci zařízení, mobilní TypeScript a příslušné testy.
- Žádná produkční migrace, restart služby, deploy ani rozesílání skutečných oznámení není součástí tohoto lokálního refaktoru.
