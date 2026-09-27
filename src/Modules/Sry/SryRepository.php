<?php
declare(strict_types=1);
namespace App\Modules\Sry;
use App\Modules\BaseRepository;
/** Shared transaction boundary for Sry repositories; no public raw-SQL API. */
abstract class SryRepository extends BaseRepository
{
    public function transaction(callable $action): mixed
    {
        $pdo = $this->_db->getPdo();
        $nested = $pdo->inTransaction();
        $savepoint = "sry_" . bin2hex(random_bytes(8));
        if ($nested) {
            $pdo->exec("SAVEPOINT $savepoint");
        } else {
            $pdo->beginTransaction();
        }
        try {
            $result = $action();
            if ($nested) {
                $pdo->exec("RELEASE SAVEPOINT $savepoint");
            } else {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                if ($nested) {
                    $pdo->exec("ROLLBACK TO SAVEPOINT $savepoint");
                } else {
                    $pdo->rollBack();
                }
            }
            throw $e;
        }
    }
}
