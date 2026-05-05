-- ════════════════════════════════════════════════════════════
-- seed.sql — demo data for php_core (franchise_code = 'default')
-- Admin password: 12345678
-- ════════════════════════════════════════════════════════════

-- ── Seed: default roles ───────────────────────────────────
INSERT IGNORE INTO `role` (`franchise_code`, `name`, `label`, `position`) VALUES
  ('default', 'admin',   'Admin',   10),
  ('default', 'manager', 'Manager', 20),
  ('default', 'user',    'User',    30);

-- ── Seed: default enumerations ────────────────────────────
INSERT IGNORE INTO `enumeration` (`franchise_code`, `type`, `syscode`, `label`, `value`, `position`) VALUES
  -- Order statuses
  ('default', 'order_status', 'pending',    'Pending',    'pending',    10),
  ('default', 'order_status', 'confirmed',  'Confirmed',  'confirmed',  20),
  ('default', 'order_status', 'processing', 'Processing', 'processing', 30),
  ('default', 'order_status', 'shipped',    'Shipped',    'shipped',    40),
  ('default', 'order_status', 'delivered',  'Delivered',  'delivered',  50),
  ('default', 'order_status', 'cancelled',  'Cancelled',  'cancelled',  60),
  ('default', 'order_status', 'refunded',   'Refunded',   'refunded',   70),
  -- Invoice statuses
  ('default', 'invoice_status', 'draft',     'Draft',     'draft',     10),
  ('default', 'invoice_status', 'issued',    'Issued',    'issued',    20),
  ('default', 'invoice_status', 'paid',      'Paid',      'paid',      30),
  ('default', 'invoice_status', 'overdue',   'Overdue',   'overdue',   40),
  ('default', 'invoice_status', 'cancelled', 'Cancelled', 'cancelled', 50),
  ('default', 'invoice_status', 'refunded',  'Refunded',  'refunded',  60),
  -- Payment methods
  ('default', 'payment_method', 'bank_transfer', 'Bank Transfer', 'bank_transfer', 10),
  ('default', 'payment_method', 'cash',          'Cash',          'cash',          20),
  ('default', 'payment_method', 'card',          'Card',          'card',          30),
  ('default', 'payment_method', 'online',        'Online',        'online',        40),
  -- Currencies
  ('default', 'currency', 'CZK', 'Czech Koruna', 'CZK', 10),
  ('default', 'currency', 'EUR', 'Euro',         'EUR', 20),
  ('default', 'currency', 'USD', 'US Dollar',    'USD', 30),
  -- VAT rates
  ('default', 'vat_rate', '0',  '0%',  '0',  10),
  ('default', 'vat_rate', '10', '10%', '10', 20),
  ('default', 'vat_rate', '12', '12%', '12', 30),
  ('default', 'vat_rate', '21', '21%', '21', 40);

-- ── Seed: admin user (password: 12345678) ─────────────────
INSERT IGNORE INTO `user` (`franchise_code`, `first_name`, `last_name`, `email`, `password`, `role_id`) VALUES
  ('default', 'Admin', 'User', 'admin@example.com',
   '$2y$12$VV9nkI2IT23cCyE0RhGdJejQS1ynute9M01xhcpC3HqoAP.1d1PeG',
   (SELECT id FROM role WHERE franchise_code = 'default' AND name = 'admin'));

-- ── Seed: regular users (password: 12345678) ─────────────
INSERT IGNORE INTO `user` (`franchise_code`, `first_name`, `last_name`, `email`, `password`, `role_id`, `phone`) VALUES
  ('default', 'Jan',    'Novák',    'jan.novak@example.com',    '$2y$12$VV9nkI2IT23cCyE0RhGdJejQS1ynute9M01xhcpC3HqoAP.1d1PeG', (SELECT id FROM role WHERE franchise_code = 'default' AND name = 'user'), '+420 601 111 001'),
  ('default', 'Marie',  'Svobodová','marie.svobodova@example.com','$2y$12$VV9nkI2IT23cCyE0RhGdJejQS1ynute9M01xhcpC3HqoAP.1d1PeG',(SELECT id FROM role WHERE franchise_code = 'default' AND name = 'user'), '+420 601 111 002'),
  ('default', 'Petr',   'Dvořák',   'petr.dvorak@example.com',   '$2y$12$VV9nkI2IT23cCyE0RhGdJejQS1ynute9M01xhcpC3HqoAP.1d1PeG',(SELECT id FROM role WHERE franchise_code = 'default' AND name = 'user'), '+420 601 111 003'),
  ('default', 'Lucie',  'Procházková','lucie.prochazkova@example.com','$2y$12$VV9nkI2IT23cCyE0RhGdJejQS1ynute9M01xhcpC3HqoAP.1d1PeG',(SELECT id FROM role WHERE franchise_code = 'default' AND name = 'user'), '+420 601 111 004'),
  ('default', 'Tomáš',  'Kučera',   'tomas.kucera@example.com',  '$2y$12$VV9nkI2IT23cCyE0RhGdJejQS1ynute9M01xhcpC3HqoAP.1d1PeG',(SELECT id FROM role WHERE franchise_code = 'default' AND name = 'user'), '+420 601 111 005'),
  ('default', 'Eva',    'Veselá',   'eva.vesela@example.com',    '$2y$12$VV9nkI2IT23cCyE0RhGdJejQS1ynute9M01xhcpC3HqoAP.1d1PeG',(SELECT id FROM role WHERE franchise_code = 'default' AND name = 'user'), '+420 601 111 006');

