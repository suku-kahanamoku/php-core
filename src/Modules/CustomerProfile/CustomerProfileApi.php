<?php
declare(strict_types=1);
namespace App\Modules\CustomerProfile;
use App\Modules\Auth\Auth;use App\Modules\Database\Database;use App\Modules\Router\Request;use App\Modules\Router\Response;use App\Modules\Router\Router;
/**
 * HTTP vrstva modulu CustomerProfile — zákaznické profily (persony).
 *
 * Handler jen předává tělo požadavku službě a převádí výsledek na odpověď.
 * Kontrola role `admin` probíhá uvnitř `CustomerProfileService`.
 */
class CustomerProfileApi
{
    private CustomerProfileService $service;

    /**
     * @param  Database $db  Připojení k databázi.
     * @param  string   $code Kód okurku (tenanta).
     * @param  Auth     $auth Kontext autentizace pro kontrolu rolí.
     * @return void
     */
    public function __construct(Database $db,string $code,Auth $auth){$this->service=new CustomerProfileService($db,$code,$auth);}
    /**
     * GET /customer-profiles — Vrátí stránkovaný seznam profilů (vyžaduje roli admin).
     *
     * @param  Request $r Aktuální požadavek; query: page, limit, sort, q.
     * @return void       Odpověď se stránkovacími metadaty.
     */
    public function list(Request $r):void{Response::successList($this->service->list(max(1,(int)$r->get('page',1)),min(100,max(1,(int)$r->get('limit',100))),(string)$r->get('sort',''),(string)$r->get('q','')),$r);}
    /**
     * GET /customer-profiles/:id — Vrátí jeden profil.
     *
     * @param  Request $r Aktuální požadavek.
     * @param  array   $p Parametry routy s `id` profilu.
     * @return void       Odpověď s jedním záznamem; nepublikovaný profil není pro non-admin vidět (404).
     */
    public function get(Request $r,array $p):void{Response::successItem($this->service->get((int)$p['id']),$r);}
    /**
     * POST /customer-profiles — Vytvoří nový profil (vyžaduje roli admin).
     *
     * @param  Request $r Tělo požadavku: minimálně `syscode` a `name`.
     * @return void       Odpověď 201 s vytvořeným profilem; duplicitní syscode končí s 409.
     */
    public function create(Request $r):void{Response::created($this->service->create($r->body),'Customer profile created');}
    /**
     * PUT|PATCH /customer-profiles/:id — Aktualizuje profil (vyžaduje roli admin).
     *
     * @param  Request $r Tělo požadavku s měnícími se atributy.
     * @param  array   $p Parametry routy s `id` profilu.
     * @return void       Odpověď s aktualizovaným profilem; duplicitní syscode končí s 409.
     */
    public function update(Request $r,array $p):void{Response::success($this->service->update((int)$p['id'],$r->body),'Customer profile updated');}
    /**
     * DELETE /customer-profiles/:id — Označí profil jako smazaný (vyžaduje roli admin).
     *
     * @param  Request $r Aktuální požadavek.
     * @param  array   $p Parametry routy s `id` profilu.
     * @return void       Odpověď bez těla; chybějící profil končí s 404.
     */
    public function delete(Request $r,array $p):void{$this->service->delete((int)$p['id']);Response::success(null,'Customer profile deleted');}
    /**
     * Zaregistruje routy modulu: GET/POST `/`, GET/PUT/PATCH/DELETE `/:id`.
     *
     * @param  Router $r Router, do kterého se routy zapisují.
     * @return void
     */
    public function registerRoutes(Router $r):void{$r->get('/',[$this,'list']);$r->post('/',[$this,'create']);$r->get('/:id',[$this,'get']);$r->put('/:id',[$this,'update']);$r->patch('/:id',[$this,'update']);$r->delete('/:id',[$this,'delete']);}
}
