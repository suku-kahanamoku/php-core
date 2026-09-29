-- etymolog bootstrap/reference data, consolidated through 2026-09-28.
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
SELECT 'etymolog',0,'Český jmenný kalendář – komunitní zdroj','czech-namedays','cs','calendar',500,604800,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='czech-namedays' AND `language`='cs' AND `kind`='calendar');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Český Wikislovník – příjmení','wiktionary-cs','cs','surname',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-cs' AND `language`='cs' AND `kind`='surname');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Český Wikislovník – rodná jména','wiktionary-cs','cs','given',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-cs' AND `language`='cs' AND `kind`='given');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Český Wikislovník – přednostní příjmení','wiktionary-cs','cs','surname_priority',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-cs' AND `language`='cs' AND `kind`='surname_priority');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Český Wikislovník – přednostní rodná jména','wiktionary-cs','cs','given_priority',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-cs' AND `language`='cs' AND `kind`='given_priority');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Anglický Wiktionary – přednostní česká příjmení','wiktionary','cs','surname_priority',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='cs' AND `kind`='surname_priority');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Anglický Wiktionary – přednostní česká rodná jména','wiktionary','cs','given_priority',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='cs' AND `kind`='given_priority');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Francouzský Wiktionnaire – příjmení','wiktionary-fr','cs','surname',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-fr' AND `language`='cs' AND `kind`='surname');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Francouzský Wiktionnaire – rodná jména','wiktionary-fr','cs','given',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-fr' AND `language`='cs' AND `kind`='given');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Francouzský Wiktionnaire – přednostní příjmení','wiktionary-fr','cs','surname_priority',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-fr' AND `language`='cs' AND `kind`='surname_priority');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Francouzský Wiktionnaire – přednostní rodná jména','wiktionary-fr','cs','given_priority',3,300,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary-fr' AND `language`='cs' AND `kind`='given_priority');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – cs surname','wiktionary','cs','surname',2,3600,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='cs' AND `kind`='surname');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – cs given','wiktionary','cs','given',2,3600,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='cs' AND `kind`='given');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – sk surname','wiktionary','sk','surname',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='sk' AND `kind`='surname');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – sk given','wiktionary','sk','given',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='sk' AND `kind`='given');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – pl surname','wiktionary','pl','surname',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='pl' AND `kind`='surname');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – pl given','wiktionary','pl','given',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='pl' AND `kind`='given');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – uk surname','wiktionary','uk','surname',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='uk' AND `kind`='surname');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – uk given','wiktionary','uk','given',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='uk' AND `kind`='given');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – de surname','wiktionary','de','surname',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='de' AND `kind`='surname');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'Wiktionary – de given','wiktionary','de','given',2,3600,0
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='wiktionary' AND `language`='de' AND `kind`='given');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'PESEL – PL male','poland-pesel','pl','surname_male',500,3600,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='poland-pesel' AND `language`='pl' AND `kind`='surname_male');
INSERT INTO `etymolog_sync_job` (`franchise_code`,`deleted`,`title`,`provider`,`language`,`kind`,`batch_size`,`interval_seconds`,`enabled`)
SELECT 'etymolog',0,'PESEL – PL female','poland-pesel','pl','surname_female',500,3600,1
WHERE NOT EXISTS (SELECT 1 FROM `etymolog_sync_job` WHERE `franchise_code`='etymolog' AND `provider`='poland-pesel' AND `language`='pl' AND `kind`='surname_female');
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
