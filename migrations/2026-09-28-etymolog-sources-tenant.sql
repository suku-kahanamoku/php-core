-- Optional tenant jobs; apply after 2026-09-28-etymolog-sources.sql. Idempotent.
SET NAMES utf8mb4;
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – cs surname','wiktionary','cs','surname',2,3600,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='cs' AND kind='surname');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – cs given','wiktionary','cs','given',2,3600,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='cs' AND kind='given');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – sk surname','wiktionary','sk','surname',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='sk' AND kind='surname');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – sk given','wiktionary','sk','given',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='sk' AND kind='given');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – pl surname','wiktionary','pl','surname',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='pl' AND kind='surname');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – pl given','wiktionary','pl','given',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='pl' AND kind='given');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – uk surname','wiktionary','uk','surname',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='uk' AND kind='surname');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – uk given','wiktionary','uk','given',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='uk' AND kind='given');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – de surname','wiktionary','de','surname',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='de' AND kind='surname');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Wiktionary – de given','wiktionary','de','given',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary' AND language='de' AND kind='given');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','PESEL – PL male','poland-pesel','pl','surname_male',500,3600,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='poland-pesel' AND language='pl' AND kind='surname_male');
INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','PESEL – PL female','poland-pesel','pl','surname_female',500,3600,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='poland-pesel' AND language='pl' AND kind='surname_female');

INSERT INTO etymolog_sync_job (franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','ČSÚ – jména novorozenců 2025 TOP 100','csu-baby-names','cs','births_2025',500,604800,1
WHERE NOT EXISTS (SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='csu-baby-names' AND kind='births_2025');
