<?php

/**
 * DetalStudio - Layout Header
 * Шапка сторінки
 */
?>
<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/assets/img/faviconV2.png">
    <link rel="apple-touch-icon" href="/assets/img/faviconV2.png">
    <meta name="theme-color" content="#1fade4">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?><?= APP_NAME ?></title>
    <?= \App\Csrf::meta() ?>
    <script>
        // Prevent FOUC
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();

        // Global resource monitor
        window.addEventListener('error', function(e) {
            if (e.target.tagName === 'SCRIPT' || e.target.tagName === 'LINK') {
                console.error('[ResourceError]', e.target.tagName, e.target.src || e.target.href);
            }
        }, true);
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" id="flatpickr-theme" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <!-- DataTables Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= CACHE_VERSION ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/assets/js/main.js?v=<?= CACHE_VERSION ?>"></script>
    <script>
        // Force unregister Service Workers to disable offline mode
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                for (let registration of registrations) {
                    registration.unregister();
                }
            });
        }
    </script>
    <!-- CSRF: Auto-send token with all AJAX requests -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken && typeof $ !== 'undefined') {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-Token': csrfToken
                    }
                });
            }
        });
    </script>
    <!-- Core includes handled by config.php -->
    <?php
    // No manual includes needed here
    ?>
</head>

