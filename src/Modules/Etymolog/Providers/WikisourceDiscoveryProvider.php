<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;

use App\Modules\Etymolog\{EtymologDiscoveryRepository,SyncException};
use App\Modules\Http\Contracts\HttpClient;

/** Search a licensed collection for each DB name; no catalog or inferred name associations. */
final class WikisourceDiscoveryProvider
{
    public function __construct(private readonly HttpClient $http, private readonly EtymologDiscoveryRepository $names) {}

    public function page(string $book, ?string $cursor): array
    {
        $state=$cursor===null ? ['after'=>0] : json_decode($cursor,true);
        if (is_int($state) && $state>=0) { $state=['after'=>0]; } // Retired catalog offset.
        if (!is_array($state) || !is_int($state['after'] ?? null) || $state['after']<0 || (array_key_exists('name_id',$state) && (!is_int($state['name_id']) || $state['name_id']<=$state['after'] || !is_int($state['offset'] ?? null) || $state['offset']<1)) || (isset($state['offset']) && !isset($state['name_id']))) { throw new SyncException('invalid_provider_cursor'); }
        $name=isset($state['name_id']) ? $this->names->find($state['name_id'],'') : $this->names->next('',$state['after']);
        if (!$name) {
            if (isset($state['name_id'])) { return $this->finish(null,null,['after'=>$state['name_id']]); }
            return ['page'=>null,'name'=>null,'cursor'=>null,'complete'=>true,'scanned'=>0];
        }
        // Restrict the search grammar as well as the endpoint; names are never operators.
        if (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M} .’\x{0027}-]*$/uD',$name['name'])) { return $this->finish(null,$name,['after'=>(int)$name['id']]); }
        $url='https://cs.wikisource.org/w/api.php?';
        $response=ProviderHttp::json($this->http,$url.http_build_query(['action'=>'query','list'=>'search','srsearch'=>'"'.$name['name'].'" prefix:"'.$book.'/"','srnamespace'=>0,'srlimit'=>1,'sroffset'=>$state['offset'] ?? 0,'srprop'=>'','format'=>'json','maxlag'=>5]));
        $found=$response['query']['search'] ?? null;
        if (!is_array($found) || !array_is_list($found) || count($found)>1) { throw new SyncException('invalid_story_discovery'); }
        $nextOffset=$response['continue']['sroffset'] ?? null;
        if ($nextOffset!==null && (!is_int($nextOffset) || $nextOffset<=($state['offset'] ?? 0) || !$found)) { throw new SyncException('invalid_story_discovery'); }
        $next=$nextOffset===null ? ['after'=>(int)$name['id']] : ['after'=>$state['after'],'name_id'=>(int)$name['id'],'offset'=>$nextOffset];
        if (!$found) { return $this->finish(null,$name,$next); }
        $hit=$found[0];
        if (!is_int($hit['pageid'] ?? null) || $hit['pageid']<1 || ($hit['ns'] ?? null)!==0 || !is_string($hit['title'] ?? null) || !str_starts_with($hit['title'],$book.'/')) { throw new SyncException('invalid_story_discovery'); }
        $response=ProviderHttp::json($this->http,$url.http_build_query(['action'=>'parse','pageid'=>$hit['pageid'],'prop'=>'text|revid','format'=>'json','maxlag'=>5]));
        $page=$response['parse'] ?? [];
        if (($page['pageid'] ?? null)!==$hit['pageid'] || ($page['title'] ?? null)!==$hit['title'] || !is_int($page['revid'] ?? null) || $page['revid']<1 || !is_string($page['text']['*'] ?? null)) { throw new SyncException('invalid_story_response'); }
        return $this->finish($page,$name,$next);
    }

    private function finish(?array $page,?array $name,array $state): array
    {
        $complete=!isset($state['name_id']) && $this->names->next('',$state['after'])===null;
        return ['page'=>$page,'name'=>$name,'cursor'=>$complete ? null : json_encode($state,JSON_THROW_ON_ERROR),'complete'=>$complete,'scanned'=>1];
    }

    public static function mentions(string $text,string $name): bool
    {
        return preg_match('/(?<![\p{L}\p{M}])'.preg_quote($name,'/').'(?![\p{L}\p{M}])/ui',$text)===1;
    }
}
