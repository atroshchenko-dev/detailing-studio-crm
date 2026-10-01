-- Add more makes
INSERT IGNORE INTO car_makes (name) VALUES 
('Aston Martin'), ('BYD'), ('Chery'), ('Chevrolet'), ('Chrysler'), 
('Dacia'), ('Daewoo'), ('Dodge'), ('DS'), ('Fiat'), 
('Geely'), ('GMC'), ('Great Wall'), ('Haval'), ('Infiniti'), 
('Iveco'), ('Jeep'), ('Lancia'), ('Lincoln'), ('Lotus'), 
('MG'), ('MINI'), ('Opel'), ('Peugeot'), ('RAM'), 
('Rolls-Royce'), ('Seat'), ('SsangYong'), ('ZEEKR');

-- BYD
SET @make_id = (SELECT id FROM car_makes WHERE name = 'BYD');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Han'), (@make_id, 'Tang'), (@make_id, 'Song'), (@make_id, 'Qin'), (@make_id, 'Dolphin'), (@make_id, 'Seal'), (@make_id, 'Atto 3');

-- Chery
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Chery');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Tiggo 2'), (@make_id, 'Tiggo 4'), (@make_id, 'Tiggo 7'), (@make_id, 'Tiggo 8'), (@make_id, 'Amulet'), (@make_id, 'QQ');

-- Geely
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Geely');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Coolray'), (@make_id, 'Atlas'), (@make_id, 'Tugella'), (@make_id, 'Monjaro'), (@make_id, 'Emgrand'), (@make_id, 'CK'), (@make_id, 'MK');

-- ZEEKR
SET @make_id = (SELECT id FROM car_makes WHERE name = 'ZEEKR');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, '001'), (@make_id, '009'), (@make_id, 'X');

-- Peugeot
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Peugeot');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, '206'), (@make_id, '207'), (@make_id, '208'), (@make_id, '301'), (@make_id, '307'), (@make_id, '308'), (@make_id, '407'), (@make_id, '508'), (@make_id, '2008'), (@make_id, '3008'), (@make_id, '5008'), (@make_id, 'Partner'), (@make_id, 'Expert'), (@make_id, 'Boxer');

-- Citroen
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Citroen');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'C1'), (@make_id, 'C2'), (@make_id, 'C3'), (@make_id, 'C3 Aircross'), (@make_id, 'C4'), (@make_id, 'C4 Cactus'), (@make_id, 'C4 Picasso'), (@make_id, 'C5'), (@make_id, 'C5 Aircross'), (@make_id, 'Berlingo'), (@make_id, 'Jumpy'), (@make_id, 'Jumper');

-- Renault (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Renault');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Captur'), (@make_id, 'Kadjar'), (@make_id, 'Koleos'), (@make_id, 'Scenic'), (@make_id, 'Kangoo'), (@make_id, 'Trafic'), (@make_id, 'Master'), (@make_id, 'Zoe'), (@make_id, 'Arkana'), (@make_id, 'Fluence'), (@make_id, 'Laguna'), (@make_id, 'Espace');

-- Opel
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Opel');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Corsa'), (@make_id, 'Astra'), (@make_id, 'Insignia'), (@make_id, 'Mokka'), (@make_id, 'Grandland'), (@make_id, 'Crossland'), (@make_id, 'Combo'), (@make_id, 'Vivaro'), (@make_id, 'Zafira'), (@make_id, 'Vectra'), (@make_id, 'Omega'), (@make_id, 'Antara');

-- Ford (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Ford');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Explorer'), (@make_id, 'Edge'), (@make_id, 'EcoSport'), (@make_id, 'Ranger'), (@make_id, 'Transit'), (@make_id, 'Tourneo'), (@make_id, 'F-150'), (@make_id, 'C-Max'), (@make_id, 'S-Max'), (@make_id, 'Galaxy');

-- Chevrolet
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Chevrolet');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Aveo'), (@make_id, 'Lacetti'), (@make_id, 'Cruze'), (@make_id, 'Captiva'), (@make_id, 'Epica'), (@make_id, 'Camaro'), (@make_id, 'Corvette'), (@make_id, 'Tahoe'), (@make_id, 'Suburban'), (@make_id, 'Equinox'), (@make_id, 'Volt'), (@make_id, 'Bolt');

-- Jeep
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Jeep');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Grand Cherokee'), (@make_id, 'Cherokee'), (@make_id, 'Wrangler'), (@make_id, 'Compass'), (@make_id, 'Renegade'), (@make_id, 'Patriot'), (@make_id, 'Gladiator');

-- Fiat
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Fiat');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, '500'), (@make_id, '500L'), (@make_id, '500X'), (@make_id, 'Panda'), (@make_id, 'Tipo'), (@make_id, 'Doblo'), (@make_id, 'Ducato'), (@make_id, 'Fiorino'), (@make_id, 'Scudo'), (@make_id, 'Punto'), (@make_id, 'Freemont');

-- Seat
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Seat');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Ibiza'), (@make_id, 'Leon'), (@make_id, 'Ateca'), (@make_id, 'Tarraco'), (@make_id, 'Arona'), (@make_id, 'Alhambra'), (@make_id, 'Altea'), (@make_id, 'Exeo'), (@make_id, 'Toledo');

-- MINI
SET @make_id = (SELECT id FROM car_makes WHERE name = 'MINI');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Hatch'), (@make_id, 'Countryman'), (@make_id, 'Clubman'), (@make_id, 'Paceman'), (@make_id, 'Convertible');

-- Mitsubishi (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Mitsubishi');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Eclipse Cross'), (@make_id, 'L200'), (@make_id, 'Pajero Sport'), (@make_id, 'Space Star'), (@make_id, 'Grandis');

-- Infiniti
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Infiniti');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Q50'), (@make_id, 'Q60'), (@make_id, 'Q70'), (@make_id, 'QX30'), (@make_id, 'QX50'), (@make_id, 'QX60'), (@make_id, 'QX70'), (@make_id, 'QX80'), (@make_id, 'FX35'), (@make_id, 'FX37'), (@make_id, 'FX50'), (@make_id, 'G35'), (@make_id, 'G37');

-- Acura
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Acura');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'MDX'), (@make_id, 'RDX'), (@make_id, 'TLX'), (@make_id, 'ILX'), (@make_id, 'NSX'), (@make_id, 'ZDX');

-- Dodge
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Dodge');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Challenger'), (@make_id, 'Charger'), (@make_id, 'Durango'), (@make_id, 'Journey'), (@make_id, 'Ram'), (@make_id, 'Dart'), (@make_id, 'Caliber'), (@make_id, 'Grand Caravan');

-- Great Wall / Haval
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Great Wall');
INSERT IGNORE INTO car_models (make_id, name) VALUES (@make_id, 'Wingle'), (@make_id, 'Hover H3'), (@make_id, 'Hover H5'), (@make_id, 'Voleex C30');
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Haval');
INSERT IGNORE INTO car_models (make_id, name) VALUES (@make_id, 'H2'), (@make_id, 'H6'), (@make_id, 'H9'), (@make_id, 'Jolion'), (@make_id, 'Dargo');
