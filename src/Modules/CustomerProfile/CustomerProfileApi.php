<?php
declare(strict_types=1);
namespace App\Modules\CustomerProfile;
use App\Modules\Auth\Auth;use App\Modules\Database\Database;use App\Modules\Router\Request;use App\Modules\Router\Response;use App\Modules\Router\Router;
class CustomerProfileApi
{
    private CustomerProfileService $service;
    public function __construct(Database $db,string $code,Auth $auth){$this->service=new CustomerProfileService($db,$code,$auth);}
    public function list(Request $r):void{Response::successList($this->service->list(max(1,(int)$r->get('page',1)),min(100,max(1,(int)$r->get('limit',100))),(string)$r->get('sort',''),(string)$r->get('q','')),$r);}
    public function get(Request $r,array $p):void{Response::successItem($this->service->get((int)$p['id']),$r);}
    public function create(Request $r):void{Response::created($this->service->create($r->body),'Customer profile created');}
    public function update(Request $r,array $p):void{Response::success($this->service->update((int)$p['id'],$r->body),'Customer profile updated');}
    public function delete(Request $r,array $p):void{$this->service->delete((int)$p['id']);Response::success(null,'Customer profile deleted');}
    public function registerRoutes(Router $r):void{$r->get('/',[$this,'list']);$r->post('/',[$this,'create']);$r->get('/:id',[$this,'get']);$r->put('/:id',[$this,'update']);$r->patch('/:id',[$this,'update']);$r->delete('/:id',[$this,'delete']);}
}
