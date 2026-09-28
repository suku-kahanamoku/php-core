SET NAMES utf8mb4;
INSERT INTO etymolog_sync_job(franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Erben – pranostiky a tradice','erben-folklore','cs','folklore',4,604800,1
WHERE NOT EXISTS(SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='erben-folklore');
INSERT INTO etymolog_sync_job(franchise_code,title,provider,language,kind,batch_size,interval_seconds,enabled)
SELECT 'etymolog','Český jmenný kalendář – komunitní zdroj','czech-namedays','cs','calendar',500,604800,1
WHERE NOT EXISTS(SELECT 1 FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='czech-namedays');
