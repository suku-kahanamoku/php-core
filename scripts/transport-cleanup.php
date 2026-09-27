<?php

declare(strict_types=1);
require __DIR__.'/transport-bootstrap.php';
$cache = $repository->execute('DELETE FROM transport_journey_cache WHERE franchise_code=? AND expires_at<UTC_TIMESTAMP() LIMIT 10000', [$tenant]);
$runs = $repository->execute('DELETE FROM transport_sync_run WHERE franchise_code=? AND finished_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) LIMIT 10000', [$tenant]);
echo json_encode(['expired_journeys' => $cache,'old_sync_logs' => $runs])."\n";
