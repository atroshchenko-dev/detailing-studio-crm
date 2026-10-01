-- Comprehensive Car Catalog Migration
-- Adds a wide range of Makes and Models
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- 1. Ensure new Makes exist
-- --------------------------------------------------------
INSERT IGNORE INTO car_makes (name) VALUES 
('Acura'), ('Alfa Romeo'), ('Aston Martin'), ('Audi'), ('Bentley'), ('BMW'), 
('Bugatti'), ('Buick'), ('BYD'), ('Cadillac'), ('Changan'), ('Chery'), 
('Chevrolet'), ('Chrysler'), ('Citroen'), ('Cupra'), ('Dacia'), ('Daewoo'), 
('Daihatsu'), ('Dodge'), ('DS'), ('FAW'), ('Ferrari'), ('Fiat'), ('Fisker'), 
('Ford'), ('Geely'), ('Genesis'), ('GMC'), ('Great Wall'), ('Haval'), 
('Honda'), ('Hummer'), ('Hyundai'), ('Infiniti'), ('Isuzu'), ('Iveco'), 
('JAC'), ('Jaguar'), ('Jeep'), ('Jetour'), ('Kia'), ('Koenigsegg'), 
('Lamborghini'), ('Lancia'), ('Land Rover'), ('Lexus'), ('Lincoln'), 
('Lotus'), ('Lucid'), ('Maserati'), ('Maybach'), ('Mazda'), ('McLaren'), 
('Mercedes-Benz'), ('MG'), ('MINI'), ('Mitsubishi'), ('Nio'), ('Nissan'), 
('Opel'), ('Pagani'), ('Peugeot'), ('Polestar'), ('Porsche'), ('RAM'), 
('Renault'), ('Rivian'), ('Rolls-Royce'), ('Rover'), ('Saab'), ('Seat'), 
('Skoda'), ('Smart'), ('SsangYong'), ('Subaru'), ('Suzuki'), ('Tesla'), 
('Toyota'), ('Volkswagen'), ('Volvo'), ('Xpeng'), ('ZEEKR');

-- --------------------------------------------------------
-- 2. Add Models (Using Variables to be safe)
-- --------------------------------------------------------

-- Acura
SET @id = (SELECT id FROM car_makes WHERE name = 'Acura');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'CL'), (@id, 'CSX'), (@id, 'EL'), (@id, 'ILX'), (@id, 'Integra'), 
(@id, 'Legend'), (@id, 'MDX'), (@id, 'NSX'), (@id, 'RDX'), (@id, 'RL'), 
(@id, 'RLX'), (@id, 'RSX'), (@id, 'SLX'), (@id, 'TL'), (@id, 'TLX'), 
(@id, 'TSX'), (@id, 'ZDX');

-- Alfa Romeo
SET @id = (SELECT id FROM car_makes WHERE name = 'Alfa Romeo');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '145'), (@id, '146'), (@id, '147'), (@id, '155'), (@id, '156'), 
(@id, '159'), (@id, '164'), (@id, '166'), (@id, '4C'), (@id, '8C'), 
(@id, 'Brera'), (@id, 'Giulia'), (@id, 'Giulietta'), (@id, 'GT'), 
(@id, 'GTV'), (@id, 'MiTo'), (@id, 'Spider'), (@id, 'Stelvio'), (@id, 'Tonale');

-- Audi
SET @id = (SELECT id FROM car_makes WHERE name = 'Audi');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '80'), (@id, '90'), (@id, '100'), (@id, '200'), (@id, 'A1'), 
(@id, 'A2'), (@id, 'A3'), (@id, 'A4'), (@id, 'A4 Allroad'), (@id, 'A5'), 
(@id, 'A6'), (@id, 'A6 Allroad'), (@id, 'A7'), (@id, 'A8'), (@id, 'Cabriolet'), 
(@id, 'Coupe'), (@id, 'e-tron'), (@id, 'e-tron GT'), (@id, 'Q2'), (@id, 'Q3'), 
(@id, 'Q4 e-tron'), (@id, 'Q5'), (@id, 'Q7'), (@id, 'Q8'), (@id, 'Q8 e-tron'), 
(@id, 'R8'), (@id, 'RS3'), (@id, 'RS4'), (@id, 'RS5'), (@id, 'RS6'), 
(@id, 'RS7'), (@id, 'RS Q3'), (@id, 'RS Q8'), (@id, 'S1'), (@id, 'S3'), 
(@id, 'S4'), (@id, 'S5'), (@id, 'S6'), (@id, 'S7'), (@id, 'S8'), 
(@id, 'SQ2'), (@id, 'SQ5'), (@id, 'SQ7'), (@id, 'SQ8'), (@id, 'TT'), 
(@id, 'TTS'), (@id, 'TTRS'), (@id, 'V8');

