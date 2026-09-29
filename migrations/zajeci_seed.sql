-- zajeci bootstrap/reference data, consolidated through 2026-09-28.
-- Insert missing records only; preserve passwords, edits and deleted tombstones.
-- IDs use tenant/natural keys, never fixed AUTO_INCREMENT values.
SET NAMES utf8mb4;
START TRANSACTION;

-- role
INSERT INTO `role` (`franchise_code`,`name`,`label`,`position`,`deleted`)
SELECT 'zajeci','admin','Admin',10,0
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `franchise_code`='zajeci' AND `name`='admin');
INSERT INTO `role` (`franchise_code`,`name`,`label`,`position`,`deleted`)
SELECT 'zajeci','manager','Manager',20,0
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `franchise_code`='zajeci' AND `name`='manager');
INSERT INTO `role` (`franchise_code`,`name`,`label`,`position`,`deleted`)
SELECT 'zajeci','user','User',30,0
WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE `franchise_code`='zajeci' AND `name`='user');

-- enumeration
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','currency','CZK','Czech Koruna','CZK',10,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='currency' AND `syscode`='CZK');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','currency','EUR','Euro','EUR',20,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='currency' AND `syscode`='EUR');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','currency','USD','US Dollar','USD',30,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='currency' AND `syscode`='USD');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','dry','Dry','dry',10,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='dry');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','semi_dry','Semi-dry','semi_dry',20,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='semi_dry');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','sweet','Sweet','sweet',30,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='sweet');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','semi_sweet','Semi-sweet','semi_sweet',40,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='semi_sweet');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','extra_dry','Extra dry','extra_dry',50,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='extra_dry');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','off_dry','Off-dry','off_dry',60,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='off_dry');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','medium_dry','Medium dry','medium_dry',70,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='medium_dry');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','medium_sweet','Medium sweet','medium_sweet',80,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='medium_sweet');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','very_sweet','Very sweet','very_sweet',90,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='very_sweet');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_kind','dessert','Dessert','dessert',100,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_kind' AND `syscode`='dessert');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_color','white','White','white',10,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_color' AND `syscode`='white');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_color','red','Red','red',20,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_color' AND `syscode`='red');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_color','rose','Rosé','rose',30,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_color' AND `syscode`='rose');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','wine_color','orange','Orange','orange',40,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='wine_color' AND `syscode`='orange');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','language','cs','Čeština','cs',10,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='language' AND `syscode`='cs');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','language','en','English','en',20,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='language' AND `syscode`='en');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','country_code','cs','CZ','cs',10,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='country_code' AND `syscode`='cs');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','country_code','sk','SK','sk',20,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='country_code' AND `syscode`='sk');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','order_status','pending','Pending','pending',10,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='order_status' AND `syscode`='pending');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','order_status','confirmed','Confirmed','confirmed',20,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='order_status' AND `syscode`='confirmed');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','order_status','shipped','Shipped','shipped',30,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='order_status' AND `syscode`='shipped');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','order_status','delivered','Delivered','delivered',40,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='order_status' AND `syscode`='delivered');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','order_status','cancelled','Cancelled','cancelled',50,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='order_status' AND `syscode`='cancelled');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','invoice_status','draft','Draft','draft',10,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='invoice_status' AND `syscode`='draft');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','invoice_status','issued','Issued','issued',20,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='invoice_status' AND `syscode`='issued');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','invoice_status','paid','Paid','paid',30,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='invoice_status' AND `syscode`='paid');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','invoice_status','overdue','Overdue','overdue',40,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='invoice_status' AND `syscode`='overdue');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','invoice_status','cancelled','Cancelled','cancelled',50,1,NULL,0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='invoice_status' AND `syscode`='cancelled');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','taste','basic','Basic','basic',10,1,'{"food": "Pečivo, voda", "time": "Doba trvání 1 hodina", "drink": "Ochutnávka 6 vzorků", "price": 250.00, "description": ""}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='taste' AND `syscode`='basic');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','taste','medium','Medium','medium',20,1,'{"food": "Občerstvení, pečivo, voda", "time": "Doba trvání 2 až 2,5 hodiny", "drink": "Ochutnávka 10 vzorků", "price": 500.00, "description": ""}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='taste' AND `syscode`='medium');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','taste','all_you_can_drink','All you can drink','all_you_can_drink',30,1,'{"food": "Bohaté občerstvení, voda, nealko, pivo, cider, šláftruňk", "time": "Doba trvání podle nálady, max 5 hodin", "drink": "Ochutnávka všech vzorků (min. 9 bílých, 4 růžové, 4 červené)", "price": 900.00, "description": ""}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='taste' AND `syscode`='all_you_can_drink');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','shipping','free','Vyzvednutí v Zaječí','free',10,1,'{"help": "$.shipping.brno_free", "icon": "mdi:home-city-outline", "price": 0, "disabled": false}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='shipping' AND `syscode`='free');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','shipping','post','Česká pošta','post',20,1,'{"help": "$.shipping.not_quaranteed", "icon": "/img/shipping/post.jpg", "price": 209, "disabled": false}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='shipping' AND `syscode`='post');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','shipping','dpd','DPD','dpd',30,1,'{"help": "$.shipping.not_quaranteed", "icon": "mdi:truck-outline", "price": 150, "disabled": false}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='shipping' AND `syscode`='dpd');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','shipping','messenger','Vlastní doručení','messenger',40,1,'{"help": "$.shipping.third_day", "icon": "mdi:truck-outline", "price": 175, "disabled": false}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='shipping' AND `syscode`='messenger');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','vat_rate','vat_21','DPH 21 %','vat_21',10,1,'{"rate": 21.00}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='vat_rate' AND `syscode`='vat_21');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','payment','bank','Bankovní převod','bank',10,1,'{"iban": "CZ0220100000002403322687", "icon": "mdi:bank-outline", "price": 0, "swift": "FIOBCZPPXXX", "account": "2403322687/2010", "disabled": false}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='payment' AND `syscode`='bank');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','payment','cash','Hotovost','cash',20,1,'{"icon": "mdi:cash-100", "price": 0, "disabled": false}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='payment' AND `syscode`='cash');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','payment','card','Platební karta','card',30,1,'{"icon": "mdi:credit-card-outline", "price": 0, "disabled": true}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='payment' AND `syscode`='card');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','payment','paypal','PayPal','paypal',40,1,'{"icon": "logos:paypal", "price": 0, "disabled": true}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='payment' AND `syscode`='paypal');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','payment','gopay','GoPay','gopay',50,1,'{"icon": "arcticons:gopay", "price": 0, "disabled": true}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='payment' AND `syscode`='gopay');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','payment','apple_pay','Apple Pay','apple_pay',60,1,'{"icon": "simple-icons:applepay", "price": 0, "disabled": true}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='payment' AND `syscode`='apple_pay');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','payment','google_pay','Google Pay','google_pay',70,1,'{"icon": "simple-icons:googlepay", "price": 0, "disabled": true}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='payment' AND `syscode`='google_pay');
INSERT INTO `enumeration` (`franchise_code`,`type`,`syscode`,`label`,`value`,`position`,`published`,`data`,`deleted`)
SELECT 'zajeci','contact','contact','Kontakt','contact',10,1,'{"ic": "19737491", "dic": "CZ7951084053", "zip": "69105", "city": "Zaječí", "email": "vyborne@vinozezajeci.cz", "phone1": "+420 770 199 999", "phone2": "+420 778 711 111", "street": "Školní 156"}',0
WHERE NOT EXISTS (SELECT 1 FROM `enumeration` WHERE `franchise_code`='zajeci' AND `type`='contact' AND `syscode`='contact');

