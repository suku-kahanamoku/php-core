-- Idempotent demo data for the Zoo CRM tenant.
-- Adds the two CRM profile columns when upgrading an existing php-core database.

SET @zoo_has_client_type = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'user' AND column_name = 'client_type_id'
);
SET @zoo_ddl = IF(
  @zoo_has_client_type = 0,
  'ALTER TABLE `user` ADD COLUMN `client_type_id` INT UNSIGNED NULL COMMENT ''logical FK to enumeration.id (type client_type)'' AFTER `phone`',
  'SELECT 1'
);
PREPARE zoo_stmt FROM @zoo_ddl;
EXECUTE zoo_stmt;
DEALLOCATE PREPARE zoo_stmt;

SET @zoo_has_profile = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'user' AND column_name = 'profile'
);
SET @zoo_ddl = IF(
  @zoo_has_profile = 0,
  'ALTER TABLE `user` ADD COLUMN `profile` JSON NULL COMMENT ''CRM customer profile and recommendations'' AFTER `client_type_id`',
  'SELECT 1'
);
PREPARE zoo_stmt FROM @zoo_ddl;
EXECUTE zoo_stmt;
DEALLOCATE PREPARE zoo_stmt;

SET @zoo_has_client_type_index = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'user' AND index_name = 'idx_user_client_type_id'
);
SET @zoo_ddl = IF(
  @zoo_has_client_type_index = 0,
  'ALTER TABLE `user` ADD INDEX `idx_user_client_type_id` (`client_type_id`)',
  'SELECT 1'
);
PREPARE zoo_stmt FROM @zoo_ddl;
EXECUTE zoo_stmt;
DEALLOCATE PREPARE zoo_stmt;

START TRANSACTION;

INSERT INTO `role` (`franchise_code`, `name`, `label`, `position`) VALUES
  ('zoo', 'admin', 'Administrátor', 10),
  ('zoo', 'manager', 'Manažer', 20),
  ('zoo', 'user', 'Klient', 30)
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `position` = VALUES(`position`),
  `deleted` = 0;

SET @zoo_admin_role_id = (
  SELECT `id` FROM `role` WHERE `franchise_code` = 'zoo' AND `name` = 'admin' LIMIT 1
);
SET @zoo_user_role_id = (
  SELECT `id` FROM `role` WHERE `franchise_code` = 'zoo' AND `name` = 'user' LIMIT 1
);

INSERT INTO `enumeration`
  (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`, `published`, `data`)
VALUES
  ('zoo', 'client_type', 'premium_shopper', 'Rozmaznávač', 'Prémiový nakupující', 10, 1,
   JSON_OBJECT('color', 'emerald', 'icon', 'sparkles')),
  ('zoo', 'client_type', 'beginner_aquarist', 'Zmatený prvoakvarista', 'Impulzivní začátečník', 20, 1,
   JSON_OBJECT('color', 'blue', 'icon', 'waves')),
  ('zoo', 'client_type', 'professional_breeder', 'Profesionální chovatel', 'Expert a specialista', 30, 1,
   JSON_OBJECT('color', 'violet', 'icon', 'badge-check')),
  ('zoo', 'client_type', 'puppy_rescuer', 'Záchranář se štěnětem', 'Řešitel krizových situací', 40, 1,
   JSON_OBJECT('color', 'orange', 'icon', 'heart-handshake')),
  ('zoo', 'client_type', 'family_visitor', 'Sobotní tatínek', 'Rekreační rodinný návštěvník', 50, 1,
   JSON_OBJECT('color', 'amber', 'icon', 'users')),
  ('zoo', 'client_type', 'terrarium_specialist', 'Pan terarista', 'Specializovaný nákupčí', 60, 1,
   JSON_OBJECT('color', 'lime', 'icon', 'bug')),
  ('zoo', 'client_type', 'cat_loyalist', 'Babička s kočičím královstvím', 'Věrný vztahový zákazník', 70, 1,
   JSON_OBJECT('color', 'pink', 'icon', 'heart')),
  ('zoo', 'client_type', 'experiential_tester', 'Tester všeho', 'Zážitkový zákazník', 80, 1,
   JSON_OBJECT('color', 'cyan', 'icon', 'party-popper')),
  ('zoo', 'client_type', 'discount_specialist', 'Lovec slev', 'Cenově citlivý věrnostní specialista', 90, 1,
   JSON_OBJECT('color', 'red', 'icon', 'badge-percent')),
  ('zoo', 'client_type', 'community_rescuer', 'Záchranář z balkonu', 'Objemový komunitní nakupující', 100, 1,
   JSON_OBJECT('color', 'teal', 'icon', 'bird'))
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`),
  `value` = VALUES(`value`),
  `position` = VALUES(`position`),
  `published` = 1,
  `data` = VALUES(`data`),
  `deleted` = 0;

