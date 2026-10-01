<?php

use App\Models\Order;
use App\Models\Task;

/**
 * DetalStudio - Dashboard View
 *
 * @var array $stats
 * @var array $trends
 * @var array $todayStats
 * @var array $financial
 * @var array $monthlyData
 */
?>

<div class="container-fluid py-4">
    <div class="container-fluid py-4">
<?php
        require __DIR__ . '/components/dashboard/today_stats.php';
        require __DIR__ . '/components/dashboard/stat_cards.php';
        require __DIR__ . '/components/dashboard/analytics_charts.php';
        require __DIR__ . '/components/dashboard/recent_tables.php';
        require __DIR__ . '/components/dashboard/top_lists.php';
?>
    </div>
</div>

<!-- Dashboard Scripts -->
<script src="/assets/js/dashboard.js?v=<?= time() ?>"></script>