-- category
INSERT INTO `category` (`franchise_code`,`parent_id`,`syscode`,`name`,`description`,`position`,`published`,`deleted`)
SELECT 'zajeci',NULL,'top','Top Produkty','Nejlepší vína z nabídky',10,1,0
WHERE NOT EXISTS (SELECT 1 FROM `category` WHERE `franchise_code`='zajeci' AND `syscode`='top');

-- user
INSERT INTO `user` (`franchise_code`,`first_name`,`last_name`,`email`,`phone`,`password`,`role_id`,`status`,`deleted`)
SELECT 'zajeci','Admin','User','admin@example.com',NULL,'$2y$12$iHtrWWa.BMJBFu3d0YA8EuoojjRMXCa0OHuPfBmVoJcT26OLKGbSC',(SELECT `id` FROM `role` WHERE `franchise_code`='zajeci' AND `name`='admin'),'active',0
WHERE NOT EXISTS (SELECT 1 FROM `user` WHERE `franchise_code`='zajeci' AND `email`='admin@example.com');

-- product
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zajeci','ZAJ-MT-2025','Müller Thurgau 2025','Moravské zemské víno. Vinařská obec Zaječí, viniční trať U Kapličky. Cukernatost hroznů při sběru 21 °NM, zbytkový cukr do 4 g/l. Kvašeno a školeno v dubovém sudu, bez použití selektovaných kvasinek a enzymů.','190.00',50,1,0,'dry','white','Müller-Thurgau','{"year": 2025, "batch": "12025", "region": "Zaječí – U Kapličky", "volume": 0.75, "winery": "Vinařství Zaječí", "alcohol": 12.0, "quality": "Moravské zemské víno", "sugar_at_harvest": 21}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-MT-2025');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zajeci','ZAJ-SZ-2025','Sylvánské zelené 2025','Moravské zemské víno. Vinařská obec Zaječí, viniční trať Stará Hora. Cukernatost hroznů při sběru 22 °NM, zbytkový cukr do 4 g/l. Kvašeno a školeno ve skle, bez použití selektovaných kvasinek a enzymů.','190.00',50,1,0,'dry','white','Sylvánské zelené','{"year": 2025, "batch": "52025", "region": "Zaječí – Stará Hora", "volume": 0.75, "winery": "Vinařství Zaječí", "alcohol": 12.0, "quality": "Moravské zemské víno", "sugar_at_harvest": 22}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-SZ-2025');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zajeci','ZAJ-NB-2025','Neuburské 2025','Moravské zemské víno. Vinařská obec Zaječí, viniční trať U Kapličky, severní svah. Cukernatost hroznů při sběru 22 °NM, zbytkový cukr do 9 g/l. Kvašeno a školeno ve skle, bez použití selektovaných kvasinek a enzymů.','200.00',40,1,0,'semi_dry','white','Neuburské','{"year": 2025, "batch": "82025", "region": "Zaječí – U Kapličky (sever)", "volume": 0.75, "winery": "Vinařství Zaječí", "alcohol": 12.0, "quality": "Moravské zemské víno", "sugar_at_harvest": 22}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-NB-2025');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zajeci','ZAJ-RR-2024','Rýnský ryzlink 2024','Moravské zemské víno. Vinařská obec Přítluky, viniční trať U křížku. Cukernatost hroznů při sběru 23 °NM, zbytkový cukr do 1 g/l. Kvašeno a školeno v akátovém sudu, bez použití selektovaných kvasinek a enzymů.','240.00',40,1,0,'dry','white','Rýnský ryzlink','{"year": 2024, "batch": "92024", "region": "Přítluky – U křížku", "volume": 0.75, "winery": "Vinařství Zaječí", "alcohol": 12.5, "quality": "Moravské zemské víno", "sugar_at_harvest": 23}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-RR-2024');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zajeci','ZAJ-SK-2025','Slovakia 2025','Experimentální odrůda vzniklá křížením Rýnského ryzlinku a Muškátu Ottonel (šlechtitelka Ing. Dorota Pospíšilová, CSc., VÚVV Bratislava). Odrůda není dosud uznána v ČR ani na Slovensku – zkušební výsadba. Moravské zemské víno, vinařská obec Moravská Nová Ves, viniční trať Stará hora. Cukernatost hroznů při sběru 23 °NM, zbytkový cukr do 4 g/l. Kvašeno a školeno ve skle, bez použití selektovaných kvasinek a enzymů.','240.00',30,1,0,'dry','white','Slovakia','{"year": 2025, "batch": "92025", "region": "Moravská Nová Ves – Stará hora", "volume": 0.75, "winery": "Vinařství Zaječí", "alcohol": 12.5, "quality": "Moravské zemské víno", "sugar_at_harvest": 23}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-SK-2025');
INSERT INTO `product` (`franchise_code`,`sku`,`name`,`description`,`price`,`stock_quantity`,`published`,`deleted`,`kind`,`color`,`variant`,`data`)
SELECT 'zajeci','ZAJ-MM-2025','Moravský muškát 2025','Moravské zemské víno. Vinařská obec Zaječí, viniční trať Nová Hora. Cukernatost hroznů při sběru 23 °NM, zbytkový cukr cca 25 g/l. Kvašeno a školeno ve skle, bez použití selektovaných kvasinek a enzymů.','220.00',35,1,0,'semi_sweet','white','Moravský muškát','{"year": 2025, "batch": "22025", "region": "Zaječí – Nová Hora", "volume": 0.75, "winery": "Vinařství Zaječí", "alcohol": 13.0, "quality": "Moravské zemské víno", "sugar_at_harvest": 23}'
WHERE NOT EXISTS (SELECT 1 FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-MM-2025');

-- product_category
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-MT-2025'),(SELECT `id` FROM `category` WHERE `franchise_code`='zajeci' AND `syscode`='top')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-MT-2025') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zajeci' AND `syscode`='top'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-SZ-2025'),(SELECT `id` FROM `category` WHERE `franchise_code`='zajeci' AND `syscode`='top')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-SZ-2025') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zajeci' AND `syscode`='top'));
INSERT INTO `product_category` (`product_id`,`category_id`)
SELECT (SELECT `id` FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-NB-2025'),(SELECT `id` FROM `category` WHERE `franchise_code`='zajeci' AND `syscode`='top')
WHERE NOT EXISTS (SELECT 1 FROM `product_category` WHERE `product_id`=(SELECT `id` FROM `product` WHERE `franchise_code`='zajeci' AND `sku`='ZAJ-NB-2025') AND `category_id`=(SELECT `id` FROM `category` WHERE `franchise_code`='zajeci' AND `syscode`='top'));

COMMIT;
