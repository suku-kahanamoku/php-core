<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\{EtymologDiscoveryRepository, NameNormalizer, SyncException};
use App\Modules\Http\Contracts\HttpClient;

/**
 * Objevuje důkazy z Wikipedie pro VŠECHNA jména okurku; v dávce až tři články,
 * žádné vymyšlené vazby.
 *
 * Článek musí na stránku o jméně (přes infobox nebo kategorii, s výslovným
 * rozlišením rodných jmen a příjmení) a každý úsek se zpracuje jen tehdy, když
 * jeho nadpis odpovídá známému typu (etymologie, legenda, pranostika, tradice,
 * mytologie, historie). Nic se nedoplňuje ani nepřekládá — zbožné příběhy zůstávají
 * tradicí, ne potvrzenou historií, a zveřejnění nadále čeká na kontrolu člověka.
 */
final class WikipediaNamesProvider implements BatchProvider
{
    /** Adresa textu licence CC BY-SA 4.0. */
    public const LICENSE_URL = 'https://creativecommons.org/licenses/by-sa/4.0/';

    /**
     * @param  HttpClient $http  Sdílený HTTP klient.
     * @param  EtymologDiscoveryRepository $names Zdroj kandidátních jmen z databáze.
     * @return void
     */
    public function __construct(private readonly HttpClient $http, private readonly EtymologDiscoveryRepository $names) {}

    /**
     * Provede dotaz do Wikipedie API včetně ochrany proti zpoždění replik.
     *
     * @param  array<string, mixed> $params Parametry akce.
     * @return array<string, mixed>          Dekódovaná odpověď API.
     * @throws SyncException                Při chybě upstreamu nebo neplatné odpovědi.
     */
    private function api(array $params): array
    {
        return ProviderHttp::json($this->http, 'https://cs.wikipedia.org/w/api.php?'.http_build_query($params + ['format'=>'json','maxlag'=>5]));
    }

