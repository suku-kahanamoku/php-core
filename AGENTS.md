# php-core conventions

- Outbound HTTP communication must use `App\Modules\Http\Contracts\HttpClient`, obtained from `HttpModule::client()` at composition roots. Domain services accept the interface through constructor injection. Do not add direct cURL, HTTP stream calls or separate network clients to other modules.
- SMTP uses `HttpModule::smtp($franchiseCode)` and `Contracts\MailClient`. Future SOAP/WebSocket protocol adapters belong to `Modules/Http`, with their own explicit contracts and lifecycle. PDO database access stays in the database/repository layer.
- Provider classes own endpoint allowlists, authentication payloads and domain response mapping. Keep tenant credentials per request, never on the shared Guzzle client. Preserve existing auth and tenant boundaries.
- Name classes/files by responsibility: `Repository`, `Service`, `Provider`, `Registry`, `Api`; use meaningful `Request`, `Response`, `Exception`, `Mapper`, `Codec`, `Reader` suffixes for other roles. Update all callers when renaming.
- Use additive, idempotent database migrations. Run integration tests against disposable test databases, never the application DB. HTTP refactors do not require applying the Transport migration.
- Every new list/search endpoint MUST use the standard JSON/MongoDB-compatible query contract in `docs/query-filter-contract.md`: a single `q` filter, `sort`, `projection`, `page`, `limit`, `QueryPolicy` allowlists, rows under `data`. Never add a bespoke search parameter or query grammar.
- Relevant checks: `bash scripts/test-http.sh`, `bash scripts/test-transport.sh`, PHP lint and `git diff --check`. See `src/Modules/Http/README.md` for transport limits, downloads and extension guidance.
