# SRY: family tasks API

Veřejný mobilní vstup je izolovaný v `api/sry/index.php`. Používá mapovaný tenant `sry`, vlastní hashované sessions, rate limiting a kontrolu rodiny/role. Nemění oprávnění ostatních modulů ani `InternalAuthMiddleware`.

## Model

- `sry_family` → existující rodičovský `user`; `sry_member` → volitelný vlastní `user` dítěte.
- `sry_session`, `sry_invitation`, `sry_password_reset`: hashované neprůhledné tokeny s expirací.
- `tasks`: definice zadání; `task_assignment`: konkrétní dítě a datum, bodový snapshot, stav a revize.
- `task_media`: vzorová média zadání; `task_submission`: historie odevzdaných fotografií; `task_review`: unikátní rodičovské rozhodnutí k odevzdání.
- `task_points`: jediná odměna na přiřazení; přičítá se transakčně ve chvíli schválení, do lokálního dne rodiny.
- `sry_media`: neveřejný immutable R2 objekt; `category` a `enumeration` se používají pouze z tenantového katalogu sry.
- `sry_notification` + `sry_outbox`: transakční události pro členské WebSocket místnosti a push. `sry_chat`: jen účastníci rodič/dítě.

SQL: nejprve `migrations/schema.sql`, poté `migrations/sry_schema.sql` (16 tabulek) a volitelně `migrations/sry_seed.sql` (tenantová role a čtyři výchozí kategorie). Nesahá na produkty a ostatní tenanty. Endpoint ani klient nepřijímají rodinné ID jako důkaz oprávnění.

## Endpointy (prefix /api/sry)

| Metoda | Cesta | Přístup |
| --- | --- | --- |
| POST | /auth/signup, /auth/login | veřejně, rate limited |
| POST | /auth/reset-password, /auth/complete-reset | veřejně, jednorázový reset |
| POST | /auth/join | veřejně s jednorázovou rodičovskou pozvánkou |
| GET / POST | /auth/me / /auth/logout | vlastní relace |
| GET | /family | rodič celou rodinu, dítě jen sebe a rodiče |
| POST | /family | rodič; name, volitelně email/password |
| PATCH | /family/:id | rodič vlastnímu dítěti; daily_target, wifi_allowed, data_allowed |
| POST | /invitations | rodič; volitelný child_id pro již založený profil |
| GET | /catalog | publikované sry kategorie a enumerace |
| GET / POST | /tasks | vlastní úkoly / rodič zadává |
| GET | /tasks/:id | rodič dané rodiny nebo přiřazené dítě |
| POST | /tasks/:id/submit | dítě; revision, media_id, note |
| POST | /tasks/:id/review | rodič; revision, decision, note |
| POST | /media | vlastní upload: mime, size |
| POST | /media/:id/complete | vlastník, ověření objektu v R2 |
| GET | /media/:id | oprávněný člen získá krátký download ticket |
| GET / POST | /notifications / /notifications/:id/read | jen příjemce |
| POST / DELETE | /push | registrace/odregistrace vlastního push tokenu |
| GET | /realtime | ticket pouze pro vlastní místnost |
| GET / POST | /chat | vlastní vlákna / zpráva povolenému členu |

Review a submission vyžadují aktuální revizi. Zámek řádku, unikátní review a unikátní ledger brání opakovanému přičtení bodů. Vrácení vyžaduje poznámku. Bez připravené fotografie dítě neodevzdá úkol. Přístup k souboru jiné rodiny i mezi sourozenci je zakázaný; rodič může vidět důkazy své rodiny.

Odpověď: `{success:true,data:...}`; chyba: `{success:false,code:"..."}` s odpovídajícím HTTP statusem. Texty chyb překládá klient. Role se neposílá v registračním formuláři a nelze ji přepnout klientem.

## Testy a provoz

`bash scripts/test-sry.sh` vytvoří vlastní dočasný MySQL server bez TCP listeneru, sestaví potřebné základní tabulky, aplikuje migraci dvakrát, provede integrační scénáře i lokální HTTP kontrakt proti izolované databázi a server i data uklidí. Nepoužívá `.env` ani existující DB. Potřebuje `mysqld`, `mysqladmin`, PHP PDO MySQL a Composer autoload.

`php scripts/sry-outbox.php --watch` je serverová doručovací služba. Nepouštět před nastavením Cloudflare. Backend neposílá OpenAI žádné požadavky; `ManualReview` je vědomě vypnutá implementace budoucího portu.

Kompletní konfigurace a limity jsou v `sorry-jako/docs/deployment.md`. API a migrace jsou implementované; produkční nasazení, migrace produkční DB, SMTP, Cloudflare a fyzické push doručení vyžadují samostatné ověření.