-- BMW
SET @id = (SELECT id FROM car_makes WHERE name = 'BMW');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '1 Series'), (@id, '2 Series'), (@id, '3 Series'), (@id, '4 Series'), 
(@id, '5 Series'), (@id, '6 Series'), (@id, '7 Series'), (@id, '8 Series'), 
(@id, 'i3'), (@id, 'i4'), (@id, 'i5'), (@id, 'i7'), (@id, 'i8'), 
(@id, 'iX'), (@id, 'iX1'), (@id, 'iX3'), (@id, 'M2'), (@id, 'M3'), 
(@id, 'M4'), (@id, 'M5'), (@id, 'M6'), (@id, 'M8'), (@id, 'X1'), 
(@id, 'X2'), (@id, 'X3'), (@id, 'X3 M'), (@id, 'X4'), (@id, 'X4 M'), 
(@id, 'X5'), (@id, 'X5 M'), (@id, 'X6'), (@id, 'X6 M'), (@id, 'X7'), 
(@id, 'XM'), (@id, 'Z3'), (@id, 'Z4'), (@id, 'Z8');

-- BYD
SET @id = (SELECT id FROM car_makes WHERE name = 'BYD');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Dolphin'), (@id, 'F3'), (@id, 'F6'), (@id, 'Flyer'), (@id, 'G3'), 
(@id, 'G6'), (@id, 'Han'), (@id, 'Qin'), (@id, 'Qin Plus'), (@id, 'S6'), 
(@id, 'Seal'), (@id, 'Song'), (@id, 'Song Plus'), (@id, 'Tang'), 
(@id, 'Yuan'), (@id, 'Yuan Plus'), (@id, 'e6'), (@id, 'Seagull'), (@id, 'Destroyer 05');

-- Cadillac
SET @id = (SELECT id FROM car_makes WHERE name = 'Cadillac');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'ATS'), (@id, 'BLS'), (@id, 'CT4'), (@id, 'CT5'), (@id, 'CT6'), 
(@id, 'CTS'), (@id, 'DeVille'), (@id, 'DTS'), (@id, 'Eldorado'), 
(@id, 'Escalade'), (@id, 'Lyriq'), (@id, 'Seville'), (@id, 'SRX'), 
(@id, 'STS'), (@id, 'XLR'), (@id, 'XT4'), (@id, 'XT5'), (@id, 'XT6'), (@id, 'XTS');

-- Chery
SET @id = (SELECT id FROM car_makes WHERE name = 'Chery');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Amulet'), (@id, 'Arrizo 8'), (@id, 'Beat'), (@id, 'CrossEastar'), 
(@id, 'E5'), (@id, 'Eastar'), (@id, 'Elara'), (@id, 'Fora'), (@id, 'Jaggil'), 
(@id, 'Kimo'), (@id, 'M11'), (@id, 'QQ'), (@id, 'Tiggo'), (@id, 'Tiggo 2'), 
(@id, 'Tiggo 3'), (@id, 'Tiggo 4'), (@id, 'Tiggo 4 Pro'), (@id, 'Tiggo 7'), 
(@id, 'Tiggo 7 Pro'), (@id, 'Tiggo 8'), (@id, 'Tiggo 8 Pro'), (@id, 'Tiggo 9');

-- Chevrolet
SET @id = (SELECT id FROM car_makes WHERE name = 'Chevrolet');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Avalanche'), (@id, 'Aveo'), (@id, 'Blazer'), (@id, 'Bolt'), 
(@id, 'Bolt EUV'), (@id, 'Camaro'), (@id, 'Captiva'), (@id, 'Cavalier'), 
(@id, 'Cobalt'), (@id, 'Colorado'), (@id, 'Corvette'), (@id, 'Cruze'), 
(@id, 'Epica'), (@id, 'Equinox'), (@id, 'Evanda'), (@id, 'Express'), 
(@id, 'HHR'), (@id, 'Impala'), (@id, 'Lacetti'), (@id, 'Lanos'), 
(@id, 'Malibu'), (@id, 'Niva'), (@id, 'Nubira'), (@id, 'Orlando'), 
(@id, 'Silverado'), (@id, 'Sonic'), (@id, 'Spark'), (@id, 'Suburban'), 
(@id, 'Tacuma'), (@id, 'Tahoe'), (@id, 'Tracker'), (@id, 'TrailBlazer'), 
(@id, 'Traverse'), (@id, 'Trax'), (@id, 'Volt');

-- Chrysler
SET @id = (SELECT id FROM car_makes WHERE name = 'Chrysler');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '200'), (@id, '300C'), (@id, '300M'), (@id, 'Aspen'), (@id, 'Crossfire'), 
(@id, 'Grand Voyager'), (@id, 'Neon'), (@id, 'Pacifica'), (@id, 'PT Cruiser'), 
(@id, 'Sebring'), (@id, 'Town & Country'), (@id, 'Voyager');

