<?php
$dir = __DIR__ . '/../pages/';
$files = glob($dir . '*.html');
$links = [];
foreach ($files as $f) {
    $html = file_get_contents($f);
    preg_match_all('/href="([^"]+)"/i', $html, $m);
    foreach ($m[1] as $link) {
        $links[] = [$f, $link];
    }
}

$bad = [];
foreach ($links as [$from, $link]) {
    // ignore external links
    if (preg_match('#^https?://#', $link)) continue;
    // ignore anchors
    if (strpos($link, '#') === 0) continue;
    // ignore mailto
    if (strpos($link, 'mailto:') === 0) continue;
    // relative path
    $target = realpath(dirname($from) . '/' . $link);
    if ($target === false || !file_exists($target)) {
        $bad[] = [$from, $link];
    }
}

if (empty($bad)) {
    echo "No broken links found in pages/.\n";
    exit(0);
}

echo "Broken links:\n";
foreach ($bad as [$from, $link]) {
    echo basename($from) . " -> " . $link . "\n";
}
exit(1);
