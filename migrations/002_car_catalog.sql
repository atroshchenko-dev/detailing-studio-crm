-- Create car_makes table
CREATE TABLE IF NOT EXISTS car_makes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create car_models table
CREATE TABLE IF NOT EXISTS car_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    make_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    FOREIGN KEY (make_id) REFERENCES car_makes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data (Popular Brands and Models)
INSERT INTO car_makes (name) VALUES 
('Acura'), ('Audi'), ('BMW'), ('Chevrolet'), ('Citroen'), 
('Dacia'), ('Daewoo'), ('Dodge'), ('Fiat'), ('Ford'), 
('Honda'), ('Hyundai'), ('Infiniti'), ('Jeep'), ('Kia'), 
('Land Rover'), ('Lexus'), ('Mazda'), ('Mercedes-Benz'), ('Mini'), 
('Mitsubishi'), ('Nissan'), ('Opel'), ('Peugeot'), ('Porsche'), 
('Renault'), ('Seat'), ('Skoda'), ('Subaru'), ('Suzuki'), 
('Tesla'), ('Toyota'), ('Volkswagen'), ('Volvo');

-- Audi
INSERT INTO car_models (make_id, name) SELECT id, 'A3' FROM car_makes WHERE name = 'Audi';
INSERT INTO car_models (make_id, name) SELECT id, 'A4' FROM car_makes WHERE name = 'Audi';
INSERT INTO car_models (make_id, name) SELECT id, 'A5' FROM car_makes WHERE name = 'Audi';
INSERT INTO car_models (make_id, name) SELECT id, 'A6' FROM car_makes WHERE name = 'Audi';
INSERT INTO car_models (make_id, name) SELECT id, 'Q3' FROM car_makes WHERE name = 'Audi';
INSERT INTO car_models (make_id, name) SELECT id, 'Q5' FROM car_makes WHERE name = 'Audi';
INSERT INTO car_models (make_id, name) SELECT id, 'Q7' FROM car_makes WHERE name = 'Audi';
INSERT INTO car_models (make_id, name) SELECT id, 'Q8' FROM car_makes WHERE name = 'Audi';

-- BMW
INSERT INTO car_models (make_id, name) SELECT id, '1 Series' FROM car_makes WHERE name = 'BMW';
INSERT INTO car_models (make_id, name) SELECT id, '3 Series' FROM car_makes WHERE name = 'BMW';
INSERT INTO car_models (make_id, name) SELECT id, '5 Series' FROM car_makes WHERE name = 'BMW';
INSERT INTO car_models (make_id, name) SELECT id, '7 Series' FROM car_makes WHERE name = 'BMW';
INSERT INTO car_models (make_id, name) SELECT id, 'X1' FROM car_makes WHERE name = 'BMW';
INSERT INTO car_models (make_id, name) SELECT id, 'X3' FROM car_makes WHERE name = 'BMW';
INSERT INTO car_models (make_id, name) SELECT id, 'X5' FROM car_makes WHERE name = 'BMW';
INSERT INTO car_models (make_id, name) SELECT id, 'X6' FROM car_makes WHERE name = 'BMW';
INSERT INTO car_models (make_id, name) SELECT id, 'X7' FROM car_makes WHERE name = 'BMW';

-- Ford
INSERT INTO car_models (make_id, name) SELECT id, 'Fiesta' FROM car_makes WHERE name = 'Ford';
INSERT INTO car_models (make_id, name) SELECT id, 'Focus' FROM car_makes WHERE name = 'Ford';
INSERT INTO car_models (make_id, name) SELECT id, 'Fusion' FROM car_makes WHERE name = 'Ford';
INSERT INTO car_models (make_id, name) SELECT id, 'Mondeo' FROM car_makes WHERE name = 'Ford';
INSERT INTO car_models (make_id, name) SELECT id, 'Kuga' FROM car_makes WHERE name = 'Ford';
INSERT INTO car_models (make_id, name) SELECT id, 'Escape' FROM car_makes WHERE name = 'Ford';
INSERT INTO car_models (make_id, name) SELECT id, 'Mustang' FROM car_makes WHERE name = 'Ford';

