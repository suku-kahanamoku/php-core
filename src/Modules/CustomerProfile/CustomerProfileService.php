<?php
declare(strict_types=1);
namespace App\Modules\CustomerProfile;
use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
/**
 * Doménová služba zákaznických profilů (person).
 *
 * Veškeré operace vyžadují roli `admin`. Nepublikovaný profil je mimo admina
 * nedostupný (404) a z veřejného výstupu se odfiltrují interní sloupce.
 */
class CustomerProfileService extends BaseService
{
    private CustomerProfileRepository $profiles;

    /**
     * @param  Database $db   Připojení k databázi pro repozitář profilů.
     * @param  string   $code Kód okurku (tenanta).
     * @param  Auth     $auth Kontext autentizace pro kontrolu rolí.
     * @return void
     */
    public function __construct(Database $db,string $code,Auth $auth){$this->profiles=new CustomerProfileRepository($db,$code);$this->_auth=$auth;}
    /**
     * Vrátí stránkovaný seznam profilů.
     *
     * @param  int    $page   Číslo stránky (od 1).
     * @param  int    $limit  Počet záznamů na stránku; ořízne na maximálně 100.
     * @param  string $sort   Řazení dle standardního dotazového kontraktu.
     * @param  string $filter JSON filtr dle standardního dotazového kontraktu.
     * @return array           Stránkovací odpověď s `data`, `total`, `page`, `limit`, `totalPages`.
     * @throws \Throwable      Při chybějící roli `admin` je request ukončen s 401/403.
     */
    public function list(int $page,int $limit,string $sort,string $filter):array{$this->_auth->requireRole('admin');return $this->profiles->findAll($page,$limit,$sort,$filter);}
    /**
     * Vrátí jeden profil.
     *
     * @param  int $id ID profilu.
     * @return array   Profil s poli `questions`, `objections` a `preferences`.
     * @throws \Throwable Při chybějícím záznamu nebo nepublikovaném profilu u non-admin končí request 404.
     */
    public function get(int $id):array
    {
        $isAdmin = $this->_auth->hasRole('admin');
        $value = $this->profiles->findById($id);
        $this->_requireEntity($value,'Customer profile not found');
        if (!$isAdmin && (int) ($value['published'] ?? 0) !== 1) {
            Response::notFound('Customer profile not found');
        }
        if (!$isAdmin) {
            unset($value['franchise_code'], $value['deleted']);
        }
        return $value;
    }
    /**
     * Vytvoří nový profil.
     *
     * @param  array<string, mixed> $input Vstupní atributy; `syscode` a `name` jsou povinné.
     * @return array                    Vytvořený profil.
     * @throws \Throwable               Bez role `admin` nebo při chybějících povinných polích je request ukončen; duplicitní syscode končí s 409.
     */
    public function create(array $input):array{$this->_auth->requireRole('admin');$d=$this->sanitize($input,true);if($this->profiles->codeExists($d['syscode']))Response::error('Profile syscode already exists',409);return $this->profiles->create($d);}
    /**
     * Aktualizuje existující profil.
     *
     * @param  int                  $id    ID profilu.
     * @param  array<string, mixed> $input Patch: ukládají se pouze zaslané a neprázdné hodnoty.
     * @return array                    Aktualizovaný profil.
     * @throws \Throwable               Bez role `admin`, u chybějícího záznamu nebo při duplicitním syscode je request ukončen (404/409).
     */
    public function update(int $id,array $input):array{$this->_auth->requireRole('admin');$this->_requireEntity($this->profiles->findById($id),'Customer profile not found');$d=$this->sanitize($input,false);if(isset($d['syscode'])&&$this->profiles->codeExists($d['syscode'],$id))Response::error('Profile syscode already exists',409);return $this->profiles->update($id,$d);}
    /**
     * Označí profil jako smazaný (soft delete).
     *
     * @param  int $id ID profilu.
     * @return int     Počet ovlivněných řádků (0 nebo 1).
     * @throws \Throwable Bez role `admin` nebo u chybějícího záznamu je request ukončen.
     */
    public function delete(int $id):int{$this->_auth->requireRole('admin');$this->_requireEntity($this->profiles->findById($id),'Customer profile not found');return $this->profiles->softDelete($id);}
    /**
     * Omezí vstup na známé sloupce a převede hodnoty na správné typy.
     *
     * @param  array<string, mixed> $input    Z teleco požadavku.
     * @param  bool                 $required true pro create (validace `syscode` a `name`), false pro update.
     * @return array<string, mixed>           Data připravená pro repozitář.
     * @throws \Throwable                    Při chybějícím `syscode` nebo `name` je request ukončen s 422.
     */
    private function sanitize(array $input,bool $required):array
    {
        if($required)VALIDATOR(['syscode'=>trim((string)($input['syscode']??'')),'name'=>trim((string)($input['name']??''))])->required(['syscode','name'])->validate();
        $d=[];
        foreach(['syscode','name','selection_need','summary','aura','visual','behavior','business_potential','typical_quote','marketing_note'] as $f)if(array_key_exists($f,$input)&&$input[$f]!==null)$d[$f]=trim((string)$input[$f]);
        foreach(['profile_number','position','published'] as $f)if(array_key_exists($f,$input)&&$input[$f]!==null)$d[$f]=(int)$input[$f];
        if(array_key_exists('average_basket',$input))$d['average_basket']=$input['average_basket']===null?null:(float)$input['average_basket'];
        foreach(['questions','objections','preferences'] as $f)if(array_key_exists($f,$input)&&is_array($input[$f]))$d[$f]=$input[$f];
        return $d;
    }
}
