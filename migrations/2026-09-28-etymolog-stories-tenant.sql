-- Optional initial Wikisource job for tenant etymolog; no account or cron creation.
SET NAMES utf8mb4;
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wikizdroje – české pověsti','wikisource','cs','stories',4,86400,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikisource' AND language='cs' AND kind='stories');
