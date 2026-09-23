#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\FannCatalog\FannCatalogParser;

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

$parser = new FannCatalogParser();
$failed = 0;
$assert = static function (string $label, bool $condition) use (&$failed): void {
    fwrite(STDOUT, ($condition ? '✓ ' : '✗ ') . $label . PHP_EOL);
    $failed += $condition ? 0 : 1;
};

$catalog = <<<'HTML'
<ul class="nav_produkty">
  <li><h5><a href="https://www.fann.cz/produkty/plet">Pleť</a></h5></li>
  <li><h5><a href="https://www.fann.cz/produkty/vune">Vůně</a></h5></li>
</ul>
<section id="produkty">
  <a class="produkt" href="https://www.fann.cz/produkty/test/10/20">Test</a>
  <a class="produkt" href="https://example.com/produkt/1">Cizí</a>
</section>
<ul class="pagination"><li><a href="https://www.fann.cz/produkty/plet?page=2" rel="next">Další</a></li></ul>
HTML;
$categories = $parser->parseCategories($catalog);
$assert('načte hlavní kategorie v UTF-8', count($categories) === 2 && $categories[0]['name'] === 'Pleť');
$assert('načte jen validní URL produktu FAnn', $parser->parseProductUrls($catalog) === ['https://www.fann.cz/produkty/test/10/20']);
$assert('pokračuje podle odkazu rel=next', $parser->parseNextPageUrl($catalog) === 'https://www.fann.cz/produkty/plet?page=2');
$assert('bez rel=next správně skončí', $parser->parseNextPageUrl('<html></html>') === null);

$detail = <<<'HTML'
<section class="gallery"><i class="ikona female" title="Pro ženy"></i></section>
<section class="variant"><div class="selected">parfémová voda 30 ml</div></section>
<section><div id="slozeni"><div><h3>Hlava</h3><p>bergamot</p></div></div></section>
<div itemprop="ingredients"><p>AQUA, PARFUM</p></div>
<script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":"Test","brand":{"@type":"Brand","name":"Značka"},"description":"Český popis","sku":"1234567890123","gtin13":"1234567890123","image":["https://static.fann.cz/test.jpg"],"offers":{"price":999,"priceCurrency":"CZK","availability":"https://schema.org/InStock"}}</script>
HTML;
$product = $parser->parseProduct($detail, 'https://www.fann.cz/produkty/test/10/20');
$assert('přečte EAN, cenu a dostupnost z JSON-LD', $product['sku'] === 'FANN-1234567890123' && $product['price'] === 999.0 && $product['stock_quantity'] === 1);
$assert('uloží variantu a explicitní atributy', $product['variant'] === 'parfémová voda 30 ml' && $product['data']['composition']['Hlava'] === 'bergamot');
$assert('nevytvoří duplicitní značku v názvu', $product['name'] === 'Značka Test');

exit($failed > 0 ? 1 : 0);
