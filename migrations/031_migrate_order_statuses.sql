-- Міграція статусів замовлень
-- Оновлення існуючих замовлень з старих статусів на нові

-- Оновлюємо 'approved' на 'in_progress' (погоджені замовлення переходять у роботу)
UPDATE orders 
SET status = 'in_progress' 
WHERE status = 'approved';

-- Оновлюємо 'waiting_client' на 'ready' (замовлення, що очікують клієнта, вважаються готовими)
UPDATE orders 
SET status = 'ready' 
WHERE status = 'waiting_client';

-- Оновлюємо 'cancelled' на 'closed' (скасовані замовлення вважаються закритими)
UPDATE orders 
SET status = 'closed' 
WHERE status = 'cancelled';

-- Перевірка результатів
SELECT status, COUNT(*) as count 
FROM orders 
GROUP BY status 
ORDER BY status;