-- Citroen
SET @id = (SELECT id FROM car_makes WHERE name = 'Citroen');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'AX'), (@id, 'Berlingo'), (@id, 'BX'), (@id, 'C-Crosser'), 
(@id, 'C-Elysee'), (@id, 'C-Zero'), (@id, 'C1'), (@id, 'C2'), (@id, 'C3'), 
(@id, 'C3 Aircross'), (@id, 'C3 Picasso'), (@id, 'C4'), (@id, 'C4 Aircross'), 
(@id, 'C4 Cactus'), (@id, 'C4 Picasso'), (@id, 'C4 SpaceTourer'), 
(@id, 'C4 X'), (@id, 'C5'), (@id, 'C5 Aircross'), (@id, 'C5 X'), 
(@id, 'C6'), (@id, 'C8'), (@id, 'DS3'), (@id, 'DS4'), (@id, 'DS5'), 
(@id, 'E-Mehari'), (@id, 'Grand C4 Picasso'), (@id, 'Jumper'), (@id, 'Jumpy'), 
(@id, 'Nemo'), (@id, 'Saxo'), (@id, 'SpaceTourer'), (@id, 'Xantia'), 
(@id, 'XM'), (@id, 'Xsara'), (@id, 'Xsara Picasso'), (@id, 'ZX');

-- Cupra
SET @id = (SELECT id FROM car_makes WHERE name = 'Cupra');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Ateca'), (@id, 'Born'), (@id, 'Formentor'), (@id, 'Leon'), (@id, 'Tavascan');

-- Dacia
SET @id = (SELECT id FROM car_makes WHERE name = 'Dacia');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Dokker'), (@id, 'Duster'), (@id, 'Jogger'), (@id, 'Lodgy'), 
(@id, 'Logan'), (@id, 'Sandero'), (@id, 'Spring');

-- Daewoo
SET @id = (SELECT id FROM car_makes WHERE name = 'Daewoo');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Espero'), (@id, 'Gentra'), (@id, 'Kalos'), (@id, 'Lacetti'), 
(@id, 'Lanos'), (@id, 'Leganza'), (@id, 'Magnus'), (@id, 'Matiz'), 
(@id, 'Nexia'), (@id, 'Nubira'), (@id, 'Sens'), (@id, 'Tacuma');

-- Dodge
SET @id = (SELECT id FROM car_makes WHERE name = 'Dodge');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Avenger'), (@id, 'Caliber'), (@id, 'Caravan'), (@id, 'Challenger'), 
(@id, 'Charger'), (@id, 'Dakota'), (@id, 'Dart'), (@id, 'Durango'), 
(@id, 'Grand Caravan'), (@id, 'Hornet'), (@id, 'Journey'), (@id, 'Magnum'), 
(@id, 'Neon'), (@id, 'Nitro'), (@id, 'RAM'), (@id, 'Stratus'), (@id, 'Viper');

-- Fiat
SET @id = (SELECT id FROM car_makes WHERE name = 'Fiat');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '124 Spider'), (@id, '500'), (@id, '500L'), (@id, '500X'), 
(@id, '500e'), (@id, 'Albea'), (@id, 'Brava'), (@id, 'Bravo'), 
(@id, 'Cinquecento'), (@id, 'Coupe'), (@id, 'Croma'), (@id, 'Doblo'), 
(@id, 'Ducato'), (@id, 'Fiorino'), (@id, 'Freemont'), (@id, 'Fullback'), 
(@id, 'Grande Punto'), (@id, 'Idea'), (@id, 'Linea'), (@id, 'Marea'), 
(@id, 'Multipla'), (@id, 'Palio'), (@id, 'Panda'), (@id, 'Punto'), 
(@id, 'Qubo'), (@id, 'Scudo'), (@id, 'Sedici'), (@id, 'Seicento'), 
(@id, 'Stilo'), (@id, 'Strada'), (@id, 'Talento'), (@id, 'Tempra'), 
(@id, 'Tipo'), (@id, 'Ulysse'), (@id, 'Uno');