    /**
     * Stáhne jednu dávku důkazů podle kurzoru.
     *
     * @param  string     $language Musí být 'cs'.
     * @param  string     $kind     'etymologies' pro původ jména, 'culture' pro navazující články.
     * @param  string|null $cursor  Kurzor s pozicí v katalogu jmen, případně s rozpracovaným článkem.
     * @param  int        $limit    Maximálně 3 články na dávku.
     * @return array{items:list<array<string, mixed>>, scanned:int, cursor:?string, complete:bool} Dávka důkazů.
     * @throws SyncException           'invalid_provider_configuration', 'invalid_provider_cursor',
     *                                'upstream_license_changed', 'invalid_dossier_revision',
     *                                'invalid_dossier_response', 'dossier_markup_changed',
     *                                'culture_body_out_of_bounds' nebo chyba upstreamu.
     */
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'cs' || !in_array($kind,['etymologies','culture'],true) || $limit < 1 || $limit > 3) { throw new SyncException('invalid_provider_configuration'); }
        $items = []; $scanned = 0; $complete = false;
        for ($i = 0; $i < $limit; ++$i) {
            $part = $this->batchOne($kind, $cursor);
            array_push($items, ...$part['items']);
            $scanned += $part['scanned'];
            $complete = $part['complete'];
            if ($complete) { $cursor = null; break; }
            if ($part['cursor'] === $cursor) { throw new SyncException('provider_cursor_stalled'); }
            $cursor = $part['cursor'];
        }
        return ['items' => $items, 'scanned' => $scanned, 'cursor' => $cursor, 'complete' => $complete];
    }

    /** One source page and its cursor. */
    private function batchOne(string $kind, ?string $cursor): array
    {
        if ($cursor !== null && preg_match('/^[0-9]+$/D',$cursor)) { $cursor=null; } // Retired fixed-catalog offset.
        $state = $cursor === null ? ['after'=>0] : json_decode($cursor,true);
        if (!is_array($state) || !is_int($state['after'] ?? null) || $state['after'] < 0) { throw new SyncException('invalid_provider_cursor'); }
        $related = array_key_exists('name_id',$state);
        if ($related && (!is_int($state['name_id']) || $state['name_id'] <= $state['after'] || !is_int($state['revision'] ?? null) || $state['revision'] < 1 || !is_int($state['page_id'] ?? null) || $state['page_id'] < 1 || !is_int($state['related'] ?? null) || $state['related'] < 0)) { throw new SyncException('invalid_provider_cursor'); }
        $name = $related ? $this->names->findIncomplete($state['name_id'],'') : $this->names->nextIncomplete('',$state['after']);
        if (!$name) {
            return $related ? $this->result([], ['after'=>$state['name_id']], $kind) : ['items'=>[],'scanned'=>0,'cursor'=>null,'complete'=>true];
        }
        if (preg_match('/[|:#\x00-\x1f]/u', $name['name'])) { return $this->result([], ['after'=>(int)$name['id']], $kind); }
        $rights=null;
        if ($related) {
            $parent=$this->page(['oldid'=>$state['revision']]);
            if ($parent['pageid'] !== $state['page_id'] || $parent['revid'] !== $state['revision'] || !$this->isName($parent,$name)) { throw new SyncException('invalid_dossier_revision'); }
            $parentData=$this->extract($parent);
            $titles=$parentData['related'];
            if (!isset($titles[$state['related']])) { throw new SyncException('invalid_provider_cursor'); }
            $page=$this->discover([$titles[$state['related']]],null,$rights);
            $items=[];
            if ($page && $this->isCultural($page)) {
                $items=$this->items($page,$this->extract($page),$name,$rights,true, ['page_id'=>$parent['pageid'],'revision'=>$parent['revid'],'url'=>'https://cs.wikipedia.org/w/index.php?oldid='.$parent['revid']]);
            }
            ++$state['related'];
            return $this->result($items,isset($titles[$state['related']]) ? $state : ['after'=>(int)$name['id']],$kind);
        }
        $title=NameNormalizer::display($name['name']);
        // Explicit suffixes resolve a name/surname collision; untyped people or places are rejected.
        $titles=$name['kind']==='given' ? [$title.' (jméno)',$title.' (rodné jméno)',$title] : [$title.' (příjmení)',$title];
        $page=$this->discover($titles,$name,$rights);
        if (!$page) { return $this->result([],['after'=>(int)$name['id']],$kind); }
        $data=$this->extract($page);
        $items=$this->items($page,$data,$name,$rights,false);
        $next=$data['related'] && $kind==='culture' && $name['kind']==='given' ? ['after'=>$state['after'],'name_id'=>(int)$name['id'],'page_id'=>$page['pageid'],'revision'=>$page['revid'],'related'=>0] : ['after'=>(int)$name['id']];
        return $this->result($items,$next,$kind);
    }

    /**
     * Sestaví výsledek dávky a rozhodne, zda je průchod dokončený.
     *
     * @param  list<array<string, mixed>> $items Nalezené položky před rozdělením podle druhu.
     * @param  array<string, mixed>       $state Další stav kurzoru.
     * @param  string                     $kind  'etymologies' nebo 'culture'.
     * @return array{items:list<array<string, mixed>>, scanned:int, cursor:?string, complete:bool} Dávka odpovídající druhu.
     */
    private function result(array $items,array $state,string $kind): array
    {
        $items=array_values(array_filter($items, static fn($item)=>$kind==='etymologies' ? in_array($item['entry']['type'],['etymology','history'],true) : !in_array($item['entry']['type'],['etymology','history'],true)));
        $complete=!isset($state['name_id']) && $this->names->nextIncomplete('',$state['after'])===null;
        return ['items'=>$items,'scanned'=>1,'cursor'=>$complete ? null : json_encode($state,JSON_THROW_ON_ERROR),'complete'=>$complete];
    }

    /**
     * Najde stránku odpovídající jednomu z kandidátních názvů.
     *
     * @param  list<string>          $titles Kandidátní názvy v pořadí priority.
     * @param  array<string, mixed>|null $name Jméno z databáze, nebo null při hledání kulturního článku.
     * @param  array<string, mixed>|null $rights Práva vrácená ve stejném požadavku jako stránky.
     * @return array<string, mixed>|null    Stránka z `action=parse`, nebo null pokud žádná nevyhovuje.
     * @throws SyncException                'invalid_discovery_response', 'dossier_metadata_incomplete',
     *                                     'invalid_dossier_revision' nebo chyba upstreamu.
     */
    private function discover(array $titles,?array $name,?array &$rights): ?array
    {
        $response=$this->api(['action'=>'query','titles'=>implode('|',$titles),'redirects'=>1,'prop'=>'categories|templates','cllimit'=>500,'tllimit'=>500,'meta'=>'siteinfo','siprop'=>'rightsinfo']);
        $license=self::LICENSE_URL;
        if (!in_array($response['query']['rightsinfo']['url'] ?? '',[$license,$license.'deed.cs',$license.'deed.en'],true)) { throw new SyncException('upstream_license_changed'); }
        $rights=['query'=>['rightsinfo'=>$response['query']['rightsinfo']]];
        if (!is_array($response['query']['pages'] ?? null)) { throw new SyncException('invalid_discovery_response'); }
        if (isset($response['continue'])) { throw new SyncException('dossier_metadata_incomplete'); }
        $pages=array_values($response['query']['pages']);
        usort($pages,static fn($a,$b)=>((array_search($a['title'] ?? '',$titles,true) === false) ? PHP_INT_MAX : array_search($a['title'],$titles,true))<=>((array_search($b['title'] ?? '',$titles,true) === false) ? PHP_INT_MAX : array_search($b['title'],$titles,true)));
        foreach ($pages as $candidate) {
            if (isset($candidate['missing']) || isset($candidate['invalid'])) { continue; }
            if (!is_int($candidate['pageid'] ?? null) || $candidate['pageid']<1 || !is_string($candidate['title'] ?? null)) { throw new SyncException('invalid_discovery_response'); }
            if ($name ? !$this->isName($candidate,$name) : !$this->isCultural($candidate)) { continue; }
            $page=$this->page(['pageid'=>$candidate['pageid']]);
            if ($page['pageid'] !== $candidate['pageid'] || ($name ? !$this->isName($page,$name) : !$this->isCultural($page))) { throw new SyncException('invalid_dossier_revision'); }
            return $page;
        }
        return null;
    }

    /**
     * Načte stránku s textem, revizí, kategoriemi a šablonami.
     *
     * @param  array<string, mixed> $params Parametry (`pageid` nebo `oldid`).
     * @return array<string, mixed>          Obsah stránky.
     * @throws SyncException                'invalid_dossier_response' nebo chyba upstreamu.
     */
    private function page(array $params): array
    {
        $response=$this->api(['action'=>'parse','prop'=>'text|revid|categories|templates']+$params);
        $page=$response['parse'] ?? [];
        if (!is_int($page['pageid'] ?? null) || $page['pageid']<1 || !is_int($page['revid'] ?? null) || $page['revid']<1 || !is_string($page['title'] ?? null) || !is_string($page['text']['*'] ?? null)) { throw new SyncException('invalid_dossier_response'); }
        return $page;
    }

    /**
     * Vrátí názvy kategorií stránky bez předpony a s mezerami místo podtržítka.
     *
     * @param  array<string, mixed> $page Stránka z `action=parse`.
     * @return list<string>               Názvy kategorií.
     */
    private function categories(array $page): array
    {
        return array_map(static fn($c)=>str_replace('_',' ',preg_replace('/^Kategorie:/u','',$c['title'] ?? $c['*'] ?? '')),$page['categories'] ?? []);
    }

    /**
     * Ověří, že stránka skutečně pojednává o daném jméně, ne o osobě nebo místě.
     *
     * @param  array<string, mixed> $page Stránka z `action=parse`.
     * @param  array<string, mixed> $name Jméno z databáze (`name`, `kind`).
     * @return bool                     true, pokud stránka odpovídá jménu.
     */
    private function isName(array $page,array $name): bool
    {
        $title=preg_replace('/ \((?:rodné jméno|jméno|příjmení)\)$/u','',$page['title'] ?? '');
        if (mb_strtolower($title,'UTF-8') !== mb_strtolower(trim($name['name']),'UTF-8')) { return false; }
        $templates=array_map(static fn($t)=>str_replace('_',' ',$t['title'] ?? $t['*'] ?? ''),$page['templates'] ?? []);
        if (in_array($name['kind']==='given' ? 'Šablona:Infobox - jméno' : 'Šablona:Infobox - příjmení',$templates,true)) { return true; }
        foreach ($this->categories($page) as $category) {
            if ($name['kind']==='surname' ? preg_match('/^(?:[\p{L}]+ )?příjmení$/uiD',$category) : preg_match('/^(?:Mužská|Ženská|Rodná|Obourodá) jména(?: |$)/u',$category)) { return true; }
        }
        return false;
    }

    /**
     * Ověří, že stránka patří mezi kulturní (svatí, bohové, mytologické postavy).
     *
     * @param  array<string, mixed> $page Stránka z `action=parse`.
     * @return bool                     true, pokud má stránka sjednocující kulturní kategorii.
     */
    private function isCultural(array $page): bool
    {
        foreach ($this->categories($page) as $category) {
            if (preg_match('/(?:svatí|světci|světice|blahoslavení|blahoslavené|bohové|bohyně|božstva|mytologické postavy)/ui',$category)) { return true; }
        }
        return false;
    }

    /**
     * Rozdělí článek na úseky podle nadpisů a vytáhne z nich čistý text.
     *
     * @param  array<string, mixed> $page Stránka z `action=parse`.
     * @return array{chunks:list<array<string, mixed>>, related:list<string>}
     *         Úseky (`heading`, `anchor`, `paragraphs`) a názvy navazujících kulturních článků.
     * @throws SyncException 'invalid_dossier_html' nebo 'dossier_markup_changed'.
     */
    private function extract(array $page): array
    {
        $dom=new \DOMDocument(); $previous=libxml_use_internal_errors(true);
        try { $loaded=$dom->loadHTML('<?xml encoding="UTF-8">'.$page['text']['*'],LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING); }
        finally { libxml_clear_errors();libxml_use_internal_errors($previous); }
        if (!$loaded) { throw new SyncException('invalid_dossier_html'); }
        $xp=new \DOMXPath($dom);
        $roots=$xp->query('//div[contains(concat(" ",normalize-space(@class)," ")," mw-parser-output ")]');
        if ($roots->length!==1) { throw new SyncException('dossier_markup_changed'); }
        $root=$roots->item(0);
        foreach (iterator_to_array($xp->query('.//script|.//style|.//table|.//sup|.//figure|.//img|.//nav|.//div[contains(@class,"thumb") or contains(@class,"navbox") or contains(@class,"hatnote") or contains(@class,"reflist")]|.//span[contains(@class,"mw-editsection")]', $root)) as $node) { $node->parentNode?->removeChild($node); }
        foreach (iterator_to_array($xp->query('.//br',$root)) as $br) { $br->parentNode->replaceChild($dom->createTextNode("\n"),$br); }
        $chunks=[['heading'=>'','anchor'=>'','paragraphs'=>[]]]; $index=0; $related=[];
        foreach ($xp->query('.//h2|.//h3|.//h4|.//h5|.//h6|.//p[not(ancestor::li)]|.//li[not(ancestor::li)]',$root) as $node) {
            $text=trim(preg_replace('/[^\S\n]+/u',' ',$node->textContent));
            if (preg_match('/^h[2-6]$/D',$node->nodeName)) {
                $anchor=$node->getAttribute('id') ?: ($xp->query('.//*[@id]',$node)->item(0)?->getAttribute('id') ?? '');
                $chunks[]=['heading'=>$text,'anchor'=>$anchor,'paragraphs'=>[]];++$index;continue;
            }
            if ($text!=='') { $chunks[$index]['paragraphs'][]=$text; }
            foreach ($xp->query('.//a[@href]',$node) as $link) {
                $title=$link->getAttribute('title');
                $href=$link->getAttribute('href');
                $explicit=(bool)preg_match('/^(?:Svat|Světc|Patron|Mytolog)/ui',$chunks[$index]['heading']) || (bool)preg_match('/^Svat[áýí] | \(mytologie\)$/u',$title);
                if (!$explicit || !str_starts_with($href,'/wiki/') || $title==='' || str_contains($title,':') || str_contains($title,'|') || str_contains($href,'#') || mb_strlen($title)>255) { continue; }
                $related[$title]=$title;
            }
        }
        return ['chunks'=>$chunks,'related'=>array_values($related)];
    }

    /**
     * Převede úseky článku na importní položky podle typu nadpisu.
     *
     * Odkazy na navazující články se sbírají jen v sekcích o svatých, patronech
     * a mytologii, aby se nerozšiřovala o kódované tradice a vymyšlené vazby.
     *
     * @param  array<string, mixed>       $page        Stránka z `action=parse`.
     * @param  array<string, mixed>       $data        Výstup `extract()`.
     * @param  array<string, mixed>       $name        Jméno z databáze.
     * @param  array<string, mixed>       $rights      Informace o licenci Wikipedie.
     * @param  bool                       $related     true, jde-li o navazující kulturní článek.
     * @param  array<string, mixed>       $association Popis vazby na výchozí článek.
     * @return list<array<string, mixed>>              Importní položky (nepublikované, `unverified`).
     * @throws SyncException                            'culture_body_out_of_bounds' nebo 'dossier_markup_changed'.
     */
    private function items(array $page,array $data,array $name,array $rights,bool $related,array $association=[]): array
    {
        $items=[];
        $mythological=$related && (bool)preg_grep('/(?:bohové|bohyně|božstva|mytologické postavy)/ui',$this->categories($page));
        foreach ($data['chunks'] as $chunk) {
            $heading=$chunk['heading'];$lower=mb_strtolower($heading,'UTF-8');
            $type=match(true) {
                $mythological && $heading===''=>'mythology',
                !$related && (str_starts_with($lower,'etymolog') || preg_match('/^původ(?: jména| příjmení)?$/uD',$lower))=>'etymology',
                preg_match('/^(?:legenda|legendy|pověst|pověsti)(?: |$)/u',$lower)===1=>'legend',
                str_starts_with($lower,'pranostik')=>'proverb',
                preg_match('/^(?:uctívání|úcta|patron|tradice|zvyky|svátek)(?:[\p{L}]*)(?: |$)/u',$lower)===1=>'tradition',
                str_starts_with($lower,'mytolog')=>'mythology',
                !$related && preg_match('/^(?:historie|dějiny)(?: jména| příjmení)?$/uD',$lower)===1=>'history',
                default=>null,
            };
            $paragraphs=$chunk['paragraphs'];
            if (!$related && $heading==='') {
                $paragraphs=array_values(array_filter($paragraphs,static fn($p)=>preg_match('/(?:pochází|odvozen|vznikl|původ|znamená|významem|ve významu|označoval|zdrobnělin)/ui',$p)));
                $type=$paragraphs ? 'etymology' : null;
            }
            if (!$type || !$paragraphs) { continue; }
            $body=implode("\n\n",$paragraphs);
            if (strlen($body)<30) { continue; }
            if (strlen($body)>60000 || str_contains($body,"\0")) { throw new SyncException('culture_body_out_of_bounds'); }
            $label=$heading ?: 'Úvod – původ jména';
            $url='https://cs.wikipedia.org/w/index.php?oldid='.$page['revid'].($chunk['anchor']!=='' ? '#'.rawurlencode($chunk['anchor']) : '');
            $notes='Převzato z české Wikipedie; výběr odstavců, odstranění HTML a značek referencí, bez generování a překladu. Náboženské příběhy jsou tradice či legendy, nikoli potvrzená historie.';
            $items[]=['external_id'=>'cs:auto:'.$name['kind'].':'.$name['id'].':'.$page['pageid'].':'.hash('sha256',$chunk['anchor'] ?: $heading),'revision'=>(string)$page['revid'],
                'name'=>$name['name'],'kind'=>$name['kind'],'language'=>'cs','country_code'=>null,
                'source_key'=>'cs:'.$page['pageid'],'source_title'=>'Wikipedie: '.$page['title'],'source_url'=>$url,
                'license'=>'CC-BY-SA-4.0','license_url'=>WikipediaNamesProvider::LICENSE_URL,
                'attribution'=>'Přispěvatelé české Wikipedie; https://cs.wikipedia.org/w/index.php?title='.rawurlencode($page['title']).'&action=history; výběr odstavců a převod do prostého textu. CC BY-SA 4.0.',
                'locator'=>$page['title'].' / '.$label.'; revize '.$page['revid'],'notes'=>$notes,
                'entry'=>['type'=>$type,'title'=>$page['title'].' – '.$label,'body'=>$body,'language'=>'cs','source_url'=>$url,'certainty'=>'unverified','published'=>0],
                'payload'=>['page_id'=>$page['pageid'],'revision'=>$page['revid'],'section'=>$label,'section_anchor'=>$chunk['anchor'],'body'=>$body,'name_id'=>$name['id'],'kind'=>$name['kind'],'association'=>$association,'license_evidence'=>$rights['query']['rightsinfo'],'changes'=>$notes]];
        }
        return $items;
    }
}