-- ── Seed: addresses ───────────────────────────────────────
INSERT IGNORE INTO `address` (`franchise_code`, `user_id`, `type`, `name`, `street`, `city`, `zip`, `country`, `is_default`) VALUES
  ('default', (SELECT id FROM user WHERE email='jan.novak@example.com'),           'billing',  'Jan Novák',           'Václavské náměstí 1',   'Praha',      '110 00', 'CZ', 1),
  ('default', (SELECT id FROM user WHERE email='jan.novak@example.com'),           'shipping', 'Jan Novák',           'Václavské náměstí 1',   'Praha',      '110 00', 'CZ', 1),
  ('default', (SELECT id FROM user WHERE email='marie.svobodova@example.com'),     'billing',  'Marie Svobodová',     'Masarykova 12',         'Brno',       '602 00', 'CZ', 1),
  ('default', (SELECT id FROM user WHERE email='marie.svobodova@example.com'),     'shipping', 'Marie Svobodová',     'Masarykova 12',         'Brno',       '602 00', 'CZ', 1),
  ('default', (SELECT id FROM user WHERE email='petr.dvorak@example.com'),         'billing',  'Petr Dvořák',         'Nádražní 5',            'Ostrava',    '702 00', 'CZ', 1),
  ('default', (SELECT id FROM user WHERE email='petr.dvorak@example.com'),         'shipping', 'Petr Dvořák',         'Nádražní 5',            'Ostrava',    '702 00', 'CZ', 1),
  ('default', (SELECT id FROM user WHERE email='lucie.prochazkova@example.com'),   'billing',  'Lucie Procházková',   'Jiráskovo nám. 3',      'Plzeň',      '301 00', 'CZ', 1),
  ('default', (SELECT id FROM user WHERE email='tomas.kucera@example.com'),        'billing',  'Tomáš Kučera',        'Palackého třída 7',     'Olomouc',    '779 00', 'CZ', 1),
  ('default', (SELECT id FROM user WHERE email='tomas.kucera@example.com'),        'shipping', 'Tomáš Kučera',        'Palackého třída 7',     'Olomouc',    '779 00', 'CZ', 1),
  ('default', (SELECT id FROM user WHERE email='eva.vesela@example.com'),          'billing',  'Eva Veselá',          'Náměstí Republiky 2',   'České Budějovice', '370 01', 'CZ', 1);

-- ── Seed: categories ──────────────────────────────────────
-- Parent categories
INSERT IGNORE INTO `category` (`franchise_code`, `parent_id`, `syscode`, `name`, `description`, `position`) VALUES
  ('default', NULL, 'cervena_vina',   'Červená vína',         'Moravská a česká červená vína ze špičkových vinařství',        10),
  ('default', NULL, 'bila_vina',      'Bílá vína',            'Aromatická a svěží bílá vína z různých odrůd a oblastí',       20),
  ('default', NULL, 'rose_vina',      'Rosé vína',            'Lehká a svěží rosé vína ideální pro letní večery',             30),
  ('default', NULL, 'sumiva_vina',    'Šumivá vína',          'Sekty a šumivá vína pro každou slavnostní příležitost',        40),
  ('default', NULL, 'dezertni_vina',  'Dezertní vína',        'Výjimečné dezertní a přívlastkové rarity výběru z bobulí',     50),
  ('default', NULL, 'akcove_sety',    'Akce a dárkové sety',  'Výhodné sety a dárkové balení pro milovníky vína',             60);

-- Child categories
SET @c_red    = (SELECT id FROM category WHERE franchise_code='default' AND syscode='cervena_vina');
SET @c_white  = (SELECT id FROM category WHERE franchise_code='default' AND syscode='bila_vina');
SET @c_rose   = (SELECT id FROM category WHERE franchise_code='default' AND syscode='rose_vina');
SET @c_spark  = (SELECT id FROM category WHERE franchise_code='default' AND syscode='sumiva_vina');
SET @c_desert = (SELECT id FROM category WHERE franchise_code='default' AND syscode='dezertni_vina');

INSERT IGNORE INTO `category` (`franchise_code`, `parent_id`, `syscode`, `name`, `description`, `position`) VALUES
  ('default', @c_red,    'moravska_cervena', 'Moravská červená',    'Červená vína z moravských vinařských podoblastí',         10),
  ('default', @c_red,    'ceska_cervena',    'Česká červená',       'Červená vína ze středočeských vinařských oblastí',        20),
  ('default', @c_white,  'moravska_bila',    'Moravská bílá',       'Bílá vína z Mikulovské, Znojemské a Slovácké podoblasti', 10),
  ('default', @c_white,  'ceska_bila',       'Česká bílá',          'Bílá vína z Litoměřické a Mělnické podoblasti',           20),
  ('default', @c_rose,   'moravske_rose',    'Moravské rosé',       'Rosé vína z Moravy lisovaná na bílo',                     10),
  ('default', @c_spark,  'sekty',            'Moravské sekty',      'Šumivá vína tradiční i moderní metodou výroby',           10),
  ('default', @c_desert, 'vyber_bobuli',     'Výběr z bobulí',      'Přívlastková vína výběr z bobulí, ledové a slámové',      10);

-- ── Seed: products (wines) ────────────────────────────────
-- kind  = wine type: red | white | rosé | sparkling | dessert | orange
-- color = red | white | rosé | orange | yellow | pink
-- variant = bottle size: 0.75l | 1.5l | 0.375l | 3.0l
-- data  = full wine attributes (ročník, odrůda, vinařství, oblast, kvalita, alkohol, cukor, kyselost, ...)