-- Honda
INSERT INTO car_models (make_id, name) SELECT id, 'Civic' FROM car_makes WHERE name = 'Honda';
INSERT INTO car_models (make_id, name) SELECT id, 'Accord' FROM car_makes WHERE name = 'Honda';
INSERT INTO car_models (make_id, name) SELECT id, 'CR-V' FROM car_makes WHERE name = 'Honda';
INSERT INTO car_models (make_id, name) SELECT id, 'HR-V' FROM car_makes WHERE name = 'Honda';

-- Hyundai
INSERT INTO car_models (make_id, name) SELECT id, 'Accent' FROM car_makes WHERE name = 'Hyundai';
INSERT INTO car_models (make_id, name) SELECT id, 'Elantra' FROM car_makes WHERE name = 'Hyundai';
INSERT INTO car_models (make_id, name) SELECT id, 'i30' FROM car_makes WHERE name = 'Hyundai';
INSERT INTO car_models (make_id, name) SELECT id, 'Sonata' FROM car_makes WHERE name = 'Hyundai';
INSERT INTO car_models (make_id, name) SELECT id, 'Tucson' FROM car_makes WHERE name = 'Hyundai';
INSERT INTO car_models (make_id, name) SELECT id, 'Santa Fe' FROM car_makes WHERE name = 'Hyundai';

-- Kia
INSERT INTO car_models (make_id, name) SELECT id, 'Rio' FROM car_makes WHERE name = 'Kia';
INSERT INTO car_models (make_id, name) SELECT id, 'Ceed' FROM car_makes WHERE name = 'Kia';
INSERT INTO car_models (make_id, name) SELECT id, 'Cerato' FROM car_makes WHERE name = 'Kia';
INSERT INTO car_models (make_id, name) SELECT id, 'Optima' FROM car_makes WHERE name = 'Kia';
INSERT INTO car_models (make_id, name) SELECT id, 'Sportage' FROM car_makes WHERE name = 'Kia';
INSERT INTO car_models (make_id, name) SELECT id, 'Sorento' FROM car_makes WHERE name = 'Kia';

-- Mazda
INSERT INTO car_models (make_id, name) SELECT id, '3' FROM car_makes WHERE name = 'Mazda';
INSERT INTO car_models (make_id, name) SELECT id, '6' FROM car_makes WHERE name = 'Mazda';
INSERT INTO car_models (make_id, name) SELECT id, 'CX-3' FROM car_makes WHERE name = 'Mazda';
INSERT INTO car_models (make_id, name) SELECT id, 'CX-5' FROM car_makes WHERE name = 'Mazda';
INSERT INTO car_models (make_id, name) SELECT id, 'CX-9' FROM car_makes WHERE name = 'Mazda';

-- Mercedes-Benz
INSERT INTO car_models (make_id, name) SELECT id, 'A-Class' FROM car_makes WHERE name = 'Mercedes-Benz';
INSERT INTO car_models (make_id, name) SELECT id, 'C-Class' FROM car_makes WHERE name = 'Mercedes-Benz';
INSERT INTO car_models (make_id, name) SELECT id, 'E-Class' FROM car_makes WHERE name = 'Mercedes-Benz';
INSERT INTO car_models (make_id, name) SELECT id, 'S-Class' FROM car_makes WHERE name = 'Mercedes-Benz';
INSERT INTO car_models (make_id, name) SELECT id, 'GLA' FROM car_makes WHERE name = 'Mercedes-Benz';
INSERT INTO car_models (make_id, name) SELECT id, 'GLC' FROM car_makes WHERE name = 'Mercedes-Benz';
INSERT INTO car_models (make_id, name) SELECT id, 'GLE' FROM car_makes WHERE name = 'Mercedes-Benz';
INSERT INTO car_models (make_id, name) SELECT id, 'G-Class' FROM car_makes WHERE name = 'Mercedes-Benz';

-- Mitsubishi
INSERT INTO car_models (make_id, name) SELECT id, 'Lancer' FROM car_makes WHERE name = 'Mitsubishi';
INSERT INTO car_models (make_id, name) SELECT id, 'Outlander' FROM car_makes WHERE name = 'Mitsubishi';
INSERT INTO car_models (make_id, name) SELECT id, 'Pajero' FROM car_makes WHERE name = 'Mitsubishi';
INSERT INTO car_models (make_id, name) SELECT id, 'ASX' FROM car_makes WHERE name = 'Mitsubishi';

