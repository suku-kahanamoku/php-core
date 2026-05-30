<?php
// Safely update inner HTML of <div id="ep-list"> in pages/api-reference.html
$root = __DIR__ . '/../';
$pattern = $root . 'src/Modules/*/*Api.php';
$files = glob($pattern);
usort($files, function($a,$b){return strcmp($a,$b);});

$fragments = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (!$content) continue;
    // Module name from folder
    $parts = explode('/', str_replace('\\','/',$file));
    $module = strtolower($parts[count($parts)-2]);

    // collect doc lines
    preg_match_all('/\*\s+(GET|POST|PATCH|PUT|DELETE)\s+([^\s]+)\s*[—-]\s*(.*)/Ui', $content, $matches, PREG_SET_ORDER);
    if (!$matches) continue;

    $modHtml = [];
    $modHtml[] = "<div class=\"module-section mb-6\" data-module=\"" . htmlspecialchars($module) . "\">";
    $modHtml[] = "  <h2 class=\"text-sm font-bold text-slate-700 border-b border-slate-200 pb-2 mb-2\">";
    $modHtml[] = "    " . htmlspecialchars(ucfirst($module)) . " &nbsp;<span class=\"text-slate-400 font-normal text-xs\">/api/" . htmlspecialchars($module) . "/…</span>";
    $modHtml[] = "  </h2>";

    foreach ($matches as $mm) {
        $method = strtoupper($mm[1]);
        $path = $mm[2];
        $desc = trim($mm[3]);

        // determine auth badge
        $authBadge = 'public';
        if (stripos($desc, 'Vyzaduje') !== false || stripos($desc, 'vyžaduje') !== false || stripos($desc, 'auth') !== false) {
            $authBadge = 'auth';
        }
        if (stripos($desc, 'admin') !== false) {
            $authBadge = 'admin';
        }

        $methodClass = 'bg-blue-600';
        if ($method === 'GET') $methodClass = 'bg-green-600';
        if ($method === 'PATCH') $methodClass = 'bg-amber-500';
        if ($method === 'PUT') $methodClass = 'bg-orange-600';
        if ($method === 'DELETE') $methodClass = 'bg-red-600';

        $badgeHtml = "<span class=\"method-badge $methodClass text-white px-2 py-0.5 rounded text-xs font-bold\">$method</span>";

        $searchAttr = htmlspecialchars(strtolower($method . ' ' . $module . ' ' . $path . ' ' . $desc));

        $modHtml[] = "  <div class=\"ep-item bg-white rounded-xl border border-slate-200 mb-2 overflow-hidden\" data-module=\"" . htmlspecialchars($module) . "\" data-search=\"$searchAttr\">";
        $modHtml[] = "    <div class=\"ep-row flex items-center gap-3 px-4 py-3\" onclick=\"toggleDetail(this)\">";
        $modHtml[] = "      $badgeHtml";
        $modHtml[] = "      <code class=\"text-sm font-mono text-slate-700\">" . htmlspecialchars($path) . "</code>";
        if ($authBadge === 'public') {
            $modHtml[] = "      <span class=\"bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded font-bold ml-1\">&#128275; public</span>";
        } elseif ($authBadge === 'auth') {
            $modHtml[] = "      <span class=\"bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded font-bold ml-1\">&#128274; auth</span>";
        } else {
            $modHtml[] = "      <span class=\"bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded font-bold ml-1\">&#128737; admin</span>";
        }
        $modHtml[] = "      <span class=\"text-xs text-slate-500 ml-2\">" . htmlspecialchars($desc) . "</span>";
        $modHtml[] = "      <span class=\"ml-auto text-slate-400 text-xs\">&#9660;</span>";
        $modHtml[] = "    </div>";
        $modHtml[] = "    <div class=\"ep-detail border-t border-slate-100 px-4 py-4 grid md:grid-cols-3 gap-4\">";
        $modHtml[] = "      <div><div class=\"text-xs font-bold text-slate-500 mb-1\">Request</div><pre class=\"bg-slate-900 text-green-400 rounded-lg p-3 text-xs leading-relaxed\">// see implementation</pre></div>";
        $modHtml[] = "      <div><div class=\"text-xs font-bold text-slate-500 mb-1\">Response</div><pre class=\"bg-slate-900 text-green-400 rounded-lg p-3 text-xs leading-relaxed\">{\n  \"success\": true,\n  \"data\": null\n}</pre></div>";
        $modHtml[] = "      <div><div class=\"text-xs font-bold text-slate-500 mb-1\">curl</div><pre class=\"bg-slate-900 text-green-400 rounded-lg p-3 text-xs leading-relaxed\"><code>curl $BASE" . htmlspecialchars($path) . "</code></pre></div>";
        $modHtml[] = "    </div>";
        $modHtml[] = "  </div>";
    }

    $modHtml[] = "</div>";
    $fragments[] = implode("\n", $modHtml);
}

$generated = "<!-- GENERATED API EP-LIST START -->\n" . implode("\n", $fragments) . "\n<!-- GENERATED API EP-LIST END -->\n";

$page = $root . 'pages/api-reference.html';
$bak = $page . '.bak.' . time();
copy($page, $bak);
echo "Backup created: $bak\n";

$html = file_get_contents($page);
$start = strpos($html, '<div id="ep-list">');
if ($start === false) {
    echo "div#ep-list not found\n";
    exit(1);
}
$startEnd = strpos($html, '>', $start) + 1;
$end = strpos($html, '<!-- ═══════════════════════════════', $startEnd);
if ($end === false) {
    // fallback: find closing </div> of ep-list by searching for '</div>' after startEnd and assume it's the container end
    $end = strpos($html, '</div>', $startEnd);
}

if ($end === false) {
    echo "Couldn't locate end of ep-list container\n";
    exit(1);
}

$newHtml = substr($html, 0, $startEnd) . "\n" . $generated . "\n";

// find the position to continue after the original ep-list closing div
$rest = substr($html, $end);
$newHtml .= $rest;

file_put_contents($page, $newHtml);
echo "Updated pages/api-reference.html (backup at $bak)\n";
