<?php
declare(strict_types=1);
namespace App\Modules\CustomerProfile;
use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
class CustomerProfileService extends BaseService
{
    private CustomerProfileRepository $profiles;
    public function __construct(Database $db,string $code,Auth $auth){$this->profiles=new CustomerProfileRepository($db,$code);$this->_auth=$auth;}
    public function list(int $page,int $limit,string $sort,string $filter):array{$this->_auth->requireRole('admin');return $this->profiles->findAll($page,$limit,$sort,$filter);}
    public function get(int $id):array{$this->_auth->requireRole('admin');$v=$this->profiles->findById($id);$this->_requireEntity($v,'Customer profile not found');return $v;}
    public function create(array $input):array{$this->_auth->requireRole('admin');$d=$this->sanitize($input,true);if($this->profiles->codeExists($d['syscode']))Response::error('Profile syscode already exists',409);return $this->profiles->create($d);}
    public function update(int $id,array $input):array{$this->_auth->requireRole('admin');$this->_requireEntity($this->profiles->findById($id),'Customer profile not found');$d=$this->sanitize($input,false);if(isset($d['syscode'])&&$this->profiles->codeExists($d['syscode'],$id))Response::error('Profile syscode already exists',409);return $this->profiles->update($id,$d);}
    public function delete(int $id):int{$this->_auth->requireRole('admin');$this->_requireEntity($this->profiles->findById($id),'Customer profile not found');return $this->profiles->softDelete($id);}
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