INSERT IGNORE INTO `product` (`franchise_code`, `sku`, `name`, `description`, `price`, `vat_rate`, `stock_quantity`, `is_active`, `kind`, `color`, `variant`, `data`) VALUES

  -- ── Červená vína – Moravská ──────────────────────────────
  ('default', 'WN-R001', 'Frankovka výběr z hroznů 2021',
   'Plné, tanické víno s vůní černého rybízu, třešní a jemným dřevem. Dlouhý teplý závěr.',
   389.00, 21.00, 48, 1, 'red', 'red', '0.75l',
   '{"year":2021,"volume":0.75,"quality":"Výběr z hroznů","region":"Moravská vinařská oblast","subregion":"Velkopavlovická","village":"Bořetice","winery":"Vinařství Volařík","grape":"Frankovka","alcohol":13.5,"sugar":"suché","residual_sugar":2.1,"acidity":5.8,"serving_temp":"16–18 °C","food_pairing":"hovězí, zvěřina, zralé sýry","ean":"8594001110012","awards":["Salon vín ČR 2022 – zlatá","Vinoforum 2022 – stříbrná"]}'),

  ('default', 'WN-R002', 'Cabernet Sauvignon pozdní sběr 2020',
   'Elegantní červené víno s aromatem černého rybízu, fialky a vanilky. Jemné třísloviny.',
   429.00, 21.00, 32, 1, 'red', 'red', '0.75l',
   '{"year":2020,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Mikulov","winery":"Vinařství Tanzberg","grape":"Cabernet Sauvignon","alcohol":13.0,"sugar":"suché","residual_sugar":1.8,"acidity":5.5,"serving_temp":"17–19 °C","food_pairing":"jehněčí, pečené hovězí, tvrdé sýry","ean":"8594002220023","awards":["Decanter 2021 – stříbrná","Vinaria 2021 – výborné"]}'),

  ('default', 'WN-R003', 'Merlot pozdní sběr 2021',
   'Měkké, sametové víno s vůní švestky, kakaa a bylin. Harmonický a dlouhý závěr.',
   359.00, 21.00, 56, 1, 'red', 'red', '0.75l',
   '{"year":2021,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Valtice","winery":"Château Valtice","grape":"Merlot","alcohol":13.0,"sugar":"suché","residual_sugar":2.4,"acidity":5.6,"serving_temp":"16–18 °C","food_pairing":"svíčková, pizza, paštiky","ean":"8594003330034","awards":["Vinoforum 2022 – zlatá"]}'),

  ('default', 'WN-R004', 'Zweigelt pozdní sběr 2022',
   'Šťavnaté červené víno s vůní třešní, pepře a lesních plodů. Svěží a přístupné.',
   299.00, 21.00, 74, 1, 'red', 'red', '0.75l',
   '{"year":2022,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Velkopavlovická","village":"Němčičky","winery":"Vinařství Spielberg","grape":"Zweigelt","alcohol":12.5,"sugar":"suché","residual_sugar":2.0,"acidity":6.0,"serving_temp":"15–17 °C","food_pairing":"pasta, grilované maso, polotvrdé sýry","ean":"8594004440045"}'),

  ('default', 'WN-R005', 'Pinot Noir výběr z hroznů 2020',
   'Jemné, hedvábné víno s vůní malin, třešní a drobného koření. VOC Mikulov.',
   549.00, 21.00, 18, 1, 'red', 'red', '0.75l',
   '{"year":2020,"volume":0.75,"quality":"VOC – Výběr z hroznů","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Popice","winery":"Vinařství Sonberk","grape":"Pinot Noir","alcohol":13.5,"sugar":"suché","residual_sugar":1.5,"acidity":5.9,"serving_temp":"15–17 °C","food_pairing":"kachna, losos, jemné sýry","ean":"8594005550056","awards":["Salon vín ČR 2021 – zlatá","Mundus Vini 2021 – zlatá"]}'),

  ('default', 'WN-R006', 'Neronet jakostní 2022',
   'Tmavě rubínové víno s intenzivním aromaem švestek, kávy a kakaa. Moravský unikát.',
   249.00, 21.00, 62, 1, 'red', 'red', '0.75l',
   '{"year":2022,"volume":0.75,"quality":"Jakostní víno","region":"Moravská vinařská oblast","subregion":"Slovácká","village":"Strážnice","winery":"Dobrá Vinice","grape":"Neronet","alcohol":12.0,"sugar":"suché","residual_sugar":2.8,"acidity":6.1,"serving_temp":"14–16 °C","food_pairing":"svíčková, hovězí guláš, čokoládový dezert","ean":"8594006660067"}'),

  ('default', 'WN-R007', 'Frankovka pozdní sběr 2021 Magnum',
   'Dárkový magnum formát plného Frankovky. Ideální pro slavnostní příležitosti.',
   690.00, 21.00, 12, 1, 'red', 'red', '1.5l',
   '{"year":2021,"volume":1.5,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Velkopavlovická","village":"Bořetice","winery":"Vinařství Volařík","grape":"Frankovka","alcohol":13.0,"sugar":"suché","residual_sugar":2.3,"acidity":5.7,"serving_temp":"16–18 °C","food_pairing":"zvěřina, hovězí na víně, zralé sýry","ean":"8594001110051"}'),

  -- ── Červená vína – Česká ──────────────────────────────────
  ('default', 'WN-R008', 'Zweigeltrebe pozdní sběr 2021',
   'Červené víno z Mělnické oblasti s vůní višní, borůvek a jemného koření.',
   319.00, 21.00, 28, 1, 'red', 'red', '0.75l',
   '{"year":2021,"volume":0.75,"quality":"Pozdní sběr","region":"Česká vinařská oblast","subregion":"Mělnická","village":"Mělník","winery":"Château Mělník","grape":"Zweigeltrebe","alcohol":12.5,"sugar":"suché","residual_sugar":2.2,"acidity":6.2,"serving_temp":"15–17 °C","food_pairing":"svíčková, pečená kachna, polotvrdé sýry","ean":"8594007770078"}'),

  -- ── Bílá vína – Moravská ──────────────────────────────────
  ('default', 'WN-W001', 'Pálava výběr z hroznů 2022',
   'Aromatické, exotické víno s vůní meruněk, pomeranče a orientálního koření. Skvostný ročník.',
   469.00, 21.00, 34, 1, 'white', 'white', '0.75l',
   '{"year":2022,"volume":0.75,"quality":"Výběr z hroznů","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Popice","winery":"Vinařství Sonberk","grape":"Pálava","alcohol":14.0,"sugar":"polosuché","residual_sugar":9.2,"acidity":6.0,"serving_temp":"10–12 °C","food_pairing":"asijská kuchyně, foie gras, ovocné dezerty","ean":"8594005550112","awards":["Salon vín ČR 2023 – zlatá","Wine Spectator 2023 – 91 bodů"]}'),

  ('default', 'WN-W002', 'Rýnský ryzlink pozdní sběr 2022',
   'Minerální, svěží víno s vůní citrusů, bílých květů a jemných bylinek. Typický terroir Znojemska.',
   389.00, 21.00, 45, 1, 'white', 'yellow', '0.75l',
   '{"year":2022,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Znojemská","village":"Šatov","winery":"Vinařství Reisten","grape":"Rýnský ryzlink","alcohol":12.5,"sugar":"suché","residual_sugar":3.8,"acidity":7.2,"serving_temp":"9–11 °C","food_pairing":"ryby, mořské plody, kozí sýry","ean":"8594008880123","awards":["Vinaria 2023 – výborné"]}'),

  ('default', 'WN-W003', 'Sauvignon Blanc pozdní sběr 2023',
   'Svěží, aromatické víno s vůní angreštu, kopřiv a zeleného pepře. Nový ročník.',
   349.00, 21.00, 89, 1, 'white', 'yellow', '0.75l',
   '{"year":2023,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Mikulov","winery":"Mikrosvín Mikulov","grape":"Sauvignon Blanc","alcohol":12.5,"sugar":"suché","residual_sugar":2.5,"acidity":6.8,"serving_temp":"8–10 °C","food_pairing":"kozi syr, spargel, lehke rybi pokrmy","ean":"8594009990134"}'),

  ('default', 'WN-W004', 'Chardonnay výběr z hroznů 2021',
   'Plné, zlatavé víno s vůní máslového těsta, vanilky, broskve a medu. Zrálo 8 měsíců v barique.',
   529.00, 21.00, 22, 1, 'white', 'yellow', '0.75l',
   '{"year":2021,"volume":0.75,"quality":"Výběr z hroznů","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Bavory","winery":"Nové Vinařství","grape":"Chardonnay","alcohol":13.5,"sugar":"suché","residual_sugar":2.0,"acidity":5.8,"serving_temp":"11–13 °C","food_pairing":"krevetový bisque, telecí, máslové omáčky","ean":"8594010001145","awards":["Decanter 2022 – stříbrná","Berliner Wein Trophy – zlatá"]}'),

  ('default', 'WN-W005', 'Veltlínské zelené kabinet 2023',
   'Lehké, pepřnaté víno s vůní bílého pepře, citrusů a bylin. Ideální aperitiv.',
   229.00, 21.00, 112, 1, 'white', 'yellow', '0.75l',
   '{"year":2023,"volume":0.75,"quality":"Kabinet","region":"Moravská vinařská oblast","subregion":"Velkopavlovická","village":"Bořetice","winery":"Vinařství Volařík","grape":"Veltlínské zelené","alcohol":11.5,"sugar":"suché","residual_sugar":3.2,"acidity":6.5,"serving_temp":"8–10 °C","food_pairing":"špenát, zelené saláty, risotto","ean":"8594001110167"}'),

  ('default', 'WN-W006', 'Rulandské šedé pozdní sběr 2022',
   'Plné, zlatavě žluté víno s vůní meruněk, mandlí a jemného koření. Bohatá chuť.',
   369.00, 21.00, 51, 1, 'white', 'yellow', '0.75l',
   '{"year":2022,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Mikulov","winery":"Vinařství Tanzberg","grape":"Rulandské šedé","alcohol":13.5,"sugar":"polosuché","residual_sugar":7.8,"acidity":5.9,"serving_temp":"10–12 °C","food_pairing":"foie gras, uzené ryby, smetanové sýry","ean":"8594002220178"}'),

  ('default', 'WN-W007', 'Tramín červený pozdní sběr 2022',
   'Exotické víno s intenzivní vůní růže, liči, zázvoru a orientálního koření.',
   399.00, 21.00, 38, 1, 'white', 'yellow', '0.75l',
   '{"year":2022,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Valtice","winery":"Vinné sklepy Valtice","grape":"Tramín červený","alcohol":13.0,"sugar":"polosuché","residual_sugar":11.5,"acidity":5.7,"serving_temp":"10–12 °C","food_pairing":"thajská kuchyně, gorgonzola, ovocné dezerty","ean":"8594011001189","awards":["Salon vín ČR 2023 – stříbrná"]}'),

  ('default', 'WN-W008', 'Muškát moravský pozdní sběr 2023',
   'Lehce perlivé, aromatické víno s vůní rozkvétající louky a muskatového oříšku.',
   319.00, 21.00, 67, 1, 'white', 'yellow', '0.75l',
   '{"year":2023,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Pavlov","winery":"Vinařství Pavlov","grape":"Muškát moravský","alcohol":11.0,"sugar":"polosuché","residual_sugar":12.0,"acidity":6.3,"serving_temp":"8–10 °C","food_pairing":"ovocné dezerty, meruňkový koláč, aperitiv","ean":"8594012001190"}'),

  ('default', 'WN-W009', 'Rulandské bílé pozdní sběr 2022',
   'Harmonické víno s vůní hrušek, švestek a jemného toastu. Výrazný a dlouhý závěr.',
   349.00, 21.00, 43, 1, 'white', 'white', '0.75l',
   '{"year":2022,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Valtice","winery":"Château Valtice","grape":"Rulandské bílé","alcohol":13.0,"sugar":"suché","residual_sugar":2.6,"acidity":5.8,"serving_temp":"10–12 °C","food_pairing":"kuře na smetaně, telecí rizoto, brie","ean":"8594003330201"}'),

  ('default', 'WN-W010', 'Vlašský ryzlink jakostní 2023',
   'Svěží, lehké víno s vůní zelených jablek, citronové kůry a bílých květů.',
   199.00, 21.00, 143, 1, 'white', 'yellow', '0.75l',
   '{"year":2023,"volume":0.75,"quality":"Jakostní víno","region":"Moravská vinařská oblast","subregion":"Velkopavlovická","village":"Němčičky","winery":"Vinařství Spielberg","grape":"Vlašský ryzlink","alcohol":11.5,"sugar":"polosuché","residual_sugar":8.5,"acidity":6.7,"serving_temp":"8–10 °C","food_pairing":"grilované ryby, zelenina, šunka","ean":"8594004440212"}'),

  -- ── Bílá vína – Česká ─────────────────────────────────────
  ('default', 'WN-W011', 'Rýnský ryzlink kabinet 2022',
   'Lehké, elegantní ryzlink z jihočeského svahu s příjemnou mineralitou a citrusovostí.',
   279.00, 21.00, 35, 1, 'white', 'yellow', '0.75l',
   '{"year":2022,"volume":0.75,"quality":"Kabinet","region":"Česká vinařská oblast","subregion":"Mělnická","village":"Mělník","winery":"Château Mělník","grape":"Rýnský ryzlink","alcohol":11.5,"sugar":"suché","residual_sugar":3.0,"acidity":7.0,"serving_temp":"9–11 °C","food_pairing":"uzený losos, mušle, špenátová quiche","ean":"8594007770223"}'),

  -- ── Rosé vína ─────────────────────────────────────────────
  ('default', 'WN-RO01', 'Frankovka rosé pozdní sběr 2023',
   'Světle lososové rosé s vůní jahod, malin a růžových lístků. Svěží a elegantní.',
   299.00, 21.00, 78, 1, 'rosé', 'rosé', '0.75l',
   '{"year":2023,"volume":0.75,"quality":"Pozdní sběr","region":"Moravská vinařská oblast","subregion":"Velkopavlovická","village":"Bořetice","winery":"Vinařství Volařík","grape":"Frankovka","alcohol":12.0,"sugar":"suché","residual_sugar":3.5,"acidity":6.2,"serving_temp":"8–10 °C","food_pairing":"letní saláty, grilované kuře, krevety","ean":"8594001110234","awards":["Vinoforum 2023 – zlatá"]}'),

  ('default', 'WN-RO02', 'Cabernet Sauvignon rosé kabinet 2023',
   'Temně lososové rosé plné charakteru s vůní červeného rybízu a malin.',
   269.00, 21.00, 55, 1, 'rosé', 'rosé', '0.75l',
   '{"year":2023,"volume":0.75,"quality":"Kabinet","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Mikulov","winery":"Mikrosvín Mikulov","grape":"Cabernet Sauvignon","alcohol":12.0,"sugar":"suché","residual_sugar":4.0,"acidity":6.0,"serving_temp":"8–10 °C","food_pairing":"grilovaný tuňák, caprese, guacamole","ean":"8594009990245"}'),

  -- ── Šumivá vína ───────────────────────────────────────────
  ('default', 'WN-S001', 'Sekt Blanc de Blancs brut',
   'Elegantní sekt tradiční metodou. Jemné bubliny, vůně pečiva, citrusu a bílých plodů.',
   479.00, 21.00, 36, 1, 'sparkling', 'white', '0.75l',
   '{"year":2020,"volume":0.75,"quality":"Jakostní šumivé víno – tradiční metoda","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Valtice","winery":"Vinné sklepy Valtice","grape":"Chardonnay","alcohol":12.0,"sugar":"brut","residual_sugar":5.0,"acidity":7.5,"serving_temp":"6–8 °C","aging_on_lees":"36 měsíců","food_pairing":"ústřice, sushi, jemné předkrmy","ean":"8594011001256","awards":["Salon vín ČR 2022 – zlatá"]}'),

  ('default', 'WN-S002', 'Sekt Rosé extra dry',
   'Romantické šumivé rosé s vůní jahod a červeného ovoce. Jemné bublinky, svěží závěr.',
   429.00, 21.00, 28, 1, 'sparkling', 'rosé', '0.75l',
   '{"year":2021,"volume":0.75,"quality":"Jakostní šumivé víno","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Valtice","winery":"Château Valtice","grape":"Frankovka","alcohol":11.5,"sugar":"extra dry","residual_sugar":14.0,"acidity":7.0,"serving_temp":"6–8 °C","food_pairing":"jahody s šlehačkou, makronky, lehký dezert","ean":"8594003330267"}'),

  -- ── Dezertní vína ─────────────────────────────────────────
  ('default', 'WN-D001', 'Pálava výběr z bobulí 2021',
   'Zlatavé dezertní víno s koncentrovanou vůní medu, meruněk a exotického koření. Rarita.',
   890.00, 21.00,  9, 1, 'dessert', 'yellow', '0.375l',
   '{"year":2021,"volume":0.375,"quality":"Výběr z bobulí","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Popice","winery":"Vinařství Sonberk","grape":"Pálava","alcohol":9.5,"sugar":"sladké","residual_sugar":98.0,"acidity":8.5,"serving_temp":"8–10 °C","food_pairing":"foie gras, modrý sýr, crème brûlée","ean":"8594005550278","awards":["Salon vín ČR 2022 – nejlepší bílé víno","Mundus Vini 2022 – Grand Gold"]}'),

  ('default', 'WN-D002', 'Tramín červený slámové víno 2020',
   'Výjimečné slámové víno. Meruňky, citronová kůra, kandizovaný zázvor. Vyrobeno ze sušených hroznů.',
   1290.00, 21.00,  6, 1, 'dessert', 'yellow', '0.375l',
   '{"year":2020,"volume":0.375,"quality":"Slámové víno","region":"Moravská vinařská oblast","subregion":"Mikulovská","village":"Valtice","winery":"Vinné sklepy Valtice","grape":"Tramín červený","alcohol":10.0,"sugar":"sladké","residual_sugar":145.0,"acidity":9.2,"serving_temp":"8–10 °C","food_pairing":"Roquefort, crème brûlée, ovocné tarty","ean":"8594011001289","awards":["Salon vín ČR 2022 – zlatá","Vinalies Internationales – zlatá"]}'),

  ('default', 'WN-D003', 'Rýnský ryzlink ledové víno 2022',
   'Extrémně vzácné ledové víno sklizené při −8 °C. Nektarová sladkost s osvěžující kyselinkou.',
   1590.00, 21.00,  4, 1, 'dessert', 'yellow', '0.375l',
   '{"year":2022,"volume":0.375,"quality":"Ledové víno","region":"Moravská vinařská oblast","subregion":"Znojemská","village":"Šatov","winery":"Vinařství Reisten","grape":"Rýnský ryzlink","alcohol":8.0,"sugar":"sladké","residual_sugar":186.0,"acidity":11.5,"serving_temp":"6–8 °C","harvest_temp":"−8 °C","food_pairing":"panna cotta, ovocné sorbety, jemný plísňový sýr","ean":"8594008880290","awards":["Salon vín ČR 2023 – zlatá","Decanter 2023 – Platina 97 bodů"]}'),

  -- ── Akce a sety ───────────────────────────────────────────
  ('default', 'WN-SET01', 'Dárkový set Moravská klasika (3 lahve)',
   'Trio nejoblíbenějších moravských vín: Veltlínské zelené, Frankovka, Rulandské šedé.',
   799.00, 21.00, 15, 1, 'red', 'red', '3× 0.75l',
   '{"contents":["WN-W005","WN-R001","WN-W006"],"gift_box":true,"note":"Dárkově baleno v dřevěné krabičce s gravírováním","ean":"8594099990301"}'),

  ('default', 'WN-SET02', 'Výběr z bobulí kolekce (2 lahve)',
   'Dvě výjimečné dezertní rarity: Pálava výběr z bobulí a Tramín slámové víno.',
   1690.00, 21.00,  7, 1, 'dessert', 'yellow', '2× 0.375l',
   '{"contents":["WN-D001","WN-D002"],"gift_box":true,"note":"Luxusní dárkové balení ve skřínce s průvodcem","ean":"8594099990302"}');

