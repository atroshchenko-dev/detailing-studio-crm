<?php
$currentParams = $_GET;
function report_url($path, $params = []) {
    $q = array_merge($_GET, $params);
    return $path . '?' . http_build_query($q);
}
$active = basename($_SERVER['PHP_SELF'], '.php');
?>
<div class="mb-4">
    <ul class="nav nav-pills theme-dropdown p-1 rounded">
        <li class="nav-item">
            <a href="/reports/salary.php" class="nav-link <?= $active == 'salary' ? 'active' : 'theme-text' ?>">
                <i class="bi bi-cash-stack me-2"></i>Зарплати та KPI
            </a>
        </li>
        <li class="nav-item">
            <a href="/reports/profit.php" class="nav-link <?= $active == 'profit' ? 'active' : 'theme-text' ?>">
                <i class="bi bi-graph-up-arrow me-2"></i>Прибуток (P&L)
            </a>
        </li>
        <li class="nav-item">
            <a href="/reports/executors.php" class="nav-link <?= $active == 'executors' ? 'active' : 'theme-text' ?>">
                <i class="bi bi-person-workspace me-2"></i>Виконавці
            </a>
        </li>
    </ul>
</div>
