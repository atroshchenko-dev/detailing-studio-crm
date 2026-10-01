-- Database Performance Optimization - Indexes
-- DetalStudio

-- Orders table optimization
CREATE INDEX idx_orders_status_created ON orders(status, created_at);
CREATE INDEX idx_orders_client_id ON orders(client_id);
CREATE INDEX idx_orders_car_id ON orders(car_id);
CREATE INDEX idx_orders_manager_id ON orders(manager_id);
CREATE INDEX idx_orders_access_token ON orders(access_token);

-- Tasks table optimization
CREATE INDEX idx_tasks_order_id ON tasks(order_id);
CREATE INDEX idx_tasks_executor_id ON tasks(executor_id);
CREATE INDEX idx_tasks_status_deadline ON tasks(status, deadline);
CREATE INDEX idx_tasks_priority ON tasks(priority);

-- Clients table optimization
CREATE INDEX idx_clients_phone ON clients(phone);
CREATE INDEX idx_clients_email ON clients(email);

-- Cars table optimization
CREATE INDEX idx_cars_client_id ON cars(client_id);
CREATE INDEX idx_cars_license_plate ON cars(license_plate);

-- Order Items table optimization
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_service_id ON order_items(service_id);

-- Services table optimization
CREATE INDEX idx_services_category ON services(category);
CREATE INDEX idx_services_is_active ON services(is_active);

-- Warehouse optimization
CREATE INDEX idx_warehouse_items_category ON warehouse_items(category);
CREATE INDEX idx_warehouse_items_is_active ON warehouse_items(is_active);
CREATE INDEX idx_warehouse_transactions_item_id ON warehouse_transactions(item_id);
CREATE INDEX idx_warehouse_transactions_order_id ON warehouse_transactions(order_id);
CREATE INDEX idx_warehouse_transactions_created ON warehouse_transactions(created_at);

-- Users optimization
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_is_active ON users(is_active);

-- Full-text search for fast lookup
ALTER TABLE clients ADD FULLTEXT INDEX ft_clients_name (name);
ALTER TABLE orders ADD FULLTEXT INDEX ft_orders_number (order_number);
