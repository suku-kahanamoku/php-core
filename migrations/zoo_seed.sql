-- zoo bootstrap/reference data, consolidated through 2026-09-28.
-- Insert missing records only; preserve passwords, edits and deleted tombstones.
-- IDs use tenant/natural keys, never fixed AUTO_INCREMENT values.
SET NAMES utf8mb4;
START TRANSACTION;

-- role
INSERT INTO `role` (`franchise_code`,`name`,`label`,`position`,`deleted`)
SELECT 'zoo','admin','Administrátor',10,0
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `franchise_code`='zoo' AND `name`='admin');
INSERT INTO `role` (`franchise_code`,`name`,`label`,`position`,`deleted`)
SELECT 'zoo','manager','Manažer',20,0
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `franchise_code`='zoo' AND `name`='manager');
INSERT INTO `role` (`franchise_code`,`name`,`label`,`position`,`deleted`)
SELECT 'zoo','user','Klient',30,0
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `franchise_code`='zoo' AND `name`='user');

-- enumeration
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zoo','vat_rate','standard','Základní sazba DPH','21',10,1,'{"rate": 21}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zoo' AND `type`='vat_rate' AND `syscode`='standard');

-- customer_profile
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',1,'premium_shopper','Rozmaznávač','Prémiový nakupující','Záleží jí na designu, kvalitě a zdraví domácího mazlíčka. Ráda zkouší novinky a prémiové řady.','Můj pes má lepší život než většina lidí.','Stylové oblečení, káva v ruce a designové vodítko.','Studuje složení BIO produktů a obměňuje doplňky podle trendů.','Vysoká hodnota košíku; dobře reaguje na holistická krmiva, limitované edice a designové doplňky.','Jsou tyto kachní proužky bez přidaného cukru? Rocky má citlivé bříško.','2450.00','Zdůraznit kvalitu surovin, design, novinky a exkluzivitu.',10,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper');
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',2,'beginner_aquarist','Zmatený prvoakvarista','Impulzivní začátečník','Začínající akvarista motivovaný přáním dětí. Potřebuje jednoduché řešení a srozumitelnou edukaci.','Syn chtěl rybičku, co se může pokazit?','Lehce panikaří a ukazuje inspiraci z telefonu.','Hledá hotové sety a vyžaduje pomoc s kompletní sestavou.','Silný cross-sell startovacího akvária, filtrace, chemie, krmiva a dekorací.','Musí akvárium opravdu běžet dva týdny bez ryb?','3200.00','Nabídnout kompletní balíček a jednoduchý návod krok za krokem.',20,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist');
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',3,'professional_breeder','Profesionální chovatel','Expert a specialista','Zkušený chovatel s hlubokou znalostí výživy, který přesně ví, co hledá.','O výživě a chovu vím více než výrobce.','Praktické oblečení, batoh a přímá cesta ke konkrétnímu regálu.','Porovnává složení, podíl bílkovin a mikronutrienty.','Stabilní objemový zákazník velkých balení a specializovaných doplňků.','Nová receptura snížila podíl chondroprotektiv o dvě desetiny procenta.','4100.00','Komunikovat přesná data, složení, objemová balení a dostupnost.',30,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder');
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',4,'puppy_rescuer','Záchranář se štěnětem','Řešitel krizových situací','Nová majitelka štěněte, která potřebuje rychlá a funkční řešení prvotního chaosu.','Totální vyčerpání smíchané s čistou láskou.','Sportovní oblečení a hyperaktivní štěně na vodítku.','Rychle hledá pomůcky pro výcvik, dentální hračky a hygienu.','Vysoký potenciál dlouhodobé věrnosti při správném vedení v prvním roce.','Máte něco, co ho zabaví déle než tři minuty?','1650.00','Nabídnout praktický štěněcí checklist a opakované připomínky nákupu.',40,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer');
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',5,'family_visitor','Sobotní tatínek','Rekreační rodinný návštěvník','Rodinný návštěvník, který bere prodejnu jako výlet a interaktivní program pro děti.','Potřebuji zabavit děti, než rodina dokončí nákup.','Rodič s dětmi, nejdéle se zdrží u ryb, ptáků a hlodavců.','Nakupuje menší impulzivní položky a drobné pamlsky.','Nižší košík, ale dobrý prostor pro impulzní nabídky a rodinné akce.','Podívej na toho hada, nechtěl bys ho do pokojíčku?','420.00','Ukazovat cenově dostupné drobnosti, zážitkové produkty a rodinné akce.',50,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor');
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',6,'terrarium_specialist','Pan terarista','Specializovaný nákupčí','Specialista na plazy a teraristiku s pravidelnou spotřebou živého krmiva a techniky.','Můj svět vyžaduje přesnou teplotu, vlhkost a živé krmivo.','Neformální styl, pragmatické a rychlé vystupování.','Míří přímo k živému krmivu, kontroluje stav a doplňuje techniku.','Pravidelná fixní spotřeba krmiva, osvětlení a topení.','Vezmu dvě krabičky středních cvrčků a jednu topnou žárovku.','980.00','Hlásit dostupnost živého krmiva a technické novinky bez obecného marketingu.',60,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist');
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',7,'cat_loyalist','Babička s kočičím královstvím','Věrný vztahový zákazník','Věrná zákaznice, pro kterou je kočka hlavním společníkem a rozhodují její oblíbené chutě.','Pro pohodlí své kočky udělám cokoli.','Ručně psaný seznam a pevně dané oblíbené značky.','Vyhledává osobní radu a pravidelně kupuje mokré krmivo a stelivo.','Velmi vysoká věrnost a předvídatelný opakovaný nákup.','Máte paštiku s králíkem? Jinou mi Mína ani neochutná.','890.00','Připomínat dostupnost oblíbených příchutí a nabídnout pravidelný odběr.',70,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist');
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',8,'experiential_tester','Tester všeho','Zážitkový zákazník','Nakupuje se psem a zapojuje ho přímo do výběru hraček, obojků a doplňků.','Nákup je zážitek pro mě i mého psa.','Aktivní pes na vodítku, který zkouší produkty z nižších polic.','Rozhoduje se emotivně podle okamžité reakce psa.','Silná náchylnost k neplánovaným nákupům interaktivních produktů.','Boby, vyber si, který obojek se ti líbí.','1350.00','Používat emotivní prezentaci, testovací vzorky a produkty vhodné k vyzkoušení.',80,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester');
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',9,'discount_specialist','Lovec slev','Cenově citlivý věrnostní specialista','Aktivně porovnává ceny, používá věrnostní program a plánuje nákupy podle akcí.','Mám aplikaci, kartu a přesně vím, co je dnes v akci.','Telefon s otevřenou aplikací a připravenými kupony.','Kombinuje akce, kupony a množstevní nabídky.','Vysoká digitální angažovanost a dobrá odezva na personalizované slevy.','Platí na tyto konzervy akce dva plus jeden?','1250.00','Posílat jasné cenové nabídky, množstevní slevy a časově omezené kupony.',90,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist');
INSERT INTO `customer_profile` (`franchise_code`,`profile_number`,`syscode`,`name`,`selection_need`,`summary`,`aura`,`visual`,`behavior`,`business_potential`,`typical_quote`,`average_basket`,`marketing_note`,`position`,`published`,`deleted`)
SELECT 'zoo',10,'community_rescuer','Záchranář z balkonu','Objemový komunitní nakupující','Stará se o volně žijící ptáky a toulavé kočky, proto hledá velká funkční balení za rozumnou cenu.','Pomáhám všude tam, kde je to potřeba.','Plně naložený vozík a rychlý praktický nákup.','Kupuje velká balení krmiv, steliv a celé kartony.','Vysoký obrat základního ekonomického sortimentu.','Máte ještě velká balení semínek pro sýkorky?','2800.00','Zdůraznit cenu za jednotku, velkoobjemová balení a dostupnost zásob.',100,1,0
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer');

