<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;

/** Keyset discovery across every active name, including drafts; no manual name catalog. */
final class EtymologDiscoveryRepository extends BaseRepository
{
    public function next(string $kind, int $after): ?array
    {
        return $this->_db->fetchOne("SELECT n.id,n.name,n.kind FROM etymolog_name n WHERE n.franchise_code=? AND n.deleted=0 AND (?='' OR n.kind=?) AND n.kind IN ('given','surname') AND n.id>? AND NOT EXISTS (SELECT 1 FROM etymolog_name earlier WHERE earlier.franchise_code=n.franchise_code AND earlier.kind=n.kind AND earlier.deleted=0 AND earlier.id<n.id AND BINARY LOWER(TRIM(earlier.name))=BINARY LOWER(TRIM(n.name))) ORDER BY n.id LIMIT 1", [$this->_code,$kind,$kind,$after]) ?: null;
    }

    public function find(int $id, string $kind): ?array
    {
        return $this->_db->fetchOne("SELECT id,name,kind FROM etymolog_name WHERE franchise_code=? AND id=? AND (?='' OR kind=?) AND deleted=0", [$this->_code,$id,$kind,$kind]) ?: null;
    }
}