INSERT INTO `enumeration`
  (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`, `published`, `data`)
VALUES
  ('zoo', 'vat_rate', 'standard', 'Základní sazba DPH', '21', 10, 1, JSON_OBJECT('rate', 21))
ON DUPLICATE KEY UPDATE
  `label` = VALUES(`label`), `value` = VALUES(`value`),
  `position` = VALUES(`position`), `published` = 1,
  `data` = VALUES(`data`), `deleted` = 0;

INSERT INTO `user`
  (`franchise_code`, `first_name`, `last_name`, `email`, `phone`, `client_type_id`, `profile`, `password`, `role_id`, `status`)
VALUES
  ('zoo', 'Zoo', 'Admin', 'admin@zoo.local', NULL, NULL, NULL,
   '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_admin_role_id, 'active'),
  ('zoo', 'Karolína', 'Nováková', 'karolina.novakova@zoo.local', '+420 601 111 101',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'premium_shopper' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Záleží jí na designu, kvalitě a zdraví domácího mazlíčka. Ráda zkouší novinky a prémiové řady.',
     'aura', 'Můj pes má lepší život než většina lidí.',
     'visual', 'Stylové oblečení, káva v ruce a designové vodítko.',
     'behavior', 'Studuje složení BIO produktů a obměňuje doplňky podle trendů.',
     'business_potential', 'Vysoká hodnota košíku; dobře reaguje na holistická krmiva, limitované edice a designové doplňky.',
     'typical_quote', 'Jsou tyto kachní proužky bez přidaného cukru? Rocky má citlivé bříško.',
     'preferred_animals', JSON_ARRAY('dogs'),
     'preferred_product_kinds', JSON_ARRAY('food', 'treats', 'equipment'),
     'recommended_product_skus', JSON_ARRAY('ZOO-PREM-001', 'DOG-TREAT-001', 'DOG-COLLAR-RED'),
     'average_basket', 2450,
     'marketing_note', 'Zdůraznit kvalitu surovin, design, novinky a exkluzivitu.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active'),
  ('zoo', 'Martin', 'Dvořák', 'martin.dvorak@zoo.local', '+420 601 111 102',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'beginner_aquarist' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Začínající akvarista motivovaný přáním dětí. Potřebuje jednoduché řešení a srozumitelnou edukaci.',
     'aura', 'Syn chtěl rybičku, co se může pokazit?',
     'visual', 'Lehce panikaří a ukazuje inspiraci z telefonu.',
     'behavior', 'Hledá hotové sety a vyžaduje pomoc s kompletní sestavou.',
     'business_potential', 'Silný cross-sell startovacího akvária, filtrace, chemie, krmiva a dekorací.',
     'typical_quote', 'Musí akvárium opravdu běžet dva týdny bez ryb?',
     'preferred_animals', JSON_ARRAY('fish'),
     'preferred_product_kinds', JSON_ARRAY('equipment', 'food'),
     'recommended_product_skus', JSON_ARRAY('AQUA-SET-054', 'AQUA-COND-001', 'AQUA-BAC-001'),
     'average_basket', 3200,
     'marketing_note', 'Nabídnout kompletní balíček a jednoduchý návod krok za krokem.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active'),
  ('zoo', 'Petr', 'Kučera', 'petr.kucera@zoo.local', '+420 601 111 103',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'professional_breeder' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Zkušený chovatel s hlubokou znalostí výživy, který přesně ví, co hledá.',
     'aura', 'O výživě a chovu vím více než výrobce.',
     'visual', 'Praktické oblečení, batoh a přímá cesta ke konkrétnímu regálu.',
     'behavior', 'Porovnává složení, podíl bílkovin a mikronutrienty.',
     'business_potential', 'Stabilní objemový zákazník velkých balení a specializovaných doplňků.',
     'typical_quote', 'Nová receptura snížila podíl chondroprotektiv o dvě desetiny procenta.',
     'preferred_animals', JSON_ARRAY('dogs'),
     'preferred_product_kinds', JSON_ARRAY('food', 'supplements'),
     'recommended_product_skus', JSON_ARRAY('DOG-ONT-20B', 'DOG-SUPP-001'),
     'average_basket', 4100,
     'marketing_note', 'Komunikovat přesná data, složení, objemová balení a dostupnost.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active'),
  ('zoo', 'Lucie', 'Benešová', 'lucie.benesova@zoo.local', '+420 601 111 104',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'puppy_rescuer' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Nová majitelka štěněte, která potřebuje rychlá a funkční řešení prvotního chaosu.',
     'aura', 'Totální vyčerpání smíchané s čistou láskou.',
     'visual', 'Sportovní oblečení a hyperaktivní štěně na vodítku.',
     'behavior', 'Rychle hledá pomůcky pro výcvik, dentální hračky a hygienu.',
     'business_potential', 'Vysoký potenciál dlouhodobé věrnosti při správném vedení v prvním roce.',
     'typical_quote', 'Máte něco, co ho zabaví déle než tři minuty?',
     'preferred_animals', JSON_ARRAY('dogs'),
     'preferred_product_kinds', JSON_ARRAY('hygiene', 'toys', 'equipment'),
     'recommended_product_skus', JSON_ARRAY('PUP-PAD-60', 'DOG-TOY-001', 'DOG-DENT-001'),
     'average_basket', 1650,
     'marketing_note', 'Nabídnout praktický štěněcí checklist a opakované připomínky nákupu.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active'),
  ('zoo', 'Tomáš', 'Král', 'tomas.kral@zoo.local', '+420 601 111 105',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'family_visitor' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Rodinný návštěvník, který bere prodejnu jako výlet a interaktivní program pro děti.',
     'aura', 'Potřebuji zabavit děti, než rodina dokončí nákup.',
     'visual', 'Rodič s dětmi, nejdéle se zdrží u ryb, ptáků a hlodavců.',
     'behavior', 'Nakupuje menší impulzivní položky a drobné pamlsky.',
     'business_potential', 'Nižší košík, ale dobrý prostor pro impulzní nabídky a rodinné akce.',
     'typical_quote', 'Podívej na toho hada, nechtěl bys ho do pokojíčku?',
     'preferred_animals', JSON_ARRAY('birds', 'rodents', 'fish', 'reptiles'),
     'preferred_product_kinds', JSON_ARRAY('treats', 'toys'),
     'recommended_product_skus', JSON_ARRAY('IMP-TREAT-001', 'BIRD-SNACK-001'),
     'average_basket', 420,
     'marketing_note', 'Ukazovat cenově dostupné drobnosti, zážitkové produkty a rodinné akce.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active'),
  ('zoo', 'Radek', 'Veselý', 'radek.vesely@zoo.local', '+420 601 111 106',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'terrarium_specialist' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Specialista na plazy a teraristiku s pravidelnou spotřebou živého krmiva a techniky.',
     'aura', 'Můj svět vyžaduje přesnou teplotu, vlhkost a živé krmivo.',
     'visual', 'Neformální styl, pragmatické a rychlé vystupování.',
     'behavior', 'Míří přímo k živému krmivu, kontroluje stav a doplňuje techniku.',
     'business_potential', 'Pravidelná fixní spotřeba krmiva, osvětlení a topení.',
     'typical_quote', 'Vezmu dvě krabičky středních cvrčků a jednu topnou žárovku.',
     'preferred_animals', JSON_ARRAY('reptiles'),
     'preferred_product_kinds', JSON_ARRAY('food', 'equipment'),
     'recommended_product_skus', JSON_ARRAY('TERR-CRICKET-M', 'TERR-HEAT-075'),
     'average_basket', 980,
     'marketing_note', 'Hlásit dostupnost živého krmiva a technické novinky bez obecného marketingu.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active'),
  ('zoo', 'Marie', 'Černá', 'marie.cerna@zoo.local', '+420 601 111 107',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'cat_loyalist' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Věrná zákaznice, pro kterou je kočka hlavním společníkem a rozhodují její oblíbené chutě.',
     'aura', 'Pro pohodlí své kočky udělám cokoli.',
     'visual', 'Ručně psaný seznam a pevně dané oblíbené značky.',
     'behavior', 'Vyhledává osobní radu a pravidelně kupuje mokré krmivo a stelivo.',
     'business_potential', 'Velmi vysoká věrnost a předvídatelný opakovaný nákup.',
     'typical_quote', 'Máte paštiku s králíkem? Jinou mi Mína ani neochutná.',
     'preferred_animals', JSON_ARRAY('cats'),
     'preferred_product_kinds', JSON_ARRAY('food', 'hygiene'),
     'recommended_product_skus', JSON_ARRAY('CAT-PATE-RAB', 'CAT-LITTER-10'),
     'average_basket', 890,
     'marketing_note', 'Připomínat dostupnost oblíbených příchutí a nabídnout pravidelný odběr.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active'),
  ('zoo', 'Jakub', 'Procházka', 'jakub.prochazka@zoo.local', '+420 601 111 108',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'experiential_tester' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Nakupuje se psem a zapojuje ho přímo do výběru hraček, obojků a doplňků.',
     'aura', 'Nákup je zážitek pro mě i mého psa.',
     'visual', 'Aktivní pes na vodítku, který zkouší produkty z nižších polic.',
     'behavior', 'Rozhoduje se emotivně podle okamžité reakce psa.',
     'business_potential', 'Silná náchylnost k neplánovaným nákupům interaktivních produktů.',
     'typical_quote', 'Boby, vyber si, který obojek se ti líbí.',
     'preferred_animals', JSON_ARRAY('dogs'),
     'preferred_product_kinds', JSON_ARRAY('toys', 'equipment', 'treats'),
     'recommended_product_skus', JSON_ARRAY('DOG-TOY-INT', 'DOG-COLLAR-RED', 'DOG-TREAT-001'),
     'average_basket', 1350,
     'marketing_note', 'Používat emotivní prezentaci, testovací vzorky a produkty vhodné k vyzkoušení.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active'),
  ('zoo', 'Ivana', 'Horáková', 'ivana.horakova@zoo.local', '+420 601 111 109',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'discount_specialist' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Aktivně porovnává ceny, používá věrnostní program a plánuje nákupy podle akcí.',
     'aura', 'Mám aplikaci, kartu a přesně vím, co je dnes v akci.',
     'visual', 'Telefon s otevřenou aplikací a připravenými kupony.',
     'behavior', 'Kombinuje akce, kupony a množstevní nabídky.',
     'business_potential', 'Vysoká digitální angažovanost a dobrá odezva na personalizované slevy.',
     'typical_quote', 'Platí na tyto konzervy akce dva plus jeden?',
     'preferred_animals', JSON_ARRAY('dogs', 'cats'),
     'preferred_product_kinds', JSON_ARRAY('food', 'hygiene'),
     'recommended_product_skus', JSON_ARRAY('CAT-CAN-12', 'DOG-CAN-6', 'CAT-LITTER-10'),
     'average_basket', 1250,
     'marketing_note', 'Posílat jasné cenové nabídky, množstevní slevy a časově omezené kupony.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active'),
  ('zoo', 'Alena', 'Svobodová', 'alena.svobodova@zoo.local', '+420 601 111 110',
   (SELECT id FROM enumeration WHERE franchise_code = 'zoo' AND type = 'client_type' AND syscode = 'community_rescuer' LIMIT 1),
   JSON_OBJECT(
     'summary', 'Stará se o volně žijící ptáky a toulavé kočky, proto hledá velká funkční balení za rozumnou cenu.',
     'aura', 'Pomáhám všude tam, kde je to potřeba.',
     'visual', 'Plně naložený vozík a rychlý praktický nákup.',
     'behavior', 'Kupuje velká balení krmiv, steliv a celé kartony.',
     'business_potential', 'Vysoký obrat základního ekonomického sortimentu.',
     'typical_quote', 'Máte ještě velká balení semínek pro sýkorky?',
     'preferred_animals', JSON_ARRAY('birds', 'cats'),
     'preferred_product_kinds', JSON_ARRAY('food', 'hygiene'),
     'recommended_product_skus', JSON_ARRAY('BIRD-SEED-20', 'CAT-ECO-24'),
     'average_basket', 2800,
     'marketing_note', 'Zdůraznit cenu za jednotku, velkoobjemová balení a dostupnost zásob.'
   ), '$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO', @zoo_user_role_id, 'active')
ON DUPLICATE KEY UPDATE
  `first_name` = VALUES(`first_name`),
  `last_name` = VALUES(`last_name`),
  `phone` = VALUES(`phone`),
  `client_type_id` = VALUES(`client_type_id`),
  `profile` = VALUES(`profile`),
  `role_id` = VALUES(`role_id`),
  `status` = 'active',
  `deleted` = 0;

INSERT INTO `category`
  (`franchise_code`, `parent_id`, `syscode`, `name`, `description`, `position`)
VALUES
  ('zoo', NULL, 'dogs', 'Psi', 'Krmivo, pamlsky, hračky a potřeby pro psy', 10),
  ('zoo', NULL, 'cats', 'Kočky', 'Krmivo, stelivo, hračky a potřeby pro kočky', 20),
  ('zoo', NULL, 'rodents', 'Drobní savci', 'Sortiment pro hlodavce a další drobné savce', 30),
  ('zoo', NULL, 'birds', 'Ptáci', 'Krmivo, klece a potřeby pro ptáky', 40),
  ('zoo', NULL, 'fish', 'Akvaristika', 'Akvária, technika, péče o vodu a krmivo', 50),
  ('zoo', NULL, 'reptiles', 'Teraristika', 'Terária, živé krmivo, osvětlení a vytápění', 60),
  ('zoo', NULL, 'horses', 'Koně', 'Krmivo, doplňky a potřeby pro koně', 70)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `description` = VALUES(`description`),
  `position` = VALUES(`position`), `deleted` = 0;

INSERT INTO `category`
  (`franchise_code`, `parent_id`, `syscode`, `name`, `description`, `position`)
VALUES
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'dogs' LIMIT 1), 'dogs-food', 'Krmivo pro psy', 'Granule, konzervy a speciální diety', 11),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'dogs' LIMIT 1), 'dogs-treats', 'Pamlsky pro psy', 'Tréninkové a funkční pamlsky', 12),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'dogs' LIMIT 1), 'dogs-toys', 'Hračky pro psy', 'Interaktivní, dentální a odolné hračky', 13),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'dogs' LIMIT 1), 'dogs-training', 'Výcvik a hygiena štěňat', 'Podložky, pomůcky a výcvikové potřeby', 14),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'cats' LIMIT 1), 'cats-food', 'Krmivo pro kočky', 'Granule, kapsičky, konzervy a paštiky', 21),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'cats' LIMIT 1), 'cats-litter', 'Steliva pro kočky', 'Hrudkující a přírodní steliva', 22),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'cats' LIMIT 1), 'cats-equipment', 'Doplňky pro kočky', 'Pelíšky, misky, škrabadla a designové doplňky', 23),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'rodents' LIMIT 1), 'rodents-bedding', 'Podestýlky pro drobné savce', 'Přírodní a absorpční podestýlky', 31),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'birds' LIMIT 1), 'birds-food', 'Krmivo pro ptáky', 'Směsi, semena, tyčinky a doplňky', 41),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'birds' LIMIT 1), 'wild-birds', 'Volně žijící ptáci', 'Velká balení směsí pro venkovní ptactvo', 42),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'fish' LIMIT 1), 'aquarium-sets', 'Akvarijní sety', 'Kompletní sety pro začátečníky', 51),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'fish' LIMIT 1), 'aquarium-care', 'Péče o akvárium', 'Chemie, bakterie a úprava vody', 52),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'reptiles' LIMIT 1), 'terrarium-food', 'Krmivo pro terarijní zvířata', 'Živé a doplňkové krmivo', 61),
  ('zoo', (SELECT id FROM category c WHERE c.franchise_code = 'zoo' AND c.syscode = 'reptiles' LIMIT 1), 'terrarium-tech', 'Terarijní technika', 'Osvětlení, topení a regulace klimatu', 62)
ON DUPLICATE KEY UPDATE
  `parent_id` = VALUES(`parent_id`), `name` = VALUES(`name`),
  `description` = VALUES(`description`), `position` = VALUES(`position`), `deleted` = 0;

INSERT INTO `product`
  (`franchise_code`, `sku`, `name`, `description`, `price`, `stock_quantity`, `published`, `kind`, `variant`, `data`)
VALUES
  ('zoo', 'ZOO-PREM-001', 'Holistické granule Fresh Duck & Herbs 10 kg', 'Prémiové holistické krmivo bez obilovin pro citlivé dospělé psy.', 1899, 24, 1, 'food', '10 kg', JSON_OBJECT('brand', 'Zoo Selection', 'weight', 10, 'unit', 'kg', 'target_segments', JSON_ARRAY('premium_shopper', 'professional_breeder'))),
  ('zoo', 'DOG-TREAT-001', 'Kachní proužky bez přidaného cukru 200 g', 'Šetrně sušené masové pamlsky s jednoduchým složením.', 189, 65, 1, 'treats', '200 g', JSON_OBJECT('brand', 'Ontario', 'weight', 200, 'unit', 'g', 'target_segments', JSON_ARRAY('premium_shopper', 'experiential_tester'))),
  ('zoo', 'DOG-COLLAR-RED', 'Designový obojek Urban červený M', 'Městský obojek s měkkým polstrováním a kovovou přezkou.', 549, 18, 1, 'equipment', 'M', JSON_OBJECT('brand', 'Dog Fantasy', 'target_segments', JSON_ARRAY('premium_shopper', 'experiential_tester'))),
  ('zoo', 'AQUA-SET-054', 'Akvarijní startovací set LED 54 l', 'Kompletní akvárium s filtrem, topítkem a LED osvětlením.', 2799, 9, 1, 'equipment', '54 l', JSON_OBJECT('brand', 'Aquael', 'weight', 54, 'unit', 'l', 'target_segments', JSON_ARRAY('beginner_aquarist', 'family_visitor'))),
  ('zoo', 'AQUA-COND-001', 'Přípravek na úpravu vody 250 ml', 'Neutralizuje chlor a těžké kovy při založení akvária.', 169, 42, 1, 'supplements', '250 ml', JSON_OBJECT('brand', 'Tetra', 'weight', 250, 'unit', 'ml', 'target_segments', JSON_ARRAY('beginner_aquarist'))),
  ('zoo', 'AQUA-BAC-001', 'Startovací bakterie pro akvária 100 ml', 'Bakteriální kultura pro rychlejší biologický start filtrace.', 229, 33, 1, 'supplements', '100 ml', JSON_OBJECT('brand', 'Aqua Start', 'weight', 100, 'unit', 'ml', 'target_segments', JSON_ARRAY('beginner_aquarist'))),
  ('zoo', 'DOG-ONT-20B', 'Ontario Adult Large Beef & Brown Rice 20 kg', 'Velké balení kompletního krmiva pro dospělé psy velkých plemen.', 1849, 31, 1, 'food', '20 kg', JSON_OBJECT('brand', 'Ontario', 'weight', 20, 'unit', 'kg', 'target_segments', JSON_ARRAY('professional_breeder', 'discount_specialist'))),
  ('zoo', 'DOG-SUPP-001', 'Kloubní výživa Chondro Complex 500 g', 'Koncentrovaná výživa kloubů s kolagenem a chondroprotektivy.', 899, 17, 1, 'supplements', '500 g', JSON_OBJECT('brand', 'Canvit', 'weight', 500, 'unit', 'g', 'target_segments', JSON_ARRAY('professional_breeder', 'premium_shopper'))),
  ('zoo', 'PUP-PAD-60', 'Výcvikové podložky pro štěňata 60 ks', 'Vysoce savé hygienické podložky s nepropustnou spodní vrstvou.', 429, 58, 1, 'hygiene', '60 ks', JSON_OBJECT('brand', 'Simple Solution', 'weight', 60, 'unit', 'ks', 'target_segments', JSON_ARRAY('puppy_rescuer'))),
  ('zoo', 'DOG-TOY-001', 'Odolná plnicí hračka Puppy M', 'Měkká a odolná hračka pro zabavení a zklidnění štěněte.', 329, 27, 1, 'toys', 'M', JSON_OBJECT('brand', 'KONG', 'target_segments', JSON_ARRAY('puppy_rescuer', 'experiential_tester'))),
  ('zoo', 'DOG-DENT-001', 'Dentální přetahovací lano 35 cm', 'Bavlněné lano pomáhající s hygienou chrupu a výměnou zubů.', 149, 74, 1, 'toys', '35 cm', JSON_OBJECT('brand', 'Trixie', 'target_segments', JSON_ARRAY('puppy_rescuer', 'experiential_tester'))),
  ('zoo', 'IMP-TREAT-001', 'Mini pamlsky Mix 100 g', 'Drobné pamlsky vhodné jako rychlá odměna a impulzní nákup.', 59, 120, 1, 'treats', '100 g', JSON_OBJECT('brand', 'Rasco', 'weight', 100, 'unit', 'g', 'target_segments', JSON_ARRAY('family_visitor', 'discount_specialist'))),
  ('zoo', 'BIRD-SNACK-001', 'Ovocná tyčinka pro andulky 2 ks', 'Křupavá doplňková pochoutka s ovocem a semeny.', 69, 88, 1, 'treats', '2 ks', JSON_OBJECT('brand', 'Vitakraft', 'weight', 2, 'unit', 'ks', 'target_segments', JSON_ARRAY('family_visitor'))),
  ('zoo', 'TERR-CRICKET-M', 'Cvrček domácí střední 40 ks', 'Živé krmivo pro plazy, obojživelníky a hmyzožravce.', 69, 45, 1, 'food', '40 ks', JSON_OBJECT('brand', 'Zoo Live', 'weight', 40, 'unit', 'ks', 'target_segments', JSON_ARRAY('terrarium_specialist'))),
  ('zoo', 'TERR-HEAT-075', 'Repti Planet Daylight Basking Spot 75 W', 'Topná bodová žárovka pro vytvoření výhřevného místa.', 89, 36, 1, 'equipment', '75 W', JSON_OBJECT('brand', 'Repti Planet', 'target_segments', JSON_ARRAY('terrarium_specialist'))),
  ('zoo', 'CAT-PATE-RAB', 'Kočičí paštika s králíkem 100 g', 'Jemná masová paštika pro vybíravé dospělé kočky.', 35, 144, 1, 'food', '100 g', JSON_OBJECT('brand', 'Mister Stuzzy', 'weight', 100, 'unit', 'g', 'target_segments', JSON_ARRAY('cat_loyalist', 'discount_specialist'))),
  ('zoo', 'CAT-LITTER-10', 'Magic Litter Bentonite Ultra White 10 l', 'Jemné hrudkující stelivo s vysokou absorpcí pachů.', 209, 62, 1, 'hygiene', '10 l', JSON_OBJECT('brand', 'Magic Litter', 'weight', 10, 'unit', 'l', 'target_segments', JSON_ARRAY('cat_loyalist', 'discount_specialist'))),
  ('zoo', 'DOG-TOY-INT', 'Interaktivní hlavolam pro psy Level 2', 'Hlavolam na pamlsky podporující soustředění a mentální aktivitu.', 499, 22, 1, 'toys', 'Level 2', JSON_OBJECT('brand', 'Nina Ottosson', 'target_segments', JSON_ARRAY('experiential_tester', 'premium_shopper'))),
  ('zoo', 'CAT-CAN-12', 'Multipack kočičích konzerv 12 × 400 g', 'Výhodné balení kompletního mokrého krmiva ve více příchutích.', 599, 41, 1, 'food', '12 × 400 g', JSON_OBJECT('brand', 'Brit Premium', 'weight', 4.8, 'unit', 'kg', 'target_segments', JSON_ARRAY('discount_specialist', 'community_rescuer', 'cat_loyalist'))),
  ('zoo', 'DOG-CAN-6', 'Konzervy pro psy 6 × 800 g', 'Množstevní balení masových konzerv pro dospělé psy.', 479, 39, 1, 'food', '6 × 800 g', JSON_OBJECT('brand', 'Rasco', 'weight', 4.8, 'unit', 'kg', 'target_segments', JSON_ARRAY('discount_specialist', 'community_rescuer'))),
  ('zoo', 'BIRD-SEED-20', 'Směs pro venkovní ptactvo 20 kg', 'Velkoobjemová směs semen pro přikrmování volně žijících ptáků.', 699, 28, 1, 'food', '20 kg', JSON_OBJECT('brand', 'Nature Land', 'weight', 20, 'unit', 'kg', 'target_segments', JSON_ARRAY('community_rescuer'))),
  ('zoo', 'CAT-ECO-24', 'Ekonomický multipack pro kočky 24 × 415 g', 'Velké balení základního mokrého krmiva pro každodenní péči o více koček.', 749, 34, 1, 'food', '24 × 415 g', JSON_OBJECT('brand', 'Fine Cat', 'weight', 9.96, 'unit', 'kg', 'target_segments', JSON_ARRAY('community_rescuer', 'discount_specialist')))
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `description` = VALUES(`description`),
  `price` = VALUES(`price`), `stock_quantity` = VALUES(`stock_quantity`),
  `published` = VALUES(`published`), `kind` = VALUES(`kind`),
  `variant` = VALUES(`variant`), `data` = VALUES(`data`), `deleted` = 0;

INSERT IGNORE INTO `product_category` (`product_id`, `category_id`) VALUES
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'ZOO-PREM-001'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-food')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'DOG-TREAT-001'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-treats')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'DOG-COLLAR-RED'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'AQUA-SET-054'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'aquarium-sets')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'AQUA-COND-001'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'aquarium-care')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'AQUA-BAC-001'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'aquarium-care')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'DOG-ONT-20B'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-food')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'DOG-SUPP-001'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-food')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'PUP-PAD-60'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-training')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'DOG-TOY-001'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-toys')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'DOG-DENT-001'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-toys')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'IMP-TREAT-001'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-treats')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'BIRD-SNACK-001'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'birds-food')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'TERR-CRICKET-M'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'terrarium-food')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'TERR-HEAT-075'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'terrarium-tech')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'CAT-PATE-RAB'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'cats-food')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'CAT-LITTER-10'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'cats-litter')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'DOG-TOY-INT'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-toys')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'CAT-CAN-12'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'cats-food')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'DOG-CAN-6'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'dogs-food')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'BIRD-SEED-20'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'wild-birds')),
  ((SELECT id FROM product WHERE franchise_code = 'zoo' AND sku = 'CAT-ECO-24'), (SELECT id FROM category WHERE franchise_code = 'zoo' AND syscode = 'cats-food'));

COMMIT;