-- Ford
SET @id = (SELECT id FROM car_makes WHERE name = 'Ford');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Aerostar'), (@id, 'B-Max'), (@id, 'Bronco'), (@id, 'Bronco Sport'), 
(@id, 'C-Max'), (@id, 'Capri'), (@id, 'Cougar'), (@id, 'Crown Victoria'), 
(@id, 'EcoSport'), (@id, 'Edge'), (@id, 'Escape'), (@id, 'Escort'), 
(@id, 'Excursion'), (@id, 'Expedition'), (@id, 'Explorer'), (@id, 'F-150'), 
(@id, 'F-250'), (@id, 'F-350'), (@id, 'Fiesta'), (@id, 'Five Hundred'), 
(@id, 'Flex'), (@id, 'Focus'), (@id, 'Freestar'), (@id, 'Freestyle'), 
(@id, 'Fusion'), (@id, 'Galaxy'), (@id, 'Granada'), (@id, 'Grand C-Max'), 
(@id, 'Ka'), (@id, 'Kuga'), (@id, 'Maverick'), (@id, 'Mondeo'), 
(@id, 'Mustang'), (@id, 'Mustang Mach-E'), (@id, 'Probe'), (@id, 'Puma'), 
(@id, 'Ranger'), (@id, 'S-Max'), (@id, 'Scorpio'), (@id, 'Sierra'), 
(@id, 'Taurus'), (@id, 'Tourneo'), (@id, 'Tourneo Connect'), 
(@id, 'Tourneo Courier'), (@id, 'Tourneo Custom'), (@id, 'Transit'), 
(@id, 'Transit Connect'), (@id, 'Transit Courier'), (@id, 'Transit Custom'), 
(@id, 'Windstar');

-- Geely
SET @id = (SELECT id FROM car_makes WHERE name = 'Geely');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Atlas'), (@id, 'Atlas Pro'), (@id, 'CK'), (@id, 'Coolray'), 
(@id, 'Emgrand 7'), (@id, 'Emgrand EC7'), (@id, 'Emgrand EC8'), 
(@id, 'Emgrand X7'), (@id, 'FC'), (@id, 'GC5'), (@id, 'GC6'), 
(@id, 'GC7'), (@id, 'LC (Panda)'), (@id, 'MK'), (@id, 'MK Cross'), 
(@id, 'Monjaro'), (@id, 'SL'), (@id, 'Tugella'), (@id, 'Geometry C'), (@id, 'Geometry A');

-- Genesis
SET @id = (SELECT id FROM car_makes WHERE name = 'Genesis');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'G70'), (@id, 'G80'), (@id, 'G90'), (@id, 'GV60'), 
(@id, 'GV70'), (@id, 'GV80');

-- Haval
SET @id = (SELECT id FROM car_makes WHERE name = 'Haval');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Dargo'), (@id, 'H2'), (@id, 'H6'), (@id, 'H6 GT'), 
(@id, 'H9'), (@id, 'Jolion'), (@id, 'M6');

-- Honda
SET @id = (SELECT id FROM car_makes WHERE name = 'Honda');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Accord'), (@id, 'Crosstour'), (@id, 'City'), (@id, 'Civic'), 
(@id, 'CR-V'), (@id, 'CR-Z'), (@id, 'Crosstour'), (@id, 'e'), 
(@id, 'e:NS1'), (@id, 'Elysion'), (@id, 'Fit'), (@id, 'FR-V'), 
(@id, 'HR-V'), (@id, 'Insight'), (@id, 'Integra'), (@id, 'Jazz'), 
(@id, 'Legend'), (@id, 'M-NV'), (@id, 'Odyssey'), (@id, 'Passport'), 
(@id, 'Pilot'), (@id, 'Prelude'), (@id, 'Ridgeline'), (@id, 'S2000'), 
(@id, 'Shuttle'), (@id, 'Stream'), (@id, 'ZR-V');

-- Hyundai
SET @id = (SELECT id FROM car_makes WHERE name = 'Hyundai');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Accent'), (@id, 'Atos'), (@id, 'Avante'), (@id, 'Azera'), 
(@id, 'Bayon'), (@id, 'Coupe'), (@id, 'Creta'), (@id, 'Elantra'), 
(@id, 'Equus'), (@id, 'Galloper'), (@id, 'Genesis'), (@id, 'Getz'), 
(@id, 'Grand Santa Fe'), (@id, 'Grandeur'), (@id, 'H-1'), (@id, 'H200'), 
(@id, 'i10'), (@id, 'i20'), (@id, 'i30'), (@id, 'i40'), (@id, 'IONIQ'), 
(@id, 'IONIQ 5'), (@id, 'IONIQ 6'), (@id, 'ix20'), (@id, 'ix35'), 
(@id, 'ix55 (Veracruz)'), (@id, 'Kona'), (@id, 'Lantra'), (@id, 'Matrix'), 
(@id, 'Palisade'), (@id, 'Santa Fe'), (@id, 'Solaris'), (@id, 'Sonata'), 
(@id, 'Staria'), (@id, 'Terracan'), (@id, 'Tiburon'), (@id, 'Trajet'), 
(@id, 'Tucson'), (@id, 'Veloster'), (@id, 'Venue');

