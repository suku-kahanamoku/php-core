<?php

declare(strict_types=1);
require __DIR__.'/transport-bootstrap.php';
$cache = $repository->execute('DELETE FROM transport_journey_cache WHERE franchise_code=? AND expires_at<UTC_TIMESTAMP() LIMIT 10000', [$tenant]);
$runs = $repository->execute('DELETE FROM transport_sync_run WHERE franchise_code=? AND finished_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) LIMIT 10000', [$tenant]);
// Shared rows contain only admission timestamps; no tenant payloads or credentials.
$quotaUsage = $repository->execute('DELETE FROM transport_provider_quota_usage WHERE requested_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 HOUR) LIMIT 10000');
$quotaScopes = $repository->execute('DELETE FROM transport_provider_quota WHERE last_used_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 HOUR) AND (next_request_at IS NULL OR next_request_at<=UTC_TIMESTAMP(6)) AND (blocked_until IS NULL OR blocked_until<=UTC_TIMESTAMP(6)) LIMIT 10000');
echo json_encode(['expired_journeys' => $cache,'old_sync_logs' => $runs,'old_quota_usage'=>$quotaUsage,'old_quota_scopes'=>$quotaScopes])."\n";