<body class="h-100">
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-text" style="font-weight: 700; font-size: 1.5rem; letter-spacing: 0.04em;">DetalStudio</div>
            </div>
        </div>

        <?php
        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
        ?>
        <div class="nav flex-column flex-nowrap">
            <!-- ОСНОВНЕ -->
            <div class="nav-section">ОСНОВНЕ</div>
            <a href="/" class="nav-link <?= strpos($currentUri, '/index.php') !== false || $currentUri == '/' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Дашборд</span>
            </a>

            <?php if (\App\Models\Setting::get('feature_stalls', '1') == '1'): ?>
                <a href="/calendar/timeline.php" class="nav-link <?= strpos($currentUri, '/calendar/timeline.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-layout-three-columns"></i>
                    <span>Графік боксів</span>
                </a>
            <?php endif; ?>

            <a href="/orders/" class="nav-link <?= strpos($currentUri, '/orders/') !== false ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                <span>Замовлення</span>
            </a>

            <?php if (\App\Models\Setting::get('feature_tasks', '1') == '1'): ?>
                <a href="/tasks/" class="nav-link <?= strpos($currentUri, '/tasks/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-list-task"></i>
                    <span>Задачі</span>
                </a>
            <?php endif; ?>

            <!-- РОБОТА З КЛІЄНТАМИ -->
            <?php if (Auth::isAtLeast('manager')): ?>
                <div class="nav-section mt-2">КЛІЄНТИ ТА ПОСЛУГИ</div>
                <a href="/clients/" class="nav-link <?= strpos($currentUri, '/clients/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>Клієнти</span>
                </a>
                <a href="/services/" class="nav-link <?= strpos($currentUri, '/services/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-scissors"></i>
                    <span>Послуги</span>
                </a>
            <?php endif; ?>

            <!-- РЕСУРСИ -->
            <?php if (\App\Models\Setting::get('feature_warehouse', '1') == '1' && Auth::isAtLeast('manager')): ?>
                <div class="nav-section mt-2">РЕСУРСИ</div>
                <a href="/warehouse/" class="nav-link <?= strpos($currentUri, '/warehouse/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-box-seam"></i>
                    <span>Склад</span>
                </a>
                <?php if (Auth::isAtLeast('manager')): ?>
                    <a href="/purchases/" class="nav-link <?= strpos($currentUri, '/purchases/') !== false ? 'active' : '' ?>">
                        <i class="bi bi-cart-plus"></i>
                        <span>Закупівлі</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <!-- МЕНЕДЖМЕНТ -->
            <?php if (Auth::isAdmin()): ?>
                <div class="nav-section mt-2">МЕНЕДЖМЕНТ</div>
                <a href="/payments/" class="nav-link <?= strpos($currentUri, '/payments/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-cash-stack"></i>
                    <span>Виплати</span>
                </a>
                <a href="/expenses/" class="nav-link <?= strpos($currentUri, '/expenses/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-wallet-fill"></i>
                    <span>Витрати студії</span>
                </a>
                <a href="/reports/salary.php" class="nav-link <?= strpos($currentUri, '/reports/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-graph-up"></i>
                    <span>Звіти & Зарплати</span>
                </a>

                <!-- СИСТЕМА -->
                <div class="nav-section mt-2">СИСТЕМА</div>
                <a href="/users/" class="nav-link <?= strpos($currentUri, '/users/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Користувачі</span>
                </a>
                <a href="/admin/audit_logs.php" class="nav-link <?= strpos($currentUri, '/admin/audit_logs.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i>
                    <span>Журнал аудиту</span>
                </a>
                <a href="/admin/backups.php" class="nav-link <?= strpos($currentUri, '/admin/backups.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-hdd"></i>
                    <span>Бекапи</span>
                </a>
                <a href="/admin/settings.php" class="nav-link <?= strpos($currentUri, '/admin/settings.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-gear"></i>
                    <span>Налаштування</span>
                </a>
            <?php endif; ?>
        </div>

        <div class="mt-auto p-3">
            <a href="/logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Вийти</span>
            </a>
        </div>
    </nav>

    <!-- Sidebar Toggle -->
    <button class="sidebar-toggle" id="sidebarToggle" title="Згорнути/Розгорнути">
        <i class="bi bi-list"></i>
    </button>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <div class="navbar-top theme-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-toggle d-lg-none" id="mobileMenuToggle">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h4 class="mb-0 theme-text"><?= $pageTitle ?? 'Панель керування' ?></h4>
                    <small class="theme-text-muted d-none d-sm-inline"><?= date('d.m.Y H:i') ?></small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 gap-md-3 ms-auto">
                <!-- Telegram Bot Link -->
                <div class="d-none d-lg-flex align-items-center gap-2 me-2">
                    <a href="https://t.me/DetalStudioBot" target="_blank" class="btn btn-sm btn-outline-info py-1 px-2" style="font-size: 12px; text-transform: none;">
                        <i class="bi bi-telegram me-1"></i>Бот для майстрів
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-warning py-1 px-2" style="font-size: 11px; text-transform: none;" data-bs-toggle="modal" data-bs-target="#telegramInstructionsModal">
                        <i class="bi bi-question-circle"></i>
                    </button>
                </div>

                <!-- Global Search -->
                <div class="position-relative d-none d-md-block search-container" style="width: 300px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text theme-search theme-text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="globalSearchInput" class="form-control theme-search theme-text" placeholder="Пошук..." autocomplete="off">
                    </div>
                </div>
                <!-- Mobile Search Toggle -->
                <button class="btn btn-link theme-text d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileSearchCollapse">
                    <i class="bi bi-search"></i>
                </button>

                <!-- Calendar Icon -->
                <a href="/calendar/" class="btn btn-link theme-text me-1" title="Календар">
                    <i class="bi bi-calendar3"></i>
                </a>

                <!-- Theme Toggle -->
                <button class="btn btn-link theme-text me-2" id="themeToggle" title="Змінити тему">
                    <i class="bi bi-sun-fill" id="themeIcon"></i>
                </button>

                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle theme-text" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar me-2 rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <span class="d-none d-sm-inline mx-1"><?= Auth::getUserName() ?: 'Гість' ?></span>
                    </a>
                    <ul class="dropdown-menu theme-dropdown dropdown-menu-end text-small shadow" aria-labelledby="dropdownUser1">
                        <li><a class="dropdown-item theme-dropdown-item theme-text" href="/profile.php">Мій профіль</a></li>
                        <li>
                            <hr class="dropdown-divider theme-border">
                        </li>
                        <li><a class="dropdown-item theme-dropdown-item theme-text" href="/logout.php">Вийти</a></li>
                    </ul>
                </div>
            </div>

            <!-- Mobile Search Collapse -->
            <div class="collapse w-100 d-md-none mt-2" id="mobileSearchCollapse">
                <div class="input-group input-group-sm">
                    <span class="input-group-text theme-search theme-text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="globalSearchInputMobile" class="form-control theme-search theme-text" placeholder="Пошук (Замовлення, Клієнти...)" autocomplete="off">
                </div>
                <div id="globalSearchResultsMobile" class="list-group position-absolute w-100 shadow-lg mt-1" style="z-index: 1050; display: none;"></div>
            </div>

            <div id="globalSearchResults" class="list-group position-absolute shadow-lg mt-1" style="z-index: 1050; display: none; top: 100%; right: 200px; width: 300px;"></div>
        </div>



        <!-- Flash Messages -->
        <?php
        $flash_messages = Flash::get();
        foreach ($flash_messages as $msg):
        ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>