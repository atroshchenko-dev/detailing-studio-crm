-- Trending EVs and Additional Brands Migration
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- 1. Add New Makes
-- --------------------------------------------------------
INSERT IGNORE INTO car_makes (name) VALUES 
('Li Auto'), ('Voyah'), ('Avatr'), ('Tank'), ('Hongqi'), 
('ORA'), ('Skywell'), ('Neta'), ('HiPhi'), ('Leapmotor'), 
('Aito'), ('Arcfox'), ('Jetour'), ('Maxus'), ('Polestones');

-- --------------------------------------------------------
-- 2. Add Models
-- --------------------------------------------------------

-- Li Auto (LiXiang)
SET @id = (SELECT id FROM car_makes WHERE name = 'Li Auto');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'L6'), (@id, 'L7'), (@id, 'L8'), (@id, 'L9'), (@id, 'One'), (@id, 'Mega');

-- Voyah
SET @id = (SELECT id FROM car_makes WHERE name = 'Voyah');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Free'), (@id, 'Dream'), (@id, 'Passion (Zhuiguang)');

-- Avatr
SET @id = (SELECT id FROM car_makes WHERE name = 'Avatr');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '11'), (@id, '12');

-- Tank
SET @id = (SELECT id FROM car_makes WHERE name = 'Tank');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '300'), (@id, '400'), (@id, '500'), (@id, '700');

-- Hongqi
SET @id = (SELECT id FROM car_makes WHERE name = 'Hongqi');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'E-HS9'), (@id, 'H5'), (@id, 'H9'), (@id, 'HS5'), (@id, 'HS7');

-- ORA
SET @id = (SELECT id FROM car_makes WHERE name = 'ORA');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '03 (Funky Cat)'), (@id, '07 (Lightning Cat)'), (@id, 'Good Cat'), (@id, 'Ballet Cat');

-- Skywell
SET @id = (SELECT id FROM car_makes WHERE name = 'Skywell');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'ET5'), (@id, 'HT-i');

-- Neta
SET @id = (SELECT id FROM car_makes WHERE name = 'Neta');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'S'), (@id, 'U'), (@id, 'V'), (@id, 'GT');

-- HiPhi
SET @id = (SELECT id FROM car_makes WHERE name = 'HiPhi');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'X'), (@id, 'Y'), (@id, 'Z');

-- Leapmotor
SET @id = (SELECT id FROM car_makes WHERE name = 'Leapmotor');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'C01'), (@id, 'C10'), (@id, 'C11'), (@id, 'T03');

-- Aito
SET @id = (SELECT id FROM car_makes WHERE name = 'Aito');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'M5'), (@id, 'M7'), (@id, 'M9');

-- Arcfox
SET @id = (SELECT id FROM car_makes WHERE name = 'Arcfox');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Alpha S'), (@id, 'Alpha T'), (@id, 'Kaola');

-- Jetour (Expanding if exists, or adding new)
SET @id = (SELECT id FROM car_makes WHERE name = 'Jetour');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'X70'), (@id, 'X70 Plus'), (@id, 'X90'), (@id, 'X90 Plus'), (@id, 'Dashing'), (@id, 'Traveller');

-- Maxus
SET @id = (SELECT id FROM car_makes WHERE name = 'Maxus');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Mifa 9'), (@id, 'D90'), (@id, 'G50'), (@id, 'T90');

-- Polestones
SET @id = (SELECT id FROM car_makes WHERE name = 'Polestones');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '01');

-- ZEEKR (Update with missing)
SET @id = (SELECT id FROM car_makes WHERE name = 'ZEEKR');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Mix');

-- Xiaomi
INSERT IGNORE INTO car_makes (name) VALUES ('Xiaomi');
SET @id = (SELECT id FROM car_makes WHERE name = 'Xiaomi');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'SU7');