-- Infiniti
SET @id = (SELECT id FROM car_makes WHERE name = 'Infiniti');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'EX'), (@id, 'FX'), (@id, 'G'), (@id, 'I'), (@id, 'J'), 
(@id, 'JX'), (@id, 'M'), (@id, 'Q30'), (@id, 'Q40'), (@id, 'Q45'), 
(@id, 'Q50'), (@id, 'Q60'), (@id, 'Q70'), (@id, 'Q80'), (@id, 'QX30'), 
(@id, 'QX4'), (@id, 'QX50'), (@id, 'QX55'), (@id, 'QX56'), (@id, 'QX60'), 
(@id, 'QX70'), (@id, 'QX80');

-- Jaguar
SET @id = (SELECT id FROM car_makes WHERE name = 'Jaguar');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'E-Pace'), (@id, 'F-Pace'), (@id, 'F-Type'), (@id, 'I-Pace'), 
(@id, 'S-Type'), (@id, 'X-Type'), (@id, 'XE'), (@id, 'XF'), 
(@id, 'XJ'), (@id, 'XK');

-- Jeep
SET @id = (SELECT id FROM car_makes WHERE name = 'Jeep');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Avenger'), (@id, 'Cherokee'), (@id, 'Commander'), (@id, 'Compass'), 
(@id, 'Gladiator'), (@id, 'Grand Cherokee'), (@id, 'Liberty'), 
(@id, 'Patriot'), (@id, 'Renegade'), (@id, 'Wagoneer'), (@id, 'Wrangler');

-- Kia
SET @id = (SELECT id FROM car_makes WHERE name = 'Kia');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Carens'), (@id, 'Carnival'), (@id, 'Ceed'), (@id, 'Cerato'), 
(@id, 'Clarus'), (@id, 'EV6'), (@id, 'EV9'), (@id, 'Forte'), 
(@id, 'Joice'), (@id, 'K5'), (@id, 'K7'), (@id, 'K9'), 
(@id, 'Magentis'), (@id, 'Mohave'), (@id, 'Niro'), (@id, 'Opirus'), 
(@id, 'Optima'), (@id, 'Picanto'), (@id, 'Pregio'), (@id, 'Pride'), 
(@id, 'ProCeed'), (@id, 'Rio'), (@id, 'Sedona'), (@id, 'Seltos'), 
(@id, 'Sephia'), (@id, 'Shuma'), (@id, 'Sorento'), (@id, 'Soul'), 
(@id, 'Spectra'), (@id, 'Sportage'), (@id, 'Stinger'), (@id, 'Stonic'), 
(@id, 'Telluride'), (@id, 'Venga'), (@id, 'XCeed');

-- Land Rover
SET @id = (SELECT id FROM car_makes WHERE name = 'Land Rover');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Defender'), (@id, 'Discovery'), (@id, 'Discovery Sport'), 
(@id, 'Freelander'), (@id, 'Range Rover'), (@id, 'Range Rover Evoque'), 
(@id, 'Range Rover Sport'), (@id, 'Range Rover Velar');

-- Lexus
SET @id = (SELECT id FROM car_makes WHERE name = 'Lexus');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'CT'), (@id, 'ES'), (@id, 'GS'), (@id, 'GX'), (@id, 'HS'), 
(@id, 'IS'), (@id, 'LC'), (@id, 'LFA'), (@id, 'LS'), (@id, 'LX'), 
(@id, 'NX'), (@id, 'RC'), (@id, 'RX'), (@id, 'RZ'), (@id, 'SC'), 
(@id, 'TX'), (@id, 'UX');

-- Mazda
SET @id = (SELECT id FROM car_makes WHERE name = 'Mazda');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '121'), (@id, '2'), (@id, '3'), (@id, '323'), (@id, '5'), 
(@id, '6'), (@id, '626'), (@id, '929'), (@id, 'B-Series'), 
(@id, 'BT-50'), (@id, 'CX-3'), (@id, 'CX-30'), (@id, 'CX-5'), 
(@id, 'CX-50'), (@id, 'CX-60'), (@id, 'CX-7'), (@id, 'CX-9'), 
(@id, 'CX-90'), (@id, 'Demio'), (@id, 'MPV'), (@id, 'MX-3'), 
(@id, 'MX-30'), (@id, 'MX-5'), (@id, 'MX-6'), (@id, 'Premacy'), 
(@id, 'RX-7'), (@id, 'RX-8'), (@id, 'Tribute'), (@id, 'Xedos 6'), 
(@id, 'Xedos 9');

