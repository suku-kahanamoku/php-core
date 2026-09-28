-- Configuration only. Does not run synchronization or publish content.
-- Preserve existing settings, cursors, disabled jobs and deletion tombstones.
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wikipedie – ověřené oddíly o původu jmen','wikipedia-names','cs','etymologies',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikipedia-names' AND language='cs' AND kind='etymologies');

INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wikipedie – legendy, mytologie, tradice a pranostiky','wikipedia-names','cs','culture',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikipedia-names' AND language='cs' AND kind='culture');