-- Nissan
INSERT INTO car_models (make_id, name) SELECT id, 'Juke' FROM car_makes WHERE name = 'Nissan';
INSERT INTO car_models (make_id, name) SELECT id, 'Qashqai' FROM car_makes WHERE name = 'Nissan';
INSERT INTO car_models (make_id, name) SELECT id, 'X-Trail' FROM car_makes WHERE name = 'Nissan';
INSERT INTO car_models (make_id, name) SELECT id, 'Rogue' FROM car_makes WHERE name = 'Nissan';
INSERT INTO car_models (make_id, name) SELECT id, 'Leaf' FROM car_makes WHERE name = 'Nissan';

-- Renault
INSERT INTO car_models (make_id, name) SELECT id, 'Clio' FROM car_makes WHERE name = 'Renault';
INSERT INTO car_models (make_id, name) SELECT id, 'Megane' FROM car_makes WHERE name = 'Renault';
INSERT INTO car_models (make_id, name) SELECT id, 'Logan' FROM car_makes WHERE name = 'Renault';
INSERT INTO car_models (make_id, name) SELECT id, 'Duster' FROM car_makes WHERE name = 'Renault';

-- Skoda
INSERT INTO car_models (make_id, name) SELECT id, 'Fabia' FROM car_makes WHERE name = 'Skoda';
INSERT INTO car_models (make_id, name) SELECT id, 'Octavia' FROM car_makes WHERE name = 'Skoda';
INSERT INTO car_models (make_id, name) SELECT id, 'Superb' FROM car_makes WHERE name = 'Skoda';
INSERT INTO car_models (make_id, name) SELECT id, 'Kodiaq' FROM car_makes WHERE name = 'Skoda';
INSERT INTO car_models (make_id, name) SELECT id, 'Karoq' FROM car_makes WHERE name = 'Skoda';

-- Toyota
INSERT INTO car_models (make_id, name) SELECT id, 'Corolla' FROM car_makes WHERE name = 'Toyota';
INSERT INTO car_models (make_id, name) SELECT id, 'Camry' FROM car_makes WHERE name = 'Toyota';
INSERT INTO car_models (make_id, name) SELECT id, 'RAV4' FROM car_makes WHERE name = 'Toyota';
INSERT INTO car_models (make_id, name) SELECT id, 'Land Cruiser' FROM car_makes WHERE name = 'Toyota';
INSERT INTO car_models (make_id, name) SELECT id, 'Land Cruiser Prado' FROM car_makes WHERE name = 'Toyota';
INSERT INTO car_models (make_id, name) SELECT id, 'Hilux' FROM car_makes WHERE name = 'Toyota';
INSERT INTO car_models (make_id, name) SELECT id, 'Highlander' FROM car_makes WHERE name = 'Toyota';

-- Volkswagen
INSERT INTO car_models (make_id, name) SELECT id, 'Golf' FROM car_makes WHERE name = 'Volkswagen';
INSERT INTO car_models (make_id, name) SELECT id, 'Passat' FROM car_makes WHERE name = 'Volkswagen';
INSERT INTO car_models (make_id, name) SELECT id, 'Jetta' FROM car_makes WHERE name = 'Volkswagen';
INSERT INTO car_models (make_id, name) SELECT id, 'Tiguan' FROM car_makes WHERE name = 'Volkswagen';
INSERT INTO car_models (make_id, name) SELECT id, 'Touareg' FROM car_makes WHERE name = 'Volkswagen';
INSERT INTO car_models (make_id, name) SELECT id, 'Transporter' FROM car_makes WHERE name = 'Volkswagen';

-- Volvo
INSERT INTO car_models (make_id, name) SELECT id, 'S60' FROM car_makes WHERE name = 'Volvo';
INSERT INTO car_models (make_id, name) SELECT id, 'S90' FROM car_makes WHERE name = 'Volvo';
INSERT INTO car_models (make_id, name) SELECT id, 'XC60' FROM car_makes WHERE name = 'Volvo';
INSERT INTO car_models (make_id, name) SELECT id, 'XC90' FROM car_makes WHERE name = 'Volvo';