-- ── Seed: product_category ────────────────────────────────
INSERT IGNORE INTO `product_category` (`product_id`, `category_id`) VALUES
  -- Červená moravská
  ((SELECT id FROM product WHERE sku='WN-R001'), (SELECT id FROM category WHERE syscode='cervena_vina')),
  ((SELECT id FROM product WHERE sku='WN-R001'), (SELECT id FROM category WHERE syscode='moravska_cervena')),
  ((SELECT id FROM product WHERE sku='WN-R002'), (SELECT id FROM category WHERE syscode='cervena_vina')),
  ((SELECT id FROM product WHERE sku='WN-R002'), (SELECT id FROM category WHERE syscode='moravska_cervena')),
  ((SELECT id FROM product WHERE sku='WN-R003'), (SELECT id FROM category WHERE syscode='cervena_vina')),
  ((SELECT id FROM product WHERE sku='WN-R003'), (SELECT id FROM category WHERE syscode='moravska_cervena')),
  ((SELECT id FROM product WHERE sku='WN-R004'), (SELECT id FROM category WHERE syscode='cervena_vina')),
  ((SELECT id FROM product WHERE sku='WN-R004'), (SELECT id FROM category WHERE syscode='moravska_cervena')),
  ((SELECT id FROM product WHERE sku='WN-R005'), (SELECT id FROM category WHERE syscode='cervena_vina')),
  ((SELECT id FROM product WHERE sku='WN-R005'), (SELECT id FROM category WHERE syscode='moravska_cervena')),
  ((SELECT id FROM product WHERE sku='WN-R006'), (SELECT id FROM category WHERE syscode='cervena_vina')),
  ((SELECT id FROM product WHERE sku='WN-R006'), (SELECT id FROM category WHERE syscode='moravska_cervena')),
  ((SELECT id FROM product WHERE sku='WN-R007'), (SELECT id FROM category WHERE syscode='cervena_vina')),
  ((SELECT id FROM product WHERE sku='WN-R007'), (SELECT id FROM category WHERE syscode='moravska_cervena')),
  -- Červená česká
  ((SELECT id FROM product WHERE sku='WN-R008'), (SELECT id FROM category WHERE syscode='cervena_vina')),
  ((SELECT id FROM product WHERE sku='WN-R008'), (SELECT id FROM category WHERE syscode='ceska_cervena')),
  -- Bílá moravská
  ((SELECT id FROM product WHERE sku='WN-W001'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W001'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  ((SELECT id FROM product WHERE sku='WN-W002'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W002'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  ((SELECT id FROM product WHERE sku='WN-W003'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W003'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  ((SELECT id FROM product WHERE sku='WN-W004'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W004'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  ((SELECT id FROM product WHERE sku='WN-W005'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W005'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  ((SELECT id FROM product WHERE sku='WN-W006'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W006'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  ((SELECT id FROM product WHERE sku='WN-W007'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W007'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  ((SELECT id FROM product WHERE sku='WN-W008'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W008'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  ((SELECT id FROM product WHERE sku='WN-W009'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W009'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  ((SELECT id FROM product WHERE sku='WN-W010'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W010'), (SELECT id FROM category WHERE syscode='moravska_bila')),
  -- Bílá česká
  ((SELECT id FROM product WHERE sku='WN-W011'), (SELECT id FROM category WHERE syscode='bila_vina')),
  ((SELECT id FROM product WHERE sku='WN-W011'), (SELECT id FROM category WHERE syscode='ceska_bila')),
  -- Rosé
  ((SELECT id FROM product WHERE sku='WN-RO01'), (SELECT id FROM category WHERE syscode='rose_vina')),
  ((SELECT id FROM product WHERE sku='WN-RO01'), (SELECT id FROM category WHERE syscode='moravske_rose')),
  ((SELECT id FROM product WHERE sku='WN-RO02'), (SELECT id FROM category WHERE syscode='rose_vina')),
  ((SELECT id FROM product WHERE sku='WN-RO02'), (SELECT id FROM category WHERE syscode='moravske_rose')),
  -- Šumivá
  ((SELECT id FROM product WHERE sku='WN-S001'), (SELECT id FROM category WHERE syscode='sumiva_vina')),
  ((SELECT id FROM product WHERE sku='WN-S001'), (SELECT id FROM category WHERE syscode='sekty')),
  ((SELECT id FROM product WHERE sku='WN-S002'), (SELECT id FROM category WHERE syscode='sumiva_vina')),
  ((SELECT id FROM product WHERE sku='WN-S002'), (SELECT id FROM category WHERE syscode='sekty')),
  -- Dezertní
  ((SELECT id FROM product WHERE sku='WN-D001'), (SELECT id FROM category WHERE syscode='dezertni_vina')),
  ((SELECT id FROM product WHERE sku='WN-D001'), (SELECT id FROM category WHERE syscode='vyber_bobuli')),
  ((SELECT id FROM product WHERE sku='WN-D002'), (SELECT id FROM category WHERE syscode='dezertni_vina')),
  ((SELECT id FROM product WHERE sku='WN-D002'), (SELECT id FROM category WHERE syscode='vyber_bobuli')),
  ((SELECT id FROM product WHERE sku='WN-D003'), (SELECT id FROM category WHERE syscode='dezertni_vina')),
  ((SELECT id FROM product WHERE sku='WN-D003'), (SELECT id FROM category WHERE syscode='vyber_bobuli')),
  -- Sety
  ((SELECT id FROM product WHERE sku='WN-SET01'), (SELECT id FROM category WHERE syscode='akcove_sety')),
  ((SELECT id FROM product WHERE sku='WN-SET02'), (SELECT id FROM category WHERE syscode='akcove_sety')),
  ((SELECT id FROM product WHERE sku='WN-SET02'), (SELECT id FROM category WHERE syscode='dezertni_vina'));

-- ── Seed: orders + order_items ────────────────────────────
INSERT IGNORE INTO `order` (`franchise_code`, `order_number`, `user_id`, `status`, `total_amount`, `currency`, `payment_method`, `shipping_address_id`, `billing_address_id`, `created_at`) VALUES
  ('default', 'ORD-2025-0001', (SELECT id FROM user WHERE email='jan.novak@example.com'),          'delivered',  1536.00, 'CZK', 'card',          (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='jan.novak@example.com')        AND type='shipping'), (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='jan.novak@example.com')        AND type='billing'), '2025-11-15 10:22:00'),
  ('default', 'ORD-2025-0002', (SELECT id FROM user WHERE email='marie.svobodova@example.com'),    'delivered',  1697.00, 'CZK', 'bank_transfer', (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='marie.svobodova@example.com')  AND type='shipping'), (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='marie.svobodova@example.com')  AND type='billing'), '2025-12-03 14:08:00'),
  ('default', 'ORD-2025-0003', (SELECT id FROM user WHERE email='petr.dvorak@example.com'),        'delivered',  2180.00, 'CZK', 'online',        (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='petr.dvorak@example.com')        AND type='shipping'), (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='petr.dvorak@example.com')        AND type='billing'), '2025-12-18 09:45:00'),
  ('default', 'ORD-2026-0001', (SELECT id FROM user WHERE email='tomas.kucera@example.com'),       'shipped',    3478.00, 'CZK', 'card',          (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='tomas.kucera@example.com')       AND type='shipping'), (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='tomas.kucera@example.com')       AND type='billing'), '2026-01-08 16:30:00'),
  ('default', 'ORD-2026-0002', (SELECT id FROM user WHERE email='eva.vesela@example.com'),         'confirmed',  1258.00, 'CZK', 'bank_transfer', NULL,                                                                                                                                           (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='eva.vesela@example.com')         AND type='billing'), '2026-02-14 11:15:00'),
  ('default', 'ORD-2026-0003', (SELECT id FROM user WHERE email='jan.novak@example.com'),          'processing', 2479.00, 'CZK', 'card',          (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='jan.novak@example.com')        AND type='shipping'), (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='jan.novak@example.com')        AND type='billing'), '2026-03-22 08:55:00'),
  ('default', 'ORD-2026-0004', (SELECT id FROM user WHERE email='lucie.prochazkova@example.com'),  'pending',    1590.00, 'CZK', 'online',        NULL,                                                                                                                                           (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='lucie.prochazkova@example.com') AND type='billing'), '2026-04-30 20:10:00');

