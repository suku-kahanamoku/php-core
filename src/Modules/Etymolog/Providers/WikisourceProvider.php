<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\Contracts\BatchProvider;
use App\Modules\Etymolog\SyncException;
use App\Modules\Etymolog\EtymologDiscoveryRepository;
use App\Modules\Http\Contracts\HttpClient;

/**
 * Vyhledávání v licencované sbírce řízené databází; žádná ručně vybraná jména
 * ani kapitoly.
 *
 * Importuje se jen text, který obsahuje přesnou zmínku existujícího jména.
 * Autor, veřejná doména a název kapitoly se ověřují z infoboxu; při změně se
 * dávka zastaví. Vazba text–jméno zůstává nerevidovaná a zveřejnění čeká na
 * kontrolu člověka.
 */
final class WikisourceProvider implements BatchProvider
{
    /** Kód licence veřejného domény (PD-old-70). */
    public const LICENSE = 'PD-old-70';

    /** Adresa textu licence. */
    public const LICENSE_URL = 'https://cs.wikisource.org/wiki/Wikizdroje:Licence#PD_old_70';

    /** Autor sbírky. */
    public const AUTHOR = 'Alois Jirásek';

    /** Název knihy na Wikisource, ze které se texty čtou. */
    private const BOOK = 'Staré pověsti české (1959)';

    /**
     * @param  HttpClient $http  Sdílený HTTP klient.
     * @param  EtymologDiscoveryRepository $names Zdroj kandidátních jmen z databáze.
     * @return void
     */
    public function __construct(private readonly HttpClient $http, private readonly EtymologDiscoveryRepository $names) {}

    /**
     * Stáhne jednu dávku příběhů podle kurzoru.
     *
     * @param  string     $language Musí být 'cs'.
     * @param  string     $kind     Musí být 'stories'.
     * @param  string|null $cursor  Kurzor objevování jmen.
     * @param  int        $limit    Maximální počet kandidátů na dávku (1–4).
     * @return array{items:list<array<string, mixed>>, scanned:int, cursor:?string, complete:bool} Dávka příběhů.
     * @throws SyncException           Při chybě upstreamu nebo změně metadat stránky.
     */
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {
        if ($language !== 'cs' || $kind !== 'stories' || $limit < 1 || $limit > 4) { throw new SyncException('invalid_provider_configuration'); }
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
                    'type'=>'legend','title'=>$title,'body'=>$text['body'],'region'=>null,'names'=>$names,
                    'source_url'=>$url,'source_title'=>self::BOOK.' – '.$title,'bibliography'=>$text['bibliography'],
                    'author'=>'Alois Jirásek','license'=>WikisourceProvider::LICENSE,'license_url'=>WikisourceProvider::LICENSE_URL,
                    'payload'=>['page'=>$page['title'],'page_id'=>$page['pageid'],'revision'=>$page['revid'],'body'=>$text['body'],
                        'bibliography'=>$text['bibliography'],'author'=>'Alois Jirásek','license'=>WikisourceProvider::LICENSE,
                        'suggested_names'=>$names,'association'=>'Exact DB name mention in the licensed body, pending editorial review.',
                        'changes'=>'Plain text without HTML, no generated narrative or inferred calendar date.']];
            }
        }
        return ['items'=>$items,'scanned'=>$result['scanned'],'cursor'=>$result['cursor'],'complete'=>$result['complete']];
    }

    /**
     * Převede HTML kapitoly na čistý text a bibliografický údaj.
     *
     * @param  string $html  HTML z Wikipedie API.
     * @param  string $title Očekávaný název kapitoly (pro kontrolu infoboxu).
     * @return array{body: string, bibliography: string} Čistý text a údaj „Zdroj“.
     * @throws SyncException 'invalid_story_html', 'story_metadata_changed',
     *                       'story_license_not_allowed', 'story_markup_changed'
     *                       nebo 'story_body_out_of_bounds'.
     */
    private function extract(string $html, string $title): array
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) { throw new SyncException('invalid_story_html'); }
        $xp = new \DOMXPath($dom);
        $table = $xp->query('//table[contains(concat(" ", normalize-space(@class), " "), " textinfo ")]');
        if ($table->length !== 1) { throw new SyncException('story_metadata_changed'); }
        $metadata = [];
        foreach ($xp->query('.//tr', $table->item(0)) as $row) {
            $cells = $xp->query('./td', $row);
            if ($cells->length === 2) {
                $metadata[$this->normalize($cells->item(0)->textContent)] = $this->normalize($cells->item(1)->textContent);
            }
        }
        if (($metadata['Licence:'] ?? '') !== 'PD old 70') { throw new SyncException('story_license_not_allowed'); }
        if (($metadata['Autor:'] ?? '') !== self::AUTHOR || ($metadata['Titulek:'] ?? '') !== $title || empty($metadata['Zdroj:'])) {
            throw new SyncException('story_metadata_changed');
        }
        $prose = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " forma ") and contains(concat(" ", normalize-space(@class), " "), " proza ")]');
        if ($prose->length !== 1) { throw new SyncException('story_markup_changed'); }
        $body = $prose->item(0);
        // Never persist executable markup, image captions, navigation or reference markers.
        $remove = $xp->query('.//script|.//style|.//table|.//sup|.//figure|.//img|.//span[contains(concat(" ", normalize-space(@class), " "), " mw-editsection ")]', $body);
        foreach (iterator_to_array($remove) as $node) { $node->parentNode?->removeChild($node); }
        $paragraphs = [];
        foreach ($xp->query('.//p', $body) as $paragraph) {
            $value = $this->normalize($paragraph->textContent);
            if ($value !== '') { $paragraphs[] = $value; }
        }
        $text = implode("\n\n", $paragraphs);
        if (strlen($text) < 100 || strlen($text) > 60000 || str_contains($text, "\0")) {
            throw new SyncException('story_body_out_of_bounds');
        }
        return ['body' => $text, 'bibliography' => $metadata['Zdroj:']];
    }

    /**
     * Sjednotí bílé znaky a ořízne text.
     *
     * @param  string $text Vstupní text z HTML.
     * @return string        Text s jedinými mezerami.
     */
    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
