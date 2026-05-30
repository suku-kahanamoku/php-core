<?php
// Insert a Mermaid diagram for Auth flow into pages/flows.html (before #tab-auth)
$page = __DIR__ . '/../pages/flows.html';
if (!file_exists($page)) { echo "Missing flows.html\n"; exit(1); }
$html = file_get_contents($page);

$mermaid = <<<'M'
<div class="my-6 max-w-[900px] mx-auto">
  <script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
  <div class="bg-white p-4 rounded-lg border border-slate-200">
    <h3 class="text-sm font-bold mb-2">Auth flow (generated)</h3>
    <div class="mermaid">
sequenceDiagram
    participant C as Client
    participant A as API (/auth)
    participant S as AuthService
    participant T as UserTokenRepository
    C->>A: POST /auth/login {email,password}
    A->>S: login()
    S->>T: create(token)
    T-->>S: stored
    S-->>A: return token
    A-->>C: 200 {token}

    C->>A: POST /auth/logout (Authorization: Bearer)
    A->>S: logout()
    S->>T: delete(token)
    T-->>S: deleted
    S-->>A: 200 Logged out
    A-->>C: 200
    </div>
  </div>
</div>
M;

// insert before <div id="tab-auth"
$pos = strpos($html, '<div id="tab-auth"');
if ($pos === false) {
    echo "tab-auth not found; aborting\n";
    exit(1);
}
$new = substr($html, 0, $pos) . $mermaid . substr($html, $pos);
file_put_contents($page, $new);
echo "Inserted mermaid auth flow into pages/flows.html\n";