INSERT IGNORE INTO `order_item` (`order_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
  -- ORD-2025-0001: Sauvignon 2×, Frankovka 1×, Ryzlink 1×
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0001'), (SELECT id FROM product WHERE sku='WN-W003'), 2, 349.00,  698.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0001'), (SELECT id FROM product WHERE sku='WN-R001'), 1, 389.00,  389.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0001'), (SELECT id FROM product WHERE sku='WN-W002'), 1, 389.00,  389.00),
  -- ORD-2025-0002: Pálava VzH, Tramín, Sekt Blanc
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0002'), (SELECT id FROM product WHERE sku='WN-W001'), 1, 469.00,  469.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0002'), (SELECT id FROM product WHERE sku='WN-W007'), 1, 399.00,  399.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0002'), (SELECT id FROM product WHERE sku='WN-S001'), 1, 479.00,  479.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0002'), (SELECT id FROM product WHERE sku='WN-W005'), 2, 229.00,  350.00),
  -- ORD-2025-0003: Dárkový set + dezertní + rosé 2×
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0003'), (SELECT id FROM product WHERE sku='WN-SET01'),1, 799.00,  799.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0003'), (SELECT id FROM product WHERE sku='WN-D001'), 1, 890.00,  890.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2025-0003'), (SELECT id FROM product WHERE sku='WN-RO01'),2, 299.00,  298.00),
  -- ORD-2026-0001: Sonberk Pinot Noir, Chardonnay, sekt rosé, Tramín, ledové víno
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0001'), (SELECT id FROM product WHERE sku='WN-R005'), 1, 549.00,  549.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0001'), (SELECT id FROM product WHERE sku='WN-W004'), 1, 529.00,  529.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0001'), (SELECT id FROM product WHERE sku='WN-S002'), 1, 429.00,  429.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0001'), (SELECT id FROM product WHERE sku='WN-W007'), 1, 399.00,  399.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0001'), (SELECT id FROM product WHERE sku='WN-D003'), 1,1590.00, 1590.00),
  -- ORD-2026-0002: Muškát 2×, Veltlínské 3×
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0002'), (SELECT id FROM product WHERE sku='WN-W008'), 2, 319.00,  638.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0002'), (SELECT id FROM product WHERE sku='WN-W005'), 3, 229.00,  687.00) ,
  -- ORD-2026-0003: Výběr set, Cabernet Sauv rosé, Zweigelt Magnum, Rulandské šedé
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0003'), (SELECT id FROM product WHERE sku='WN-SET02'),1,1690.00, 1690.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0003'), (SELECT id FROM product WHERE sku='WN-RO02'), 1, 269.00,  269.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0003'), (SELECT id FROM product WHERE sku='WN-W006'), 1, 369.00,  369.00),
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0003'), (SELECT id FROM product WHERE sku='WN-R004'), 1, 299.00,  299.00),
  -- ORD-2026-0004: Ledové víno 0.375l
  ((SELECT id FROM `order` WHERE order_number='ORD-2026-0004'), (SELECT id FROM product WHERE sku='WN-D003'), 1,1590.00, 1590.00);

-- ── Seed: invoices + invoice_items ────────────────────────
INSERT IGNORE INTO `invoice` (`franchise_code`, `invoice_number`, `order_id`, `user_id`, `status`, `total_amount`, `currency`, `billing_address_id`, `issued_at`, `due_at`, `paid_at`) VALUES
  ('default', 'INV-2025-0001', (SELECT id FROM `order` WHERE order_number='ORD-2025-0001'), (SELECT id FROM user WHERE email='jan.novak@example.com'),          'paid',    1536.00, 'CZK', (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='jan.novak@example.com')        AND type='billing'), '2025-11-15 10:22:00', '2025-11-29', '2025-11-17 12:00:00'),
  ('default', 'INV-2025-0002', (SELECT id FROM `order` WHERE order_number='ORD-2025-0002'), (SELECT id FROM user WHERE email='marie.svobodova@example.com'),    'paid',    1697.00, 'CZK', (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='marie.svobodova@example.com')  AND type='billing'), '2025-12-03 14:08:00', '2025-12-17', '2025-12-05 09:00:00'),
  ('default', 'INV-2025-0003', (SELECT id FROM `order` WHERE order_number='ORD-2025-0003'), (SELECT id FROM user WHERE email='petr.dvorak@example.com'),        'paid',    2180.00, 'CZK', (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='petr.dvorak@example.com')        AND type='billing'), '2025-12-18 09:45:00', '2026-01-01', '2025-12-22 11:30:00'),
  ('default', 'INV-2026-0001', (SELECT id FROM `order` WHERE order_number='ORD-2026-0001'), (SELECT id FROM user WHERE email='tomas.kucera@example.com'),       'issued',  3478.00, 'CZK', (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='tomas.kucera@example.com')       AND type='billing'), '2026-01-08 16:30:00', '2026-01-22', NULL),
  ('default', 'INV-2026-0002', (SELECT id FROM `order` WHERE order_number='ORD-2026-0002'), (SELECT id FROM user WHERE email='eva.vesela@example.com'),         'issued',  1258.00, 'CZK', (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='eva.vesela@example.com')         AND type='billing'), '2026-02-14 11:15:00', '2026-02-28', NULL),
  ('default', 'INV-2026-0003', (SELECT id FROM `order` WHERE order_number='ORD-2026-0003'), (SELECT id FROM user WHERE email='jan.novak@example.com'),          'draft',   2479.00, 'CZK', (SELECT id FROM address WHERE user_id=(SELECT id FROM user WHERE email='jan.novak@example.com')        AND type='billing'), '2026-03-22 08:55:00', '2026-04-05', NULL);

INSERT IGNORE INTO `invoice_item` (`invoice_id`, `product_id`, `description`, `quantity`, `unit_price`, `total_price`) VALUES
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0001'), (SELECT id FROM product WHERE sku='WN-W003'), 'Sauvignon Blanc PS 2023',               2,  349.00,  698.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0001'), (SELECT id FROM product WHERE sku='WN-R001'), 'Frankovka VzH 2021',                    1,  389.00,  389.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0001'), (SELECT id FROM product WHERE sku='WN-W002'), 'Rýnský ryzlink PS 2022',                1,  389.00,  389.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0002'), (SELECT id FROM product WHERE sku='WN-W001'), 'Pálava VzH 2022 – Vinařství Sonberk',   1,  469.00,  469.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0002'), (SELECT id FROM product WHERE sku='WN-W007'), 'Tramín červený PS 2022',                1,  399.00,  399.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0002'), (SELECT id FROM product WHERE sku='WN-S001'), 'Sekt Blanc de Blancs brut',             1,  479.00,  479.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0002'), (SELECT id FROM product WHERE sku='WN-W005'), 'Veltlínské zelené kabinet 2023',        2,  229.00,  350.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0003'), (SELECT id FROM product WHERE sku='WN-SET01'),'Dárkový set Moravská klasika',          1,  799.00,  799.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0003'), (SELECT id FROM product WHERE sku='WN-D001'), 'Pálava výběr z bobulí 2021 0.375l',     1,  890.00,  890.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2025-0003'), (SELECT id FROM product WHERE sku='WN-RO01'), 'Frankovka rosé PS 2023',                2,  299.00,  298.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0001'), (SELECT id FROM product WHERE sku='WN-R005'), 'Pinot Noir VzH 2020 – Sonberk',         1,  549.00,  549.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0001'), (SELECT id FROM product WHERE sku='WN-W004'), 'Chardonnay VzH 2021',                   1,  529.00,  529.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0001'), (SELECT id FROM product WHERE sku='WN-S002'), 'Sekt Rosé extra dry',                   1,  429.00,  429.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0001'), (SELECT id FROM product WHERE sku='WN-W007'), 'Tramín červený PS 2022',                1,  399.00,  399.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0001'), (SELECT id FROM product WHERE sku='WN-D003'), 'Rýnský ryzlink ledové víno 2022 0.375l',1, 1590.00, 1590.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0002'), (SELECT id FROM product WHERE sku='WN-W008'), 'Muškát moravský PS 2023',               2,  319.00,  638.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0002'), (SELECT id FROM product WHERE sku='WN-W005'), 'Veltlínské zelené kabinet 2023',        3,  229.00,  687.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0003'), (SELECT id FROM product WHERE sku='WN-SET02'),'Výběr z bobulí kolekce (2 lahve)',      1, 1690.00, 1690.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0003'), (SELECT id FROM product WHERE sku='WN-RO02'), 'Cabernet Sauvignon rosé 2023',          1,  269.00,  269.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0003'), (SELECT id FROM product WHERE sku='WN-W006'), 'Rulandské šedé PS 2022',                1,  369.00,  369.00),
  ((SELECT id FROM invoice WHERE invoice_number='INV-2026-0003'), (SELECT id FROM product WHERE sku='WN-R004'), 'Zweigelt PS 2022',                      1,  299.00,  299.00);

-- ── Seed: texts (CMS) ─────────────────────────────────────
INSERT IGNORE INTO `text` (`franchise_code`, `syscode`, `title`, `content`, `language`, `is_active`) VALUES
  ('default', 'homepage_hero',     'Vína přímo od moravských vinařů',
   'Nabízíme pečlivě vybrané moravské a české víno. Každá lahev prošla odbornou degustací. Doprava zdarma nad 1 500 Kč.',
   'cs', 1),
  ('default', 'homepage_hero',     'Wines directly from Moravian winemakers',
   'We offer carefully selected Moravian and Czech wines. Every bottle has passed professional tasting. Free shipping on orders over 1 500 CZK.',
   'en', 1),
  ('default', 'shipping_info',     'Informace o dopravě',
   'Doprava zdarma při nákupu nad 1 500 Kč. Doručení do 2–3 pracovních dnů. Lahve jsou baleny do ochranné pěnové výplně. Možnost osobního odběru ve Valtice.',
   'cs', 1),
  ('default', 'return_policy',     'Reklamace a vrácení zboží',
   'Poškozené nebo chybně doručené zboží reklamujte do 3 dnů od doručení. Nevyhovující víno lze vrátit do 14 dnů od nákupu. Reklamace řešíme do 30 dnů.',
   'cs', 1),
  ('default', 'wine_storage',      'Jak skladovat víno',
   'Víno skladujte vleže při teplotě 10–14 °C, v temném prostředí s relativní vlhkostí 70–80 %. Chraňte před přímým slunečním světlem a vibracemi.',
   'cs', 1),
  ('default', 'wine_storage',      'How to store wine',
   'Store wine horizontally at 10–14 °C in a dark environment with 70–80% relative humidity. Protect from direct sunlight and vibrations.',
   'en', 1),
  ('default', 'about_us',          'O nás',
   'Jsme specializovaný vinný e-shop se zaměřením na moravská a česká vína. Spolupracujeme přímo s vinaři, což nám umožňuje nabídnout autentická vína za přímé ceny.',
   'cs', 1),
  ('default', 'gdpr_consent',      'Souhlas se zpracováním osobních údajů',
   'Souhlasím se zpracováním osobních údajů za účelem vyřízení objednávky v souladu s nařízením GDPR (EU) 2016/679.',
   'cs', 1),
  ('default', 'newsletter_teaser', 'Přihlaste se k odběru vinného zpravodaje',
   'Získejte 10 % slevu na první nákup! Buďte první, kdo se dozví o nových ročnících, limitovaných raritách a degustačních akcích.',
   'cs', 1),
  ('default', 'contact',           'Kontakt',
   'Zákaznická linka: +420 800 666 888 (Po–Pá 9–17 h)\nE-mail: info@vinoteka.example.com\nAdresa: Mikulovská 1, 691 42 Valtice\nIČO: 12345678 | DIČ: CZ12345678',
   'cs', 1);

