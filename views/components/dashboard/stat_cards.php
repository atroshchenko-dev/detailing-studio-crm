        <!-- Stats Cards -->
        <div class="row mb-4 animate-fade-in-up delay-100">
            <?php
            // Orders Card
            extract([
                'type' => 'orders',
                'label' => 'Замовлень',
                'value' => $stats['orders']['total_orders'] ?? 0,
                'icon' => 'receipt',
                'subtext' => '<i class="bi bi-check-circle"></i> ' . number_format($stats['orders']['completed_orders'] ?? 0) . ' виконано',
                'subtextClass' => 'text-success',
                'isCurrency' => false,
                'trend' => $trends['orders'] ?? 0
            ]);
            include __DIR__ . '/../stat_card.php';

            // Revenue Card
            extract([
                'type' => 'revenue',
                'label' => 'Дохід',
                'value' => $stats['orders']['total_revenue'] ?? 0,
                'icon' => 'currency-exchange',
                'isCurrency' => true,
                'subtext' => '<i class="bi bi-wallet2"></i> ' . format_currency($stats['orders']['total_paid'] ?? 0) . ' оплачено',
                'subtextClass' => 'text-info',
                'trend' => $trends['revenue'] ?? 0
            ]);
            include __DIR__ . '/../stat_card.php';

            // Tasks Card
            extract([
                'type' => 'tasks',
                'label' => 'Активні задачі',
                'value' => $stats['tasks']['active_tasks'] ?? 0,
                'icon' => 'list-check',
                'subtext' => '<i class="bi bi-exclamation-triangle"></i> ' . number_format($stats['tasks']['overdue_tasks'] ?? 0) . ' прострочено',
                'subtextClass' => 'text-warning',
                'isCurrency' => false,
                'trend' => $trends['tasks'] ?? 0
            ]);
            include __DIR__ . '/../stat_card.php';

            // Warehouse Card (No trend available in current stats)
            if (\App\Models\Setting::get('feature_warehouse', '1') == '1') {
                extract([
                    'type' => 'warehouse',
                    'label' => 'Склад',
                    'value' => $stats['warehouse']['total_value'] ?? 0,
                    'icon' => 'boxes',
                    'isCurrency' => true,
                    'subtext' => '<i class="bi bi-box-seam"></i> ' . number_format($stats['warehouse']['low_stock_items'] ?? 0) . ' мало залишків',
                    'subtextClass' => 'text-danger'
                ]);
                include __DIR__ . '/../stat_card.php';
            }
            ?>
        </div>
