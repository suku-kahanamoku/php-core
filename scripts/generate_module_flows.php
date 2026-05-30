<?php
// Generate simple flows list for each module by parsing *Api.php files
$root = __DIR__ . '/../';
$pattern = $root . 'src/Modules/*/*Api.php';
$files = glob($pattern);
usort($files, function($a,$b){return strcmp($a,$b);});

$out = [];
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (!$content) continue;
    preg_match('/class\s+(\w+)\s*/', $content, $m);
    $class = $m[1] ?? basename($file);
    $module = preg_replace('/Api.php$/', '', basename($file));

    // find docblock lines like: "* GET /path — description. Verejne dostupne."
    preg_match_all('/\*\s+(GET|POST|PATCH|PUT|DELETE)\s+([^\s]+)\s*[—-]\s*(.*)/Ui', $content, $matches, PREG_SET_ORDER);
    if (!$matches) continue;

    $html = [];
    $html[] = "<div class=\"module-flow mb-6\">";
    $html[] = "<h3 class=\"text-sm font-bold mb-2\">Module: " . htmlspecialchars($module) . "</h3>";
    $html[] = "<div class=\"grid gap-2\">";
    foreach ($matches as $mm) {
        $method = $mm[1]; $path = $mm[2]; $desc = trim($mm[3]);
        $badge = '<span class="method-badge ';
        $badge .= ($method==='GET')? 'bg-green-600' : 'bg-blue-600';
        $badge .= ' text-white px-2 py-0.5 rounded text-xs font-bold">' . $method . '</span>';
        $html[] = '<div class="bg-white border border-slate-200 rounded-lg p-3 flex items-start gap-3"><div>' . $badge . '</div><div><div class="font-mono text-slate-700">' . htmlspecialchars($path) . '</div><div class="text-xs text-slate-500">' . htmlspecialchars($desc) . '</div></div></div>';
    }
    $html[] = "</div>";
    $html[] = "</div>";

    $out[] = implode("\n", $html);
}

$generated = "<!-- GENERATED MODULE FLOWS START -->\n" . implode("\n", $out) . "\n<!-- GENERATED MODULE FLOWS END -->\n";

$flowsPage = $root . 'pages/flows.html';
$html = file_get_contents($flowsPage);
$pos = strpos($html, '<div id="tab-auth"');
if ($pos === false) {
    // append at end
    $html .= "\n" . $generated;
} else {
    $html = substr($html, 0, $pos) . $generated . substr($html, $pos);
}
file_put_contents($flowsPage, $html);
echo "Generated module flows into pages/flows.html\n";