-- Mercedes-Benz
SET @id = (SELECT id FROM car_makes WHERE name = 'Mercedes-Benz');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'A-Class'), (@id, 'AMG GT'), (@id, 'B-Class'), (@id, 'C-Class'), 
(@id, 'Citan'), (@id, 'CL-Class'), (@id, 'CLA-Class'), (@id, 'CLC-Class'), 
(@id, 'CLK-Class'), (@id, 'CLS-Class'), (@id, 'E-Class'), (@id, 'EQA'), 
(@id, 'EQB'), (@id, 'EQC'), (@id, 'EQE'), (@id, 'EQE SUV'), 
(@id, 'EQS'), (@id, 'EQS SUV'), (@id, 'EQV'), (@id, 'G-Class'), 
(@id, 'GL-Class'), (@id, 'GLA-Class'), (@id, 'GLB-Class'), (@id, 'GLC-Class'), 
(@id, 'GLC Coupe'), (@id, 'GLE-Class'), (@id, 'GLE Coupe'), (@id, 'GLK-Class'), 
(@id, 'GLS-Class'), (@id, 'M-Class'), (@id, 'Metris'), (@id, 'R-Class'), 
(@id, 'S-Class'), (@id, 'SL-Class'), (@id, 'SLC-Class'), (@id, 'SLK-Class'), 
(@id, 'SLR McLaren'), (@id, 'SLS AMG'), (@id, 'Sprinter'), (@id, 'T-Class'), 
(@id, 'V-Class'), (@id, 'Vaneo'), (@id, 'Viano'), (@id, 'Vito'), (@id, 'X-Class');

-- MINI
SET @id = (SELECT id FROM car_makes WHERE name = 'MINI');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Clubman'), (@id, 'Convertible'), (@id, 'Cooper'), (@id, 'Cooper S'), 
(@id, 'Countryman'), (@id, 'Coupe'), (@id, 'Hatch'), (@id, 'One'), 
(@id, 'Paceman'), (@id, 'Roadster');

-- Mitsubishi
SET @id = (SELECT id FROM car_makes WHERE name = 'Mitsubishi');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '3000 GT'), (@id, 'ASX'), (@id, 'Carisma'), (@id, 'Colt'), 
(@id, 'Eclipse'), (@id, 'Eclipse Cross'), (@id, 'Galant'), (@id, 'Grandis'), 
(@id, 'i-MiEV'), (@id, 'L200'), (@id, 'L300'), (@id, 'L400'), 
(@id, 'Lancer'), (@id, 'Lancer Evolution'), (@id, 'Mirage'), (@id, 'Outlander'), 
(@id, 'Outlander Sport'), (@id, 'Outlander XL'), (@id, 'Pajero'), 
(@id, 'Pajero Pinin'), (@id, 'Pajero Sport'), (@id, 'Pajero Wagon'), 
(@id, 'Space Gear'), (@id, 'Space Runner'), (@id, 'Space Star'), 
(@id, 'Space Wagon');

-- Nissan
SET @id = (SELECT id FROM car_makes WHERE name = 'Nissan');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '350Z'), (@id, '370Z'), (@id, 'Almera'), (@id, 'Almera Classic'), 
(@id, 'Altima'), (@id, 'Ariya'), (@id, 'Armada'), (@id, 'Bluebird'), 
(@id, 'Cube'), (@id, 'e-NV200'), (@id, 'GT-R'), (@id, 'Juke'), 
(@id, 'Kicks'), (@id, 'Leaf'), (@id, 'Maxima'), (@id, 'Micra'), 
(@id, 'Murano'), (@id, 'Navara'), (@id, 'Note'), (@id, 'NP300'), 
(@id, 'NV200'), (@id, 'Pathfinder'), (@id, 'Patrol'), (@id, 'Primera'), 
(@id, 'Pulsar'), (@id, 'Qashqai'), (@id, 'Qashqai+2'), (@id, 'Quest'), 
(@id, 'Rogue'), (@id, 'Sentra'), (@id, 'Skyline'), (@id, 'Sunny'), 
(@id, 'Teana'), (@id, 'Terrano'), (@id, 'Tiida'), (@id, 'Titan'), 
(@id, 'Townstar'), (@id, 'X-Terra'), (@id, 'X-Trail');

-- Opel
SET @id = (SELECT id FROM car_makes WHERE name = 'Opel');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Adam'), (@id, 'Agila'), (@id, 'Ampera'), (@id, 'Ampera-e'), 
(@id, 'Antara'), (@id, 'Ascona'), (@id, 'Astra'), (@id, 'Calibra'), 
(@id, 'Cascada'), (@id, 'Combo'), (@id, 'Combo-e'), (@id, 'Corsa'), 
(@id, 'Corsa-e'), (@id, 'Crossland'), (@id, 'Crossland X'), (@id, 'Frontera'), 
(@id, 'Grandland'), (@id, 'Grandland X'), (@id, 'Insignia'), (@id, 'Kadett'), 
(@id, 'Karl'), (@id, 'Manta'), (@id, 'Meriva'), (@id, 'Mokka'), 
(@id, 'Mokka-e'), (@id, 'Monterey'), (@id, 'Movano'), (@id, 'Omega'), 
(@id, 'Rekord'), (@id, 'Rocks-e'), (@id, 'Senator'), (@id, 'Signum'), 
(@id, 'Sintra'), (@id, 'Tigra'), (@id, 'Vectra'), (@id, 'Vivaro'), 
(@id, 'Zafira'), (@id, 'Zafira Life');

