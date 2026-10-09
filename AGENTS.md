# php-core conventions

- Outbound HTTP communication must use `App\Modules\Http\Contracts\HttpClient`, obtained from `HttpModule::client()` at composition roots. Domain services accept the interface through constructor injection. Do not add direct cURL, HTTP stream calls or separate network clients to other modules.
- SMTP uses `HttpModule::smtp($franchiseCode)` and `Contracts\MailClient`. Future SOAP/WebSocket protocol adapters belong to `Modules/Http`, with their own explicit contracts and lifecycle. PDO database access stays in the database/repository layer.
- Provider classes own endpoint allowlists, authentication payloads and domain response mapping. Keep tenant credentials per request, never on the shared Guzzle client. Preserve existing auth and tenant boundaries.
- Licensing gate for external dependencies: this is a proprietary, closed-source application that is free for the public and may be funded by advertising. Do not introduce Transitous public API or clients, or any external API, SDK, library, dataset or adapter whose terms require publishing this application's source code, an open-source licence, non-commercial use, or otherwise forbid proprietary or advertising-funded applications. Before adding a provider, verify that its terms explicitly permit this use, the expected request volume and any intended storage or caching; preserve required attribution. Missing or unclear commercial permission means the dependency is forbidden until the provider gives written approval.
- TRAM transport goes directly from Astro to Java. PHP retains Auth and the administrator authorization bridge `/transport-admin/online-planners` only. Do not restore a public PHP transport gateway, adapters, SQL transport state/counters, GTFS/OSM, planning or tracking. TRAM is restricted to Auth/admin entrypoints before PDO; other tenants retain their modules. The user explicitly authorized removing legacy TRAM SQL tables/data on 9 Oct 2026. Use the reviewed, backed-up `scripts/cleanup-tram.php` for existing databases. Follow `src/Modules/Transport/AGENTS.md`. Removing PHP does not establish Java capability parity.
- Name classes/files by responsibility: `Repository`, `Service`, `Provider`, `Registry`, `Api`; use meaningful `Request`, `Response`, `Exception`, `Mapper`, `Codec`, `Reader` suffixes for other roles. Update all callers when renaming.
- Use additive, idempotent database migrations. Run integration tests against disposable test databases, never the application DB. TRAM does not require transport schemas; the explicit legacy cleanup is a destructive maintenance exception with a private backup.
- Every new list/search endpoint MUST use the standard JSON/MongoDB-compatible query contract in `docs/query-filter-contract.md`: a single `q` filter, `sort`, `projection`, `page`, `limit`, `QueryPolicy` allowlists, rows under `data`. Never add a bespoke search parameter or query grammar.
- Relevant checks: `bash scripts/test-http.sh`, `bash scripts/test-transport-auth.sh`, `bash scripts/test-tram-cleanup.sh`, PHP lint and `git diff --check`. See `src/Modules/Http/README.md` for transport limits, downloads and extension guidance.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `$graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- Use Graphify as the first navigation step for every codebase task. From the php-core root, run a targeted `graphify query "<question>"`; use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. Inspect only the relevant source files identified by the graph to verify its findings. Do not scan, list, or read all project files to learn the structure. If a query misses a symbol, refine the query using names from the code or use a narrowly scoped search.
- Keep the graph current throughout the task. If code may have changed since the last graph build, run `graphify update .` before relying on graph results. After every completed code change, including additions, deletions, and renames, run `graphify update .` before another graph query or the final response. Do not leave changed code with a stale graph.
- Dirty graphify-out/ files are expected after incremental updates; dirty graph files are not a reason to skip Graphify. Only skip Graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
