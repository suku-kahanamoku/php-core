<?php
declare(strict_types=1);
namespace App\Modules\Transport\Persistence;

use App\Modules\Transport\Model\{ProviderDefinition,TransportException};

/** Cross-process admission of EVERY HTTP request, including batched prerequisite calls. */
final class ProviderQuotaRepository
{
    public function __construct(private readonly \PDO $db) {}

    private function policy(ProviderDefinition $definition): array
    {
        $quota = $definition->config['quota'] ?? [];
        $scope = isset($quota['scope']) ? 'shared:'.$quota['scope'] : 'tenant:'.$definition->tenant.':'.$definition->code;
        $interval = (int)($definition->config['min_interval_ms'] ?? 0);
        $limit = (int)($quota['limit'] ?? 0);
        $window = (int)($quota['window_ms'] ?? 0);
        return [hash('sha256', $scope), hash('sha256', "$interval:$limit:$window"), $interval, $limit, $window];
    }

    /** Zero grants one request; positive milliseconds mean wait without consuming quota. */
    public function reserve(ProviderDefinition $definition): int
    {
        [$scope,$policy,$interval,$limit,$window] = $this->policy($definition);
        if ($this->db->inTransaction()) { throw new \LogicException('Outbound quota reservation cannot run inside a domain transaction.'); }
        $this->db->beginTransaction();
        try {
            $this->query('INSERT IGNORE INTO transport_provider_quota(scope_key,policy_hash) VALUES (?,?)', [$scope,$policy]);
            $row = $this->query('SELECT policy_hash, GREATEST(0,COALESCE(TIMESTAMPDIFF(MICROSECOND,UTC_TIMESTAMP(6),next_request_at),0),COALESCE(TIMESTAMPDIFF(MICROSECOND,UTC_TIMESTAMP(6),blocked_until),0)) wait_us FROM transport_provider_quota WHERE scope_key=? FOR UPDATE', [$scope])->fetch(\PDO::FETCH_ASSOC);
            if ($row['policy_hash'] !== $policy) {
                throw new TransportException('invalid_quota_configuration', 'All instances of a quota scope must use the same policy.', 500);
            }
            $wait = (int)ceil((int)$row['wait_us'] / 1000);
            if ($limit) {
                $this->query('DELETE FROM transport_provider_quota_usage WHERE scope_key=? AND requested_at<=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL ? MICROSECOND)', [$scope,$window * 1000]);
                $used = $this->query('SELECT COUNT(*) n, COALESCE(TIMESTAMPDIFF(MICROSECOND,UTC_TIMESTAMP(6),DATE_ADD(MIN(requested_at),INTERVAL ? MICROSECOND)),0) wait_us FROM transport_provider_quota_usage WHERE scope_key=?', [$window * 1000,$scope])->fetch(\PDO::FETCH_ASSOC);
                if ((int)$used['n'] >= $limit) { $wait = max($wait, (int)ceil((int)$used['wait_us'] / 1000), 1); }
            }
            if ($wait === 0) {
                $this->query('UPDATE transport_provider_quota SET last_used_at=UTC_TIMESTAMP(6),next_request_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ? MICROSECOND) WHERE scope_key=?', [$interval * 1000,$scope]);
                if ($limit) { $this->query('INSERT INTO transport_provider_quota_usage(scope_key,requested_at) VALUES (?,UTC_TIMESTAMP(6))', [$scope]); }
            }
            $this->db->commit();
            return $wait;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function block(ProviderDefinition $definition, int $seconds): void
    {
        [$scope,$policy] = $this->policy($definition);
        $this->query('INSERT IGNORE INTO transport_provider_quota(scope_key,policy_hash) VALUES (?,?)', [$scope,$policy]);
        $this->query('UPDATE transport_provider_quota SET last_used_at=UTC_TIMESTAMP(6),blocked_until=GREATEST(COALESCE(blocked_until,UTC_TIMESTAMP(6)),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL ? SECOND)) WHERE scope_key=?', [max(1,min(3600,$seconds)),$scope]);
    }

    private function query(string $sql, array $values): \PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($values);
        return $statement;
    }
}