-- Peugeot
SET @id = (SELECT id FROM car_makes WHERE name = 'Peugeot');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '1007'), (@id, '106'), (@id, '107'), (@id, '108'), 
(@id, '2008'), (@id, '205'), (@id, '206'), (@id, '207'), 
(@id, '208'), (@id, '3008'), (@id, '301'), (@id, '306'), 
(@id, '307'), (@id, '308'), (@id, '309'), (@id, '4007'), 
(@id, '4008'), (@id, '405'), (@id, '406'), (@id, '407'), 
(@id, '408'), (@id, '5008'), (@id, '508'), (@id, '605'), 
(@id, '607'), (@id, '806'), (@id, '807'), (@id, 'Bipper'), 
(@id, 'Boxer'), (@id, 'Expert'), (@id, 'iOn'), (@id, 'Landtrek'), 
(@id, 'Partner'), (@id, 'RCZ'), (@id, 'Rifter'), (@id, 'Traveller');

-- Porsche
SET @id = (SELECT id FROM car_makes WHERE name = 'Porsche');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '718 Boxster'), (@id, '718 Cayman'), (@id, '911'), (@id, '918 Spyder'), 
(@id, '924'), (@id, '928'), (@id, '944'), (@id, '968'), 
(@id, 'Boxster'), (@id, 'Carrera GT'), (@id, 'Cayenne'), (@id, 'Cayenne Coupe'), 
(@id, 'Cayman'), (@id, 'Macan'), (@id, 'Panamera'), (@id, 'Taycan');

-- Renault
SET @id = (SELECT id FROM car_makes WHERE name = 'Renault');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '19'), (@id, '21'), (@id, '25'), (@id, 'Arkana'), 
(@id, 'Austral'), (@id, 'Avantime'), (@id, 'Captur'), (@id, 'Clio'), 
(@id, 'Dokker'), (@id, 'Duster'), (@id, 'Espace'), (@id, 'Express'), 
(@id, 'Fluence'), (@id, 'Grand Espace'), (@id, 'Grand Modus'), 
(@id, 'Grand Scenic'), (@id, 'Kadjar'), (@id, 'Kangoo'), (@id, 'Koleos'), 
(@id, 'Laguna'), (@id, 'Latitude'), (@id, 'Lodgy'), (@id, 'Logan'), 
(@id, 'Master'), (@id, 'Megane'), (@id, 'Megane E-Tech'), (@id, 'Modus'), 
(@id, 'Rafale'), (@id, 'Safrane'), (@id, 'Sandero'), (@id, 'Scenic'), 
(@id, 'Symbol'), (@id, 'Talisman'), (@id, 'Thalia'), (@id, 'Trafic'), 
(@id, 'Twingo'), (@id, 'Twizy'), (@id, 'Vel Satis'), (@id, 'Wind'), 
(@id, 'Zoe');

-- Seat
SET @id = (SELECT id FROM car_makes WHERE name = 'Seat');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Alhambra'), (@id, 'Altea'), (@id, 'Altea XL'), (@id, 'Arona'), 
(@id, 'Arosa'), (@id, 'Ateca'), (@id, 'Cordoba'), (@id, 'Exeo'), 
(@id, 'Ibiza'), (@id, 'Leon'), (@id, 'Mii'), (@id, 'Tarraco'), 
(@id, 'Toledo');

-- Skoda
SET @id = (SELECT id FROM car_makes WHERE name = 'Skoda');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Citigo'), (@id, 'Enyaq'), (@id, 'Fabia'), (@id, 'Favorit'), 
(@id, 'Felicia'), (@id, 'Kamiq'), (@id, 'Karoq'), (@id, 'Kodiaq'), 
(@id, 'Octavia'), (@id, 'Octavia Tour'), (@id, 'Praktik'), (@id, 'Rapid'), 
(@id, 'Roomster'), (@id, 'Scala'), (@id, 'Superb'), (@id, 'Yeti');

-- Subaru
SET @id = (SELECT id FROM car_makes WHERE name = 'Subaru');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Ascent'), (@id, 'B9 Tribeca'), (@id, 'BRZ'), (@id, 'Crosstrek'), 
(@id, 'Forester'), (@id, 'Impreza'), (@id, 'Impreza WRX'), 
(@id, 'Impreza WRX STI'), (@id, 'Justy'), (@id, 'Legacy'), (@id, 'Levorg'), 
(@id, 'Outback'), (@id, 'Solterra'), (@id, 'Tribeca'), (@id, 'XV');

