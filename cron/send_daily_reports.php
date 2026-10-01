<?php
/**
 * Daily Morning Reports
 * Run this script via cron at 9:00 AM
 * 0 9 * * * docker exec detalstudio-app php cron/send_daily_reports.php
 */

require_once __DIR__ . '/../config/config.php';

// Check CLI
if (php_sapi_name() !== 'cli') {
    die("CLI only");
}

echo "📢 Starting Daily Reports...\n";

try {
    // 1. Get all active users with Telegram linked
    $stmt = db()->query("SELECT * FROM users WHERE is_active = 1 AND telegram_chat_id IS NOT NULL");
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        echo "Processing user: {$user['full_name']} (ID: {$user['id']})...\n";
        
        // Mock Session for Model usage
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        
        $messageParts = [];
        
        // --- 2. Tasks for Today ---
        $today = date('Y-m-d');
        $tasksToday = Task::getForCalendar($today, $today);
        
        if (!empty($tasksToday)) {
            $msg = "📅 <b>Задачі на сьогодні (" . count($tasksToday) . "):</b>\n";
            foreach ($tasksToday as $t) {
                // Determine icon based on status
                $icon = '▫️';
                if ($t['status'] === 'completed') $icon = '✅';
                elseif ($t['status'] === 'in_progress') $icon = '🔥';
                
                $msg .= "$icon [#" . $t['id'] . "] " . htmlspecialchars($t['title']);
                if ($t['priority'] === 'high') $msg .= " ❗";
                $msg .= "\n";
            }
            $messageParts[] = $msg;
        }
        
        // --- 3. Overdue Tasks ---
        $tasksOverdue = Task::getOverdue();
        // Filter out tasks already shown in "Today" (unlikely but possible if logic changes)
        // Actually getOverdue logic is deadline < NOW(). 
        // getForCalendar(today) is deadline BETWEEN today 00:00 AND today 23:59.
        // So they are mutually exclusive mostly, unless deadline matches exactly now() which is rare for range.
        
        if (!empty($tasksOverdue)) {
            $msg = "⚠️ <b>Прострочені задачі (" . count($tasksOverdue) . "):</b>\n";
            foreach ($tasksOverdue as $t) {
                $deadline = date('d.m', strtotime($t['deadline']));
                $msg .= "🔺 [#" . $t['id'] . "] " . htmlspecialchars($t['title']) . " ($deadline)\n";
            }
            $messageParts[] = $msg;
        }
        
        // --- 4. Manager Stats (New Orders) ---
        if (in_array($user['role'], ['manager', 'director'])) {
            // Count new orders since yesterday? Or just current 'new' status open orders?
            // "Open New Orders" is more useful.
            $stmtOrders = db()->query("SELECT COUNT(*) FROM orders WHERE status = 'new' AND is_deleted = 0");
            $newOrdersCount = $stmtOrders->fetchColumn();
            
            if ($newOrdersCount > 0) {
                 $messageParts[] = "🆕 <b>Нових замовлень в обробці:</b> $newOrdersCount";
            }
            
            // Maybe total revenue for Director?
            if ($user['role'] === 'director') {
                // Revenue this month
                $startMonth = date('Y-m-01');
                $stmtRev = db()->prepare("SELECT SUM(total_amount - discount_amount) FROM orders WHERE status = 'closed' AND is_deleted = 0 AND created_at >= ?");
                $stmtRev->execute([$startMonth]);
                $revenue = $stmtRev->fetchColumn() ?: 0;
                $formattedRev = number_format($revenue, 0, '.', ' ');
                
                $messageParts[] = "💰 <b>Дохід за цей місяць:</b> $formattedRev грн";
            }
        }
        
        // --- 5. Send Message ---
        if (!empty($messageParts)) {
            $finalMessage = "👋 <b>Добрий ранок, {$user['full_name']}!</b>\n\n";
            $finalMessage .= implode("\n", $messageParts);
            $finalMessage .= "\n\n<i>Гарного продуктивного дня!</i> 🚀";
            
            $res = Telegram::sendMessage($user['telegram_chat_id'], $finalMessage);
            
            if ($res) echo "✅ Report sent.\n";
            else echo "❌ Failed to queue report.\n";
        } else {
            echo "ℹ️ Nothing to report.\n";
        }
    }
    
    echo "🏁 Daily Reports Sent.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
