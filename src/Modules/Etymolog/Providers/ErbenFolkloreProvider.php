<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Etymolog\EtymologDiscoveryRepository;
use App\Modules\Http\Contracts\HttpClient;

/**
 * Lidová vyprávění podle licence, objevovaná z jmen v databázi, s přesnými
 * zmínkami a kontrolovanými vazbami.
 *
 * Text se importuje pouze tehdy, když obsahuje přesnou zmínku existujícího
 * jména. Autor, název a veřejná doména se ověřují z infoboxu stránky; při
 * jakékoli změně se dávka zastaví místo toho, aby se do databáze dostal
 * neočekávaný obsah. Vazba text–jméno zůstává nerevidovaná.
 */
final class ErbenFolkloreProvider implements BatchProvider
{
    /** Název knihy na Wikisource, ze které se texty čtou. */
    private const BOOK = 'Prostonárodní české písně a říkadla';

    /**
     * @param  HttpClient $http  Sdílený HTTP klient.
     * @param  EtymologDiscoveryRepository $names Zdroj kandidátních jmen z databáze.
     * @return void
     */
    public function __construct(private readonly HttpClient $http, private readonly EtymologDiscoveryRepository $names) {}

    /**
     * Stáhne jednu dávku lidových textů podle kurzoru.
     *
     * @param  string     $language Musí být 'cs'.
     * @param  string     $kind     Musí být 'folklore'.
     * @param  string|null $cursor  Kurzor objevování jmen.
     * @param  int        $limit    Maximální počet kandidátů na dávku (1–4).
     * @return array{items:list<array<string, mixed>>, scanned:int, cursor:?string, complete:bool} Dávka textů.
     * @throws SyncException           Při chybě upstreamu nebo změně metadat stránky.
     */
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'cs' || $kind !== 'folklore' || $limit < 1 || $limit > 4) { throw new SyncException('invalid_provider_configuration'); }
        $result=(new WikisourceDiscoveryProvider($this->http,$this->names))->page(self::BOOK,$cursor);
        $items=[];
        if ($result['page']) {
            $page=$result['page']; $name=$result['name'];
            $title=substr($page['title'],strlen(self::BOOK)+1);
            $text=$this->extract($page['text']['*'],$title);
            if (WikisourceDiscoveryProvider::mentions($text['body'],$name['name'])) {
                $names=[['name'=>$name['name'],'kind'=>$name['kind']]];
                $url='https://cs.wikisource.org/w/index.php?oldid='.$page['revid'];
                $items[]=['external_id'=>'cs:'.$page['pageid'],'revision'=>(string)$page['revid'],
                    'type'=>'tradition','title'=>$title,'body'=>$text['body'],'region'=>null,'names'=>$names,
                    'source_url'=>$url,'source_title'=>self::BOOK.' – '.$title,'bibliography'=>$text['bibliography'],
                    'author'=>'Karel Jaromír Erben','license'=>WikisourceProvider::LICENSE,'license_url'=>WikisourceProvider::LICENSE_URL,
                    'payload'=>['page'=>$page['title'],'page_id'=>$page['pageid'],'revision'=>$page['revid'],'body'=>$text['body'],
                        'bibliography'=>$text['bibliography'],'author'=>'Karel Jaromír Erben','license'=>WikisourceProvider::LICENSE,
                        'suggested_names'=>$names,'association'=>'Exact DB name mention in the licensed body, pending editorial review.',
                        'changes'=>'Plain text without HTML, no generated narrative or inferred calendar date.']];
            }
        }
        return ['items'=>$items,'scanned'=>$result['scanned'],'cursor'=>$result['cursor'],'complete'=>$result['complete']];
    }

    /**
     * Převede HTML stránky na čistý text a bibliografický údaj.
     *
     * @param  string $html   HTML z Wikipedie API.
     * @param  string $title  Očekávaný název kapitoly (pro kontrolu infoboxu).
     * @return array{body: string, bibliography: string} Čistý text a údaj „Zdroj“.
     * @throws SyncException Při nečitelném značkování, změně metadat, jiné licenci
     *                       nebo textu mimo povolenou délku.
     */
    private function extract(string $html, string $title): array
    {
        $dom = new \DOMDocument(); $previous = libxml_use_internal_errors(true);
        try {$ok = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);}
        finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
        if (!$ok) {throw new SyncException('folklore_markup_changed');}
        $xp = new \DOMXPath($dom);
        $tables = $xp->query('//table[contains(concat(" ",normalize-space(@class)," ")," textinfo ")]');
        if ($tables->length !== 1) {throw new SyncException('folklore_metadata_changed');}
        $norm = static fn(string $s): string => trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        $meta = [];
        foreach ($xp->query('.//tr', $tables->item(0)) as $row) {
            $cells = $xp->query('./td', $row);
            if ($cells->length === 2) {$meta[$norm($cells->item(0)->textContent)] = $norm($cells->item(1)->textContent);}
        }
        if (($meta['Licence:'] ?? '') !== 'PD old 70') {throw new SyncException('story_license_not_allowed');}
        if (($meta['Autor:'] ?? '') !== 'zapsal Karel Jaromír Erben' || ($meta['Titulek:'] ?? '') !== $title || !str_contains($meta['Zdroj:'] ?? '', '1864')) {throw new SyncException('folklore_metadata_changed');}
        $roots = $xp->query('//div[contains(concat(" ",normalize-space(@class)," ")," mw-parser-output ")]');
        if ($roots->length !== 1) {throw new SyncException('folklore_markup_changed');}
        $root = $roots->item(0);
        foreach (iterator_to_array($xp->query('.//table|.//script|.//style|.//sup|.//figure|.//img|.//nav|.//div[contains(@class,"navbox")]|.//span[contains(@class,"mw-editsection")]', $root)) as $node) {$node->parentNode?->removeChild($node);}
        foreach (iterator_to_array($xp->query('.//br', $root)) as $br) {$br->parentNode->replaceChild($dom->createTextNode("\n"), $br);}
        $paragraphs = [];
        foreach ($xp->query('.//p', $root) as $paragraph) {
            $lines = array_values(array_filter(array_map($norm, explode("\n", $paragraph->textContent)), static fn($s) => $s !== ''));
            if ($lines !== []) {$paragraphs[] = implode("\n", $lines);}
        }
        $body = implode("\n\n", $paragraphs);
        if (strlen($body) < 30 || strlen($body) > 60000 || str_contains($body, "\0")) {throw new SyncException('folklore_body_out_of_bounds');}
        return ['body' => $body, 'bibliography' => $meta['Zdroj:']];
    }
}