-- Tesla
SET @id = (SELECT id FROM car_makes WHERE name = 'Tesla');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Cybertruck'), (@id, 'Model 3'), (@id, 'Model S'), (@id, 'Model X'), 
(@id, 'Model Y'), (@id, 'Roadster');

-- Toyota
SET @id = (SELECT id FROM car_makes WHERE name = 'Toyota');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '4Runner'), (@id, 'Alphard'), (@id, 'Auris'), (@id, 'Avalon'), 
(@id, 'Avensis'), (@id, 'Avensis Verso'), (@id, 'Aygo'), (@id, 'Aygo X'), 
(@id, 'bZ4X'), (@id, 'C-HR'), (@id, 'Camry'), (@id, 'Carina'), 
(@id, 'Carina E'), (@id, 'Celica'), (@id, 'Corolla'), (@id, 'Corolla Cross'), 
(@id, 'Corolla Verso'), (@id, 'Crown'), (@id, 'FJ Cruiser'), (@id, 'Fortuner'), 
(@id, 'Grand Highlander'), (@id, 'GT86'), (@id, 'Highlander'), (@id, 'Hilux'), 
(@id, 'iQ'), (@id, 'Land Cruiser'), (@id, 'Land Cruiser Prado'), (@id, 'Matrix'), 
(@id, 'Mirai'), (@id, 'MR2'), (@id, 'Previa'), (@id, 'Prius'), 
(@id, 'Prius+'), (@id, 'Proace'), (@id, 'Proace City'), (@id, 'RAV4'), 
(@id, 'Sequoia'), (@id, 'Sienna'), (@id, 'Solara'), (@id, 'Supra'), 
(@id, 'Tacoma'), (@id, 'Tundra'), (@id, 'Urban Cruiser'), (@id, 'Venza'), 
(@id, 'Verso'), (@id, 'Verso-S'), (@id, 'Yaris'), (@id, 'Yaris Cross');

-- Volkswagen
SET @id = (SELECT id FROM car_makes WHERE name = 'Volkswagen');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, 'Amarok'), (@id, 'Arteon'), (@id, 'Atlas'), (@id, 'Beetle'), 
(@id, 'Bora'), (@id, 'Caddy'), (@id, 'CC'), (@id, 'Corrado'), 
(@id, 'Crafter'), (@id, 'e-Golf'), (@id, 'Eos'), (@id, 'Fox'), 
(@id, 'Golf'), (@id, 'Golf GTI'), (@id, 'Golf Plus'), (@id, 'Golf R'), 
(@id, 'Golf Sportsvan'), (@id, 'ID.3'), (@id, 'ID.4'), (@id, 'ID.5'), 
(@id, 'ID.6'), (@id, 'ID.7'), (@id, 'ID.Buzz'), (@id, 'Jetta'), 
(@id, 'LT'), (@id, 'Lupo'), (@id, 'Multivan'), (@id, 'New Beetle'), 
(@id, 'Passat'), (@id, 'Passat Alltrack'), (@id, 'Passat CC'), 
(@id, 'Phaeton'), (@id, 'Polo'), (@id, 'Polo Sedan'), (@id, 'Scirocco'), 
(@id, 'Sharan'), (@id, 'T-Cross'), (@id, 'T-Roc'), (@id, 'Taigo'), 
(@id, 'Taos'), (@id, 'Teramont'), (@id, 'Tiguan'), (@id, 'Tiguan Allspace'), 
(@id, 'Touareg'), (@id, 'Touran'), (@id, 'Transporter'), (@id, 'up!'), 
(@id, 'Vento');

-- Volvo
SET @id = (SELECT id FROM car_makes WHERE name = 'Volvo');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '340'), (@id, '360'), (@id, '440'), (@id, '460'), 
(@id, '480'), (@id, '740'), (@id, '760'), (@id, '850'), 
(@id, '940'), (@id, '960'), (@id, 'C30'), (@id, 'C40'), 
(@id, 'C70'), (@id, 'EX30'), (@id, 'EX90'), (@id, 'S40'), 
(@id, 'S60'), (@id, 'S70'), (@id, 'S80'), (@id, 'S90'), 
(@id, 'V40'), (@id, 'V40 Cross Country'), (@id, 'V50'), (@id, 'V60'), 
(@id, 'V60 Cross Country'), (@id, 'V70'), (@id, 'V90'), 
(@id, 'V90 Cross Country'), (@id, 'XC40'), (@id, 'XC60'), 
(@id, 'XC70'), (@id, 'XC90');

-- ZEEKR
SET @id = (SELECT id FROM car_makes WHERE name = 'ZEEKR');
INSERT IGNORE INTO car_models (make_id, name) VALUES 
(@id, '001'), (@id, '007'), (@id, '009'), (@id, 'X');
