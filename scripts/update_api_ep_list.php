<?php
// Replace inner HTML of <div id="ep-list"> in pages/api-reference.html
// Generate a compact HTML fragment from API.md (preserves layout/JS)

$base = __DIR__ . '/../';
$mdFile = $base . 'API.md';
$pageFile = $base . 'pages/api-reference.html';

if (!file_exists($mdFile) || !file_exists($pageFile)) {
    echo "Missing files\n";
    exit(1);
}

$md = file_get_contents($mdFile);
$lines = preg_split('/\r?\n/', $md);

$frag = [];
$inCode = false; $codeBuf = [];
foreach ($lines as $line) {
    if (preg_match('/^```/', $line)) {
        if ($inCode) {
            $frag[] = '<pre class="bg-slate-900 text-green-400 rounded-lg p-3 text-xs leading-relaxed"><code>' . htmlspecialchars(implode("\n", $codeBuf)) . '</code></pre>';
            $codeBuf = [];
            $inCode = false;
        } else {
            $inCode = true;
        }
        continue;
    }
    if ($inCode) { $codeBuf[] = $line; continue; }

    if (preg_match('/^###\s+(.*)/', $line, $m)) { $frag[] = '<h3 class="text-sm font-bold mt-4">' . htmlspecialchars($m[1]) . '</h3>'; continue; }
    if (preg_match('/^##\s+(.*)/', $line, $m)) { $frag[] = '<h2 class="text-lg font-bold mt-6">' . htmlspecialchars($m[1]) . '</h2>'; continue; }
    if (preg_match('/^#\s+(.*)/', $line, $m)) { $frag[] = '<h1 class="text-2xl font-extrabold">' . htmlspecialchars($m[1]) . '</h1>'; continue; }
    if (trim($line) === '') { $frag[] = '<p></p>'; continue; }
    // simple inline code
    $escaped = htmlspecialchars($line);
    $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped);
    $frag[] = '<p>' . $escaped . '</p>';
}

$newInner = implode("\n", $frag);

$html = file_get_contents($pageFile);
$start = strpos($html, '<div id="ep-list"');
if ($start === false) { echo "Cannot find div#ep-list\n"; exit(1); }
$start = strpos($html, '>', $start) + 1; // position after opening tag

$pos = $start;
$depth = 1;
$len = strlen($html);
// find matching closing div by counting nested divs
while ($pos < $len && $depth > 0) {
    $nextOpen = strpos($html, '<div', $pos);
    $nextClose = strpos($html, '</div>', $pos);
    if ($nextClose === false) break;
    if ($nextOpen !== false && $nextOpen < $nextClose) {
        $depth++;
        $pos = $nextOpen + 4;
    } else {
        $depth--;
        $pos = $nextClose + 6;
    }
}

if ($depth !== 0) { echo "Failed to locate matching closing tag for div#ep-list\n"; exit(1); }
$end = $pos - 6; // position of closing </div>

$newHtml = substr($html, 0, $start) . "\n" . $newInner . "\n" . substr($html, $end);
file_put_contents($pageFile, $newHtml);
echo "Updated pages/api-reference.html (inner ep-list)\n";
