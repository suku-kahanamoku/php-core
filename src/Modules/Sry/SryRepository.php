<?php
declare(strict_types=1);
namespace App\Modules\Sry;
use App\Modules\BaseRepository;
/**
 * Společná hranice transakcí pro repozitáře modulu Sry; bez veřejného API pro surové SQL.
 *
 * Podřízené repozitáře tak nemají přímý přístup k PDO — jediné, co mohou pro
 * více krokové operace použít, je `transaction()`. Vnořené volání se řeší
 * pomocí savepointu, aby se vnější transakce neukončila předčasně.
 */
abstract class SryRepository extends BaseRepository
{
    /**
     * Provede callback v transakci; při chybě se změní rollback.
     *
     * @param  callable $action Callback bez parametrů; jeho návratová hodnota se předá dál.
     * @return mixed            Návratová hodnota callbacku.
     * @throws \Throwable      Výjimka z callbacku se po rollbacku znovu vyhodí.
     */
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
