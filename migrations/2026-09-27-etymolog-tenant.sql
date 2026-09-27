-- Optional initial configuration for tenant etymolog. Idempotent and additive.
-- Apply after 2026-09-27-etymolog.sql and the existing core auth schema.
-- Does not create accounts, passwords, tokens or a scheduled system cron.
SET NAMES utf8mb4;

INSERT INTO role (franchise_code,name,label)
SELECT 'etymolog','user','Uživatel'
WHERE NOT EXISTS (SELECT 1 FROM role WHERE franchise_code='etymolog' AND name='user');

INSERT INTO role (franchise_code,name,label)
SELECT 'etymolog','admin','Administrátor'
WHERE NOT EXISTS (SELECT 1 FROM role WHERE franchise_code='etymolog' AND name='admin');

INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wikidata – příjmení (cs)','wikidata','cs','surname',20,3600,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikidata' AND language='cs' AND kind='surname');

INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wikidata – křestní jména (cs)','wikidata','cs','given',20,3600,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikidata' AND language='cs' AND kind='given');
