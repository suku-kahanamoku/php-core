-- Etymolog Czech-only bootstrap/reference data. Foreign inventories are not seeded.
-- Insert missing records only; preserve passwords, edits and deleted tombstones.
-- IDs use tenant/natural keys, never fixed AUTO_INCREMENT values.
SET NAMES utf8mb4;
START TRANSACTION;

-- role
INSERT INTO `role` (`franchise_code`,`name`,`label`,`position`,`deleted`)
SELECT 'etymolog','user','Uživatel',0,0
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `franchise_code`='etymolog' AND `name`='user');
INSERT INTO `role` (`franchise_code`,`name`,`label`,`position`,`deleted`)
SELECT 'etymolog','admin','Administrátor',0,0
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `franchise_code`='etymolog' AND `name`='admin');

-- etymolog_sync_job
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wikidata – příjmení (cs)','wikidata','cs','surname',20,3600,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wikidata' AND `language`='cs' AND `kind`='surname');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wikidata – křestní jména (cs)','wikidata','cs','given',20,3600,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wikidata' AND `language`='cs' AND `kind`='given');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Erben – pranostiky a tradice','erben-folklore','cs','folklore',4,604800,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='erben-folklore' AND `language`='cs' AND `kind`='folklore');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wikipedie – český jmenný kalendář','czech-namedays','cs','calendar',500,604800,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='czech-namedays' AND `language`='cs' AND `kind`='calendar');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Český Wikislovník – příjmení','wiktionary-cs','cs','surname',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-cs' AND `language`='cs' AND `kind`='surname');
-- Additional CC BY-SA evidence for Czech surnames already present in the tenant DB.
-- The source text is English; no name is created from a fixed list or translated.
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Anglický Wiktionary – etymologie českých příjmení','wiktionary','cs','surname',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='cs' AND `kind`='surname');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Český Wikislovník – rodná jména','wiktionary-cs','cs','given',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-cs' AND `language`='cs' AND `kind`='given');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'ČSÚ – jména novorozenců 2025 TOP 100','csu-baby-names','cs','births_2025',500,604800,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='csu-baby-names' AND `language`='cs' AND `kind`='births_2025');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wikizdroje – české pověsti','wikisource','cs','stories',4,86400,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wikisource' AND `language`='cs' AND `kind`='stories');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wikipedie – ověřené oddíly o původu jmen','wikipedia-names','cs','etymologies',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wikipedia-names' AND `language`='cs' AND `kind`='etymologies');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wikipedie – legendy, mytologie, tradice a pranostiky','wikipedia-names','cs','culture',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wikipedia-names' AND `language`='cs' AND `kind`='culture');

COMMIT;
