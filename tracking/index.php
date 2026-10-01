<?php

/**
 * DetalStudio - Order Tracking
 * Відстеження замовлення
 * Unified Controller: Handles both Token access and Form Login
 */

require_once __DIR__ . '/../config/config.php';

$order = null;
$error = null;
$showForm = true;

// 1. Check for Token (Direct Link)
$token = $_GET['token'] ?? '';
if (!empty($token)) {
    $stmt = db()->prepare("
        SELECT o.*, 
               c.name as client_name, c.phone as client_phone,
               cr.make, cr.model, cr.year, cr.license_plate,
               u.full_name as manager_name, u.phone as manager_phone, u.telegram_username
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        LEFT JOIN cars cr ON o.car_id = cr.id
        LEFT JOIN users u ON o.manager_id = u.id
        WHERE o.access_token = ? AND o.is_deleted = 0
    ");
    $stmt->execute([$token]);
    $order = $stmt->fetch();

    if ($order) {
        $showForm = false;
    } else {
        $error = 'Недійсне посилання для відстеження';
    }
}

// 2. Check for Form Submission
if ($showForm && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');

    if (!$phone) {
        $error = 'Будь ласка, введіть номер телефону';
    } else {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($cleanPhone) < 9) {
            $error = 'Будь ласка, введіть коректний номер телефону';
        } else {
            $last9 = substr($cleanPhone, -9);
            
            // Try searching by phone (most recent non-deleted order for this phone)
            $stmt = db()->prepare("
                SELECT o.*, 
                       c.name as client_name, c.phone as client_phone,
                       cr.make, cr.model, cr.year, cr.license_plate,
                       u.full_name as manager_name, u.phone as manager_phone, u.telegram_username
                FROM orders o
                JOIN clients c ON o.client_id = c.id
                LEFT JOIN cars cr ON o.car_id = cr.id
                LEFT JOIN users u ON o.manager_id = u.id
                WHERE o.is_deleted = 0 AND REPLACE(REPLACE(REPLACE(REPLACE(c.phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?
                ORDER BY o.created_at DESC
                LIMIT 1
            ");
            $stmt->execute(['%' . $last9 . '%']);
            $foundOrder = $stmt->fetch();

            if ($foundOrder) {
                $order = $foundOrder;
                $showForm = false;
            } else {
                $error = 'Замовлення не знайдено для цього номеру телефону';
            }
        }
    }
}

// 3. Prepare Data for Rich UI if Order Found
if ($order) {
    // Get tasks
    $tasks = \App\Models\Task::getList(['order_id' => $order['id']]);

    // Get order items (services)
    $stmtItems = db()->prepare("
        SELECT oi.*, s.name as service_name, s.description 
        FROM order_items oi
        LEFT JOIN services s ON oi.service_id = s.id
        WHERE oi.order_id = ?
    ");
    $stmtItems->execute([$order['id']]);
    $items = $stmtItems->fetchAll();

    // Calculate progress
    $totalTasks = count($tasks);
    $completedTasks = 0;
    foreach ($tasks as $task) {
        if ($task['status'] === 'completed') $completedTasks++;
    }
    $progressPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

    // Status Logic
    $statusNames = [
        'new' => 'Оформлення',
        'in_progress' => 'В роботі',
        'ready' => 'Готово',
        'closed' => 'Закрито'
    ];
    $statusColor = [
        'new' => 'primary',
        'in_progress' => 'warning',
        'ready' => 'success',
        'closed' => 'secondary'
    ];

    // Override progress for finalized statuses
    if ($order['status'] === 'closed' || $order['status'] === 'ready') {
        $progressPercent = 100;
    } else if ($order['status'] === 'new') {
        $progressPercent = max($progressPercent, 5); // Minimum 5% for new
    }

    $currentStatusName = $statusNames[$order['status']] ?? 'Невідомо';
    $currentStatusColor = $statusColor[$order['status']] ?? 'secondary';

    // Manager Telegram Logic
    $managerPhone = $order['manager_phone'] ?? '';
    $managerTg = $order['telegram_username'] ?? '';

    $tgLink = 'tg://resolve?domain=detalstudio_bot'; // Fallback
    if (!empty($managerTg)) {
        $tgLink = "https://t.me/" . ltrim($managerTg, '@');
    } elseif (!empty($managerPhone)) {
        $cleanManagerPhone = preg_replace('/[^0-9]/', '', $managerPhone);
        if (strlen($cleanManagerPhone) >= 10) {
            $tgLink = "https://t.me/+" . $cleanManagerPhone;
        }
    }

    $contactPhone = $managerPhone ?: '+380500000000';
}

?>
<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Відстеження замовлення | DetalStudio</title>

    <!-- PWA / Icons -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1fade4">

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">

    <style>
        body {
            background-color: #000000;
            background-image: radial-gradient(circle at 50% 50%, #111 0%, #000 100%);
            color: #e0e0e0;
            font-family: 'Rajdhani', sans-serif;
            min-height: 100vh;
        }

        /* Form Styles (Login) */
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .tracking-card {
            background: transparent;
            border: none;
            box-shadow: none;
            max-width: 450px;
            width: 100%;
            text-align: center;
        }

        .form-control {
            background-color: #111;
            border-color: #222;
            color: #fff;
            padding: 12px;
            text-align: center;
        }

        .form-control:focus {
            background-color: #111;
            border-color: #1fade4;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(31, 173, 228, 0.25);
        }

        .custom-btn {
            color: #eee;
            border: 1px solid #444;
            background: rgba(255, 255, 255, 0.05);
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-radius: 20px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            padding: 10px 30px;
            cursor: pointer;
        }

        .custom-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #1fade4;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(31, 190, 228, 0.2);
        }

        /* Rich UI Styles */
        .tracking-header {
            background: linear-gradient(135deg, #1fade4 0%, #005cbf 100%);
            padding: 40px 20px 60px;
            text-align: center;
            border-radius: 0 0 30px 30px;
            box-shadow: 0 10px 30px rgba(31, 173, 228, 0.2);
            margin-bottom: -40px;
            position: relative;
            z-index: 1;
        }

        .car-badge {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            padding: 8px 20px;
            border-radius: 50px;
            display: inline-block;
            margin-top: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }

        .status-card {
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.5s ease-out;
        }

        .progress-ring {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: conic-gradient(#1fade4 var(--progress), #333 0deg);
            box-shadow: 0 0 20px rgba(31, 173, 228, 0.2);
        }

        .progress-ring::before {
            content: "";
            position: absolute;
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: #1e1e1e;
        }

        .progress-text {
            position: relative;
            font-size: 32px;
            font-weight: 700;
            color: #1fade4;
            z-index: 2;
        }

        .section-title {
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .section-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #333;
            margin-left: 15px;
        }

        .timeline-item {
            border-left: 2px solid #333;
            padding-left: 25px;
            padding-bottom: 30px;
            position: relative;
        }

        .timeline-item::before {
            content: "";
            width: 14px;
            height: 14px;
            background: #333;
            border-radius: 50%;
            position: absolute;
            left: -8px;
            top: 4px;
            border: 3px solid #121212;
        }

        .timeline-item.completed {
            border-left-color: #1fade4;
        }

        .timeline-item.completed::before {
            background: #1fade4;
            box-shadow: 0 0 10px rgba(31, 173, 228, 0.5);
        }

        .timeline-item:last-child {
            border-left: none;
            padding-bottom: 0;
        }

        .contact-btn {
            background: #1fade4;
            border: none;
            color: white;
            padding: 12px 30px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }

        .contact-btn:hover {
            background: #1797c6;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(31, 173, 228, 0.3);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>

    <?php if ($showForm): ?>
        <!-- LOGIN FORM -->
        <div class="login-container">
            <div class="tracking-card">
                <div class="text-center mb-4">
                    <div class="logo-text" style="font-weight: 700; font-size: 1.5rem; letter-spacing: 0.04em;">DetalStudio</div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <input type="tel" name="phone" class="form-control" placeholder="Номер телефону (0XX XXX XX XX)" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required style="max-width: 300px; margin: 0 auto;">
                    </div>

                    <div style="margin-top: 40px;">
                        <button type="submit" class="custom-btn">
                            Перевірити статус
                        </button>
                    </div>
                </form>

                <div class="text-center mt-5">
                    <a href="/" class="text-decoration-none text-muted small hover-accent" style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase;">
                        <i class="bi bi-arrow-left me-1"></i> На головну
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- RICH RESULT UI -->
        <div class="login-container">
            <div class="tracking-card" style="max-width: 650px;">
                <!-- Video Logo -->
                <div class="text-center mb-4">
                    <div class="logo-text" style="font-weight: 700; font-size: 1.5rem; letter-spacing: 0.04em;">DetalStudio</div>
                </div>

                <!-- Order Number & Car Info -->
                <div class="text-center mb-4">
                    <h5 class="text-muted mb-2" style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">
                        Замовлення #<?= htmlspecialchars($order['order_number']) ?>
                    </h5>
                    <div class="car-badge" style="display: inline-block; background: rgba(31, 173, 228, 0.1); padding: 8px 20px; border-radius: 50px; border: 1px solid rgba(31, 173, 228, 0.3);">
                        <i class="bi bi-car-front-fill me-2" style="color: #1fade4;"></i>
                        <span style="color: #fff; font-weight: 600;"><?= htmlspecialchars(($order['make'] ?? '') . ' ' . ($order['model'] ?? '')) ?></span>
                        <span class="mx-2" style="color: #666;">|</span>
                        <span style="color: #aaa;"><?= htmlspecialchars($order['license_plate'] ?? '') ?></span>
                    </div>
                </div>

                <!-- Status Card -->
                <div class="status-card" style="background: transparent; border: none; box-shadow: none; padding: 30px 0;">
                    <div class="progress-ring" style="--progress: <?= $progressPercent ?>%">
                        <div class="progress-text"><?= $progressPercent ?>%</div>
                    </div>
                    <h2 class="text-<?= $currentStatusColor ?> text-uppercase fw-bold mb-2" style="font-size: 1.5rem;">
                        <?= $currentStatusName ?>
                    </h2>
                    <p class="text-muted mb-0" style="font-size: 0.85rem;">Поточний етап виконання</p>

                    <?php if ($order['deadline']): ?>
                        <div class="mt-4 pt-3" style="border-top: 1px solid rgba(255,255,255,0.1);">
                            <small class="text-muted d-block text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 1px;">Орієнтовна готовність</small>
                            <span class="fs-4 fw-bold" style="color: #1fade4;">
                                <?= date('d.m.Y', strtotime($order['deadline'])) ?>
                                <span class="text-muted fs-6 ms-2"><?= date('H:i', strtotime($order['deadline'])) ?></span>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Services List -->
                <?php if (!empty($items)): ?>
                    <div class="mb-5 animation-delay-1" style="animation: fadeInUp 0.5s ease-out 0.2s backwards;">
                        <div class="section-title" style="text-align: left; font-size: 0.85rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <i class="bi bi-tools me-2" style="color: #1fade4;"></i>Ваше замовлення
                        </div>
                        <ul class="list-group list-group-flush bg-transparent">
                            <?php foreach ($items as $item): ?>
                                <li class="list-group-item bg-transparent text-white border-bottom px-0 py-3 d-flex justify-content-between align-items-center" style="border-bottom-color: rgba(255,255,255,0.05) !important;">
                                    <div>
                                        <div class="fw-medium" style="color: #e0e0e0;"><?= htmlspecialchars($item['service_name'] ?? 'Послуга') ?></div>
                                        <?php if (!empty($item['notes'])): ?>
                                            <small class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($item['notes']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($item['quantity'] > 1): ?>
                                        <span class="badge rounded-pill" style="background: rgba(31, 173, 228, 0.2); color: #1fade4; padding: 0.4em 0.8em;">x<?= $item['quantity'] ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Timeline -->
                <div class="mb-5 animation-delay-2" style="animation: fadeInUp 0.5s ease-out 0.4s backwards;">
                    <div class="section-title" style="text-align: left; font-size: 0.85rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <i class="bi bi-clock-history me-2" style="color: #1fade4;"></i>Етапи роботи
                    </div>
                    <div class="ps-2">
                        <?php
                        // Timeline Logic
                        $steps = [
                            'new' => ['title' => 'Прийом авто', 'desc' => 'Оформлення заявки та огляд'],
                            'in_progress' => ['title' => 'В роботі', 'desc' => 'Майстри працюють над авто'],
                            'ready' => ['title' => 'Готово', 'desc' => 'Авто чекає на вас'],
                            'closed' => ['title' => 'Видано', 'desc' => 'Замовлення завершено']
                        ];

                        // Determine active index based on status
                        $statusOrder = ['new', 'in_progress', 'ready', 'closed'];
                        $currentIndex = array_search($order['status'], $statusOrder);
                        if ($currentIndex === false) $currentIndex = 0;

                        $i = 0;
                        foreach ($steps as $stepKey => $stepData):
                            $isCompleted = false;
                            $stepIndex = array_search($stepKey, $statusOrder);

                            if ($order['status'] === 'cancelled') {
                                $isCompleted = ($stepKey === 'new');
                            } else {
                                $isCompleted = ($stepIndex <= $currentIndex);
                            }
                        ?>
                            <div class="timeline-item <?= $isCompleted ? 'completed' : '' ?>">
                                <h6 class="mb-1 fw-bold text-<?= $isCompleted ? 'white' : 'muted' ?>" style="font-size: 0.95rem;"><?= $stepData['title'] ?></h6>
                                <small class="<?= $isCompleted ? 'text-muted' : 'text-secondary' ?>" style="font-size: 0.8rem;"><?= $stepData['desc'] ?></small>

                                <?php if ($stepKey === 'new' && $isCompleted): ?>
                                    <div class="text-muted small mt-1" style="font-size: 0.75rem;"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                                <?php endif; ?>

                                <?php if ($stepKey === 'in_progress' && $order['status'] === 'in_progress'): ?>
                                    <div class="progress mt-2" style="height: 4px; background-color: rgba(31, 173, 228, 0.1);">
                                        <div class="progress-bar" style="width: <?= $progressPercent ?>%; background: linear-gradient(90deg, #1fade4 0%, #1797c6 100%);"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php $i++;
                        endforeach; ?>
                    </div>
                </div>

                <!-- Contact Manager -->
                <div class="text-center mt-5 mb-4 animation-delay-3" style="animation: fadeInUp 0.5s ease-out 0.6s backwards;">
                    <p class="text-muted mb-4" style="font-size: 0.85rem;">Виникли питання? Менеджер на зв'язку:</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contactPhone) ?>" class="custom-btn">
                            <i class="bi bi-telephone-fill me-2"></i>Зателефонувати
                        </a>
                        <a href="<?= htmlspecialchars($tgLink) ?>" class="custom-btn" target="_blank">
                            <i class="bi bi-telegram me-2"></i>Telegram
                        </a>
                    </div>
                    <?php if (!empty($order['manager_name'])): ?>
                        <div class="mt-4 text-muted" style="font-size: 0.85rem;">
                            Ваш менеджер: <span style="color: #1fade4; font-weight: 600;"><?= htmlspecialchars($order['manager_name']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="mt-5">
                        <a href="/tracking/" class="text-decoration-none text-muted" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; transition: color 0.3s;" onmouseover="this.style.color='#1fade4'" onmouseout="this.style.color='#6c757d'">
                            <i class="bi bi-arrow-left me-1"></i>Перевірити інше замовлення
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</body>

</html>