-- customer_profile_preference
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),'animal','dogs',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper') AND `preference_type`='animal' AND `value`='dogs');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'),'animal','fish',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist') AND `preference_type`='animal' AND `value`='fish');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'),'animal','dogs',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder') AND `preference_type`='animal' AND `value`='dogs');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'),'animal','dogs',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer') AND `preference_type`='animal' AND `value`='dogs');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),'animal','birds',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor') AND `preference_type`='animal' AND `value`='birds');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),'animal','rodents',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor') AND `preference_type`='animal' AND `value`='rodents');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),'animal','fish',30
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor') AND `preference_type`='animal' AND `value`='fish');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),'animal','reptiles',40
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor') AND `preference_type`='animal' AND `value`='reptiles');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist'),'animal','reptiles',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist') AND `preference_type`='animal' AND `value`='reptiles');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'),'animal','cats',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist') AND `preference_type`='animal' AND `value`='cats');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),'animal','dogs',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester') AND `preference_type`='animal' AND `value`='dogs');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),'animal','dogs',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist') AND `preference_type`='animal' AND `value`='dogs');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),'animal','cats',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist') AND `preference_type`='animal' AND `value`='cats');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'),'animal','birds',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer') AND `preference_type`='animal' AND `value`='birds');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'),'animal','cats',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer') AND `preference_type`='animal' AND `value`='cats');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),'product_kind','food',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper') AND `preference_type`='product_kind' AND `value`='food');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),'product_kind','treats',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper') AND `preference_type`='product_kind' AND `value`='treats');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),'product_kind','equipment',30
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper') AND `preference_type`='product_kind' AND `value`='equipment');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'),'product_kind','equipment',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist') AND `preference_type`='product_kind' AND `value`='equipment');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'),'product_kind','food',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist') AND `preference_type`='product_kind' AND `value`='food');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'),'product_kind','food',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder') AND `preference_type`='product_kind' AND `value`='food');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'),'product_kind','supplements',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder') AND `preference_type`='product_kind' AND `value`='supplements');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'),'product_kind','hygiene',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer') AND `preference_type`='product_kind' AND `value`='hygiene');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'),'product_kind','toys',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer') AND `preference_type`='product_kind' AND `value`='toys');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'),'product_kind','equipment',30
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer') AND `preference_type`='product_kind' AND `value`='equipment');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),'product_kind','treats',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor') AND `preference_type`='product_kind' AND `value`='treats');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),'product_kind','toys',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor') AND `preference_type`='product_kind' AND `value`='toys');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist'),'product_kind','food',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist') AND `preference_type`='product_kind' AND `value`='food');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist'),'product_kind','equipment',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist') AND `preference_type`='product_kind' AND `value`='equipment');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'),'product_kind','food',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist') AND `preference_type`='product_kind' AND `value`='food');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'),'product_kind','hygiene',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist') AND `preference_type`='product_kind' AND `value`='hygiene');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),'product_kind','toys',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester') AND `preference_type`='product_kind' AND `value`='toys');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),'product_kind','equipment',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester') AND `preference_type`='product_kind' AND `value`='equipment');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),'product_kind','treats',30
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester') AND `preference_type`='product_kind' AND `value`='treats');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),'product_kind','food',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist') AND `preference_type`='product_kind' AND `value`='food');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),'product_kind','hygiene',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist') AND `preference_type`='product_kind' AND `value`='hygiene');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'),'product_kind','food',10
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer') AND `preference_type`='product_kind' AND `value`='food');
INSERT INTO `customer_profile_preference` (`customer_profile_id`,`preference_type`,`value`,`position`)
SELECT (SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'),'product_kind','hygiene',20
WHERE NOT EXISTS (SELECT 1 FROM `customer_profile_preference` WHERE `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer') AND `preference_type`='product_kind' AND `value`='hygiene');

-- category
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'dogs','Psi','Krmivo, pamlsky, hračky a potřeby pro psy',10,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'cats','Kočky','Krmivo, stelivo, hračky a potřeby pro kočky',20,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'rodents','Drobní savci','Sortiment pro hlodavce a další drobné savce',30,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='rodents');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'birds','Ptáci','Krmivo, klece a potřeby pro ptáky',40,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='birds');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'fish','Akvaristika','Akvária, technika, péče o vodu a krmivo',50,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='fish');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'reptiles','Teraristika','Terária, živé krmivo, osvětlení a vytápění',60,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='reptiles');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'horses','Koně','Krmivo, doplňky a potřeby pro koně',70,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='horses');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'product-food','Krmivo','Krmiva pro všechna zvířata.',100,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'product-treats','Pamlsky','Pamlsky a odměny pro zvířata.',110,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-treats');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'product-toys','Hračky','Hračky a pomůcky pro zabavení zvířat.',120,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-toys');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'product-hygiene','Hygiena','Hygienické potřeby, steliva a péče.',130,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-hygiene');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'product-equipment','Chovatelské potřeby','Vybavení a chovatelské potřeby.',140,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-equipment');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'product-supplements','Doplňky stravy','Vitamíny, minerály a další doplňky stravy.',150,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-supplements');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',NULL,'product-other','Ostatní','Ostatní produkty.',160,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-other');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs'),'dogs-food','Krmivo pro psy','Granule, konzervy a speciální diety',11,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-food');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs'),'dogs-treats','Pamlsky pro psy','Tréninkové a funkční pamlsky',12,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-treats');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs'),'dogs-toys','Hračky pro psy','Interaktivní, dentální a odolné hračky',13,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-toys');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs'),'dogs-training','Výcvik a hygiena štěňat','Podložky, pomůcky a výcvikové potřeby',14,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-training');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats'),'cats-food','Krmivo pro kočky','Granule, kapsičky, konzervy a paštiky',21,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-food');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats'),'cats-litter','Steliva pro kočky','Hrudkující a přírodní steliva',22,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-litter');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats'),'cats-equipment','Doplňky pro kočky','Pelíšky, misky, škrabadla a designové doplňky',23,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-equipment');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='rodents'),'rodents-bedding','Podestýlky pro drobné savce','Přírodní a absorpční podestýlky',31,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='rodents-bedding');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='birds'),'birds-food','Krmivo pro ptáky','Směsi, semena, tyčinky a doplňky',41,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='birds-food');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='birds'),'wild-birds','Volně žijící ptáci','Velká balení směsí pro venkovní ptactvo',42,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='wild-birds');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='fish'),'aquarium-sets','Akvarijní sety','Kompletní sety pro začátečníky',51,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='aquarium-sets');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='fish'),'aquarium-care','Péče o akvárium','Chemie, bakterie a úprava vody',52,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='aquarium-care');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='reptiles'),'terrarium-food','Krmivo pro terarijní zvířata','Živé a doplňkové krmivo',61,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='terrarium-food');
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zoo',(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='reptiles'),'terrarium-tech','Terarijní technika','Osvětlení, topení a regulace klimatu',62,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='terrarium-tech');

-- user
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Zoo','Admin','admin@zoo.local',NULL,'$2y$12$nmRE/TC4K3OYnBRaqnLfz.IGMYHjt1RVgej7139P7u7ijXz0epGWy',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='admin'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='admin@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Karolína','Nováková','karolina.novakova@zoo.local','+420 601 111 101','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='karolina.novakova@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Martin','Dvořák','martin.dvorak@zoo.local','+420 601 111 102','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='martin.dvorak@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Petr','Kučera','petr.kucera@zoo.local','+420 601 111 103','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='petr.kucera@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Lucie','Benešová','lucie.benesova@zoo.local','+420 601 111 104','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='lucie.benesova@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Tomáš','Král','tomas.kral@zoo.local','+420 601 111 105','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='tomas.kral@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Radek','Veselý','radek.vesely@zoo.local','+420 601 111 106','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='radek.vesely@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Marie','Černá','marie.cerna@zoo.local','+420 601 111 107','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='marie.cerna@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Jakub','Procházka','jakub.prochazka@zoo.local','+420 601 111 108','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='jakub.prochazka@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Ivana','Horáková','ivana.horakova@zoo.local','+420 601 111 109','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='ivana.horakova@zoo.local');
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zoo','Alena','Svobodová','alena.svobodova@zoo.local','+420 601 111 110','$2y$12$J0P0lGKwBFIPbV03dvO5aee5yKDwPxgYxUNgR4zVHlY5x8XVvaTCO',(SELECT `id` FROM `role` WHERE `franchise_code`='zoo' AND `name`='user'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zoo' AND `email`='alena.svobodova@zoo.local');

-- product
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','ZOO-PREM-001','Holistické granule Fresh Duck & Herbs 10 kg','Prémiové holistické krmivo bez obilovin pro citlivé dospělé psy.','1899.00',24,1,0,NULL,NULL,'10 kg','{"unit": "kg", "brand": "Zoo Selection", "weight": 10}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='ZOO-PREM-001');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','DOG-TREAT-001','Kachní proužky bez přidaného cukru 200 g','Šetrně sušené masové pamlsky s jednoduchým složením.','189.00',65,1,0,NULL,NULL,'200 g','{"unit": "g", "brand": "Ontario", "weight": 200}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TREAT-001');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','DOG-COLLAR-RED','Designový obojek Urban červený M','Městský obojek s měkkým polstrováním a kovovou přezkou.','549.00',18,1,0,NULL,NULL,'M','{"brand": "Dog Fantasy"}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-COLLAR-RED');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','AQUA-SET-054','Akvarijní startovací set LED 54 l','Kompletní akvárium s filtrem, topítkem a LED osvětlením.','2799.00',9,1,0,NULL,NULL,'54 l','{"unit": "l", "brand": "Aquael", "weight": 54}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-SET-054');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','AQUA-COND-001','Přípravek na úpravu vody 250 ml','Neutralizuje chlor a těžké kovy při založení akvária.','169.00',42,1,0,NULL,NULL,'250 ml','{"unit": "ml", "brand": "Tetra", "weight": 250}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-COND-001');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','AQUA-BAC-001','Startovací bakterie pro akvária 100 ml','Bakteriální kultura pro rychlejší biologický start filtrace.','229.00',33,1,0,NULL,NULL,'100 ml','{"unit": "ml", "brand": "Aqua Start", "weight": 100}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-BAC-001');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','DOG-ONT-20B','Ontario Adult Large Beef & Brown Rice 20 kg','Velké balení kompletního krmiva pro dospělé psy velkých plemen.','1849.00',31,1,0,NULL,NULL,'20 kg','{"unit": "kg", "brand": "Ontario", "weight": 20}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-ONT-20B');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','DOG-SUPP-001','Kloubní výživa Chondro Complex 500 g','Koncentrovaná výživa kloubů s kolagenem a chondroprotektivy.','899.00',17,1,0,NULL,NULL,'500 g','{"unit": "g", "brand": "Canvit", "weight": 500}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-SUPP-001');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','PUP-PAD-60','Výcvikové podložky pro štěňata 60 ks','Vysoce savé hygienické podložky s nepropustnou spodní vrstvou.','429.00',58,1,0,NULL,NULL,'60 ks','{"unit": "ks", "brand": "Simple Solution", "weight": 60}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='PUP-PAD-60');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','DOG-TOY-001','Odolná plnicí hračka Puppy M','Měkká a odolná hračka pro zabavení a zklidnění štěněte.','329.00',27,1,0,NULL,NULL,'M','{"brand": "KONG"}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-001');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','DOG-DENT-001','Dentální přetahovací lano 35 cm','Bavlněné lano pomáhající s hygienou chrupu a výměnou zubů.','149.00',74,1,0,NULL,NULL,'35 cm','{"brand": "Trixie"}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-DENT-001');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','IMP-TREAT-001','Mini pamlsky Mix 100 g','Drobné pamlsky vhodné jako rychlá odměna a impulzní nákup.','59.00',120,1,0,NULL,NULL,'100 g','{"unit": "g", "brand": "Rasco", "weight": 100}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='IMP-TREAT-001');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','BIRD-SNACK-001','Ovocná tyčinka pro andulky 2 ks','Křupavá doplňková pochoutka s ovocem a semeny.','69.00',88,1,0,NULL,NULL,'2 ks','{"unit": "ks", "brand": "Vitakraft", "weight": 2}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SNACK-001');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','TERR-CRICKET-M','Cvrček domácí střední 40 ks','Živé krmivo pro plazy, obojživelníky a hmyzožravce.','69.00',45,1,0,NULL,NULL,'40 ks','{"unit": "ks", "brand": "Zoo Live", "weight": 40}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-CRICKET-M');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','TERR-HEAT-075','Repti Planet Daylight Basking Spot 75 W','Topná bodová žárovka pro vytvoření výhřevného místa.','89.00',36,1,0,NULL,NULL,'75 W','{"brand": "Repti Planet"}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-HEAT-075');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','CAT-PATE-RAB','Kočičí paštika s králíkem 100 g','Jemná masová paštika pro vybíravé dospělé kočky.','35.00',144,1,0,NULL,NULL,'100 g','{"unit": "g", "brand": "Mister Stuzzy", "weight": 100}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-PATE-RAB');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','CAT-LITTER-10','Magic Litter Bentonite Ultra White 10 l','Jemné hrudkující stelivo s vysokou absorpcí pachů.','209.00',62,1,0,NULL,NULL,'10 l','{"unit": "l", "brand": "Magic Litter", "weight": 10}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-LITTER-10');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','DOG-TOY-INT','Interaktivní hlavolam pro psy Level 2','Hlavolam na pamlsky podporující soustředění a mentální aktivitu.','499.00',22,1,0,NULL,NULL,'Level 2','{"brand": "Nina Ottosson"}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-INT');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','CAT-CAN-12','Multipack kočičích konzerv 12 × 400 g','Výhodné balení kompletního mokrého krmiva ve více příchutích.','599.00',41,1,0,NULL,NULL,'12 × 400 g','{"unit": "kg", "brand": "Brit Premium", "weight": 4.8}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','DOG-CAN-6','Konzervy pro psy 6 × 800 g','Množstevní balení masových konzerv pro dospělé psy.','479.00',39,1,0,NULL,NULL,'6 × 800 g','{"unit": "kg", "brand": "Rasco", "weight": 4.8}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-CAN-6');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','BIRD-SEED-20','Směs pro venkovní ptactvo 20 kg','Velkoobjemová směs semen pro přikrmování volně žijících ptáků.','699.00',28,1,0,NULL,NULL,'20 kg','{"unit": "kg", "brand": "Nature Land", "weight": 20}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SEED-20');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zoo','CAT-ECO-24','Ekonomický multipack pro kočky 24 × 415 g','Velké balení základního mokrého krmiva pro každodenní péči o více koček.','749.00',34,1,0,NULL,NULL,'24 × 415 g','{"unit": "kg", "brand": "Fine Cat", "weight": 9.96}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-ECO-24');

-- user_customer_profile
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='karolina.novakova@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='karolina.novakova@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'));
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='martin.dvorak@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='martin.dvorak@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'));
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='petr.kucera@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='petr.kucera@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'));
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='lucie.benesova@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='lucie.benesova@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'));
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='tomas.kral@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='tomas.kral@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'));
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='radek.vesely@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='radek.vesely@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist'));
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='marie.cerna@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='marie.cerna@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'));
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='jakub.prochazka@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='jakub.prochazka@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'));
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='ivana.horakova@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='ivana.horakova@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'));
INSERT INTO `user_customer_profile` (`franchise_code`,`user_id`,`customer_profile_id`,`position`)
SELECT 'zoo',(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='alena.svobodova@zoo.local'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'),1
WHERE NOT EXISTS (SELECT 1 FROM `user_customer_profile` WHERE `user_id`=(SELECT `id` FROM `user` WHERE `franchise_code`='zoo' AND `email`='alena.svobodova@zoo.local') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'));

-- product_category
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-COLLAR-RED'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-COLLAR-RED') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='ZOO-PREM-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='ZOO-PREM-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-ONT-20B'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-ONT-20B') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-CRICKET-M'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-CRICKET-M') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-PATE-RAB'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-PATE-RAB') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-CAN-6'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-CAN-6') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SEED-20'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SEED-20') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-ECO-24'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-ECO-24') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TREAT-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-treats')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TREAT-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-treats'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='IMP-TREAT-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-treats')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='IMP-TREAT-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-treats'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SNACK-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-treats')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SNACK-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-treats'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-toys')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-toys'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-DENT-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-toys')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-DENT-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-toys'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-INT'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-toys')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-INT') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-toys'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='PUP-PAD-60'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-hygiene')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='PUP-PAD-60') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-hygiene'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-LITTER-10'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-hygiene')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-LITTER-10') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-hygiene'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-COLLAR-RED'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-equipment')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-COLLAR-RED') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-equipment'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-SET-054'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-equipment')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-SET-054') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-equipment'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-HEAT-075'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-equipment')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-HEAT-075') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-equipment'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-COND-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-supplements')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-COND-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-supplements'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-BAC-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-supplements')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-BAC-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-supplements'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-SUPP-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-supplements')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-SUPP-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='product-supplements'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='ZOO-PREM-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='ZOO-PREM-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-ONT-20B'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-ONT-20B') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-SUPP-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-SUPP-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-CAN-6'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-CAN-6') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TREAT-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-treats')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TREAT-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-treats'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='IMP-TREAT-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-treats')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='IMP-TREAT-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-treats'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-toys')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-toys'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-DENT-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-toys')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-DENT-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-toys'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-INT'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-toys')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-INT') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-toys'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='PUP-PAD-60'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-training')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='PUP-PAD-60') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='dogs-training'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-PATE-RAB'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-PATE-RAB') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-ECO-24'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-ECO-24') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-LITTER-10'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-litter')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-LITTER-10') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='cats-litter'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SNACK-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='birds-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SNACK-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='birds-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SEED-20'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='wild-birds')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SEED-20') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='wild-birds'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-SET-054'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='aquarium-sets')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-SET-054') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='aquarium-sets'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-COND-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='aquarium-care')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-COND-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='aquarium-care'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-BAC-001'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='aquarium-care')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-BAC-001') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='aquarium-care'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-CRICKET-M'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='terrarium-food')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-CRICKET-M') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='terrarium-food'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-HEAT-075'),(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='terrarium-tech')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-HEAT-075') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zoo' AND `syscode`='terrarium-tech'));

-- product_customer_profile_probability
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='ZOO-PREM-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='ZOO-PREM-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='ZOO-PREM-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='ZOO-PREM-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TREAT-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TREAT-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TREAT-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TREAT-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-COLLAR-RED'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-COLLAR-RED') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-COLLAR-RED'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-COLLAR-RED') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-SET-054'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-SET-054') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-SET-054'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-SET-054') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-COND-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-COND-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-BAC-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='AQUA-BAC-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='beginner_aquarist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-ONT-20B'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-ONT-20B') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-ONT-20B'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-ONT-20B') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-SUPP-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-SUPP-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-SUPP-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-SUPP-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='professional_breeder'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='PUP-PAD-60'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='PUP-PAD-60') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-DENT-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-DENT-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='puppy_rescuer'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-DENT-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-DENT-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='IMP-TREAT-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='IMP-TREAT-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='IMP-TREAT-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='IMP-TREAT-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SNACK-001'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SNACK-001') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='family_visitor'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-CRICKET-M'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-CRICKET-M') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-HEAT-075'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='TERR-HEAT-075') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='terrarium_specialist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-PATE-RAB'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-PATE-RAB') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-PATE-RAB'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-PATE-RAB') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-LITTER-10'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-LITTER-10') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-LITTER-10'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-LITTER-10') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-INT'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-INT') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='premium_shopper'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-INT'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-TOY-INT') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='experiential_tester'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='cat_loyalist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-CAN-12') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-CAN-6'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-CAN-6') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-CAN-6'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='DOG-CAN-6') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SEED-20'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='BIRD-SEED-20') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-ECO-24'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-ECO-24') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='discount_specialist'));
INSERT INTO `product_customer_profile_probability` (`franchise_code`,`product_id`,`customer_profile_id`,`probability_percent`)
SELECT 'zoo',(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-ECO-24'),(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'),100
WHERE NOT EXISTS (SELECT 1 FROM `product_customer_profile_probability` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zoo' AND `sku`='CAT-ECO-24') AND `customer_profile_id`=(SELECT `id` FROM `customer_profile` WHERE `franchise_code`='zoo' AND `syscode`='community_rescuer'));

COMMIT;
