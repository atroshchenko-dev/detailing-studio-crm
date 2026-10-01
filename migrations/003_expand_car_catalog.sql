-- Add missing makes
INSERT IGNORE INTO car_makes (name) VALUES 
('Alfa Romeo'), ('Bentley'), ('Cadillac'), ('Chrysler'), ('Ferrari'), 
('Jaguar'), ('Lamborghini'), ('Maserati'), ('SsangYong'), ('Smart'), ('Rover');

-- Alfa Romeo
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Alfa Romeo');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Giulia'), (@make_id, 'Stelvio'), (@make_id, 'Giulietta');

-- Bentley
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Bentley');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Continental GT'), (@make_id, 'Bentayga'), (@make_id, 'Flying Spur');

-- Ferrari
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Ferrari');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, '488'), (@make_id, 'F8'), (@make_id, 'Roma'), (@make_id, 'Portofino');

-- Jaguar
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Jaguar');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'F-Pace'), (@make_id, 'E-Pace'), (@make_id, 'XF'), (@make_id, 'XE'), (@make_id, 'F-Type');

-- Land Rover (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Land Rover');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Defender'), (@make_id, 'Discovery'), (@make_id, 'Discovery Sport'), 
(@make_id, 'Range Rover'), (@make_id, 'Range Rover Sport'), 
(@make_id, 'Range Rover Velar'), (@make_id, 'Range Rover Evoque');

-- Porsche (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Porsche');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, '911'), (@make_id, 'Cayenne'), (@make_id, 'Macan'), (@make_id, 'Panamera'), (@make_id, 'Taycan'), (@make_id, '718 Boxster');

-- Tesla (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Tesla');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Model S'), (@make_id, 'Model 3'), (@make_id, 'Model X'), (@make_id, 'Model Y');

-- Toyota (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Toyota');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Prius'), (@make_id, 'C-HR'), (@make_id, 'Avensis'), (@make_id, 'Auris'), (@make_id, 'Yaris'), (@make_id, 'Sienna');

-- Volkswagen (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Volkswagen');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Arteon'), (@make_id, 'Polo'), (@make_id, 'Touran'), (@make_id, 'Caddy'), (@make_id, 'Scirocco'), (@make_id, 'Multivan');

-- Audi (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Audi');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'A1'), (@make_id, 'A7'), (@make_id, 'A8'), (@make_id, 'e-tron'), (@make_id, 'TT');

-- BMW (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'BMW');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, '2 Series'), (@make_id, '4 Series'), (@make_id, '8 Series'), (@make_id, 'i3'), (@make_id, 'iX'), (@make_id, 'Z4');

-- Mercedes-Benz (Expanded)
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Mercedes-Benz');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'B-Class'), (@make_id, 'CLA'), (@make_id, 'CLS'), (@make_id, 'GLB'), (@make_id, 'GLE Coupe'), (@make_id, 'GLS'), (@make_id, 'EQS'), (@make_id, 'V-Class');

-- Subaru
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Subaru');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Impreza'), (@make_id, 'Forester'), (@make_id, 'Outback'), (@make_id, 'XV'), (@make_id, 'Legacy'), (@make_id, 'Tribeca');

-- Suzuki
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Suzuki');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'Swift'), (@make_id, 'Vitara'), (@make_id, 'Grand Vitara'), (@make_id, 'Jimny'), (@make_id, 'SX4'), (@make_id, 'Ignis');

-- Lexus
SET @make_id = (SELECT id FROM car_makes WHERE name = 'Lexus');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@make_id, 'IS'), (@make_id, 'ES'), (@make_id, 'GS'), (@make_id, 'LS'), (@make_id, 'NX'), (@make_id, 'RX'), (@make_id, 'GX'), (@make_id, 'LX'), (@make_id, 'UX');
