<?php

/**
 * Audit Logs View
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Audit;
use App\Flash;
use App\Logger;

Auth::requireAuth();
Auth::requireRole('director');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clear_audit') {
        if (Audit::clearAllLogs()) {
            Flash::success('Журнал аудиту успішно очищено');
            Audit::log('audit_cleared', 'audit_log', 0, null, 'Cleared all audit logs');
        } else {
            Flash::error('Помилка при очищенні журналу');
        }
        redirect('audit_logs.php');
    }

    if ($action === 'clear_file') {
        $file = $_POST['file'] ?? '';
        $allowedFiles = ['error_log', 'logs/app.log', 'ngrok.log']; // Add other log files if needed

        // Basic security check to prevent traversing directories
        $baseName = basename($file);
        $isInDataDir = strpos($file, 'logs/') === 0;

        if (in_array($file, $allowedFiles) || ($isInDataDir && file_exists(__DIR__ . '/../' . $file))) {
            $filePath = __DIR__ . '/../' . $file;
            if (file_exists($filePath)) {
                file_put_contents($filePath, ''); // Truncate file
                Flash::success("Файл логів {$file} успішно очищено");
                Audit::log('log_cleared', 'system_log', 0, null, "Cleared log file: $file");
            } else {
                Flash::error('Файл не знайдено');
            }
        } else {
            Flash::error('Недопустимий файл');
        }
        redirect('audit_logs.php?tab=system');
    }
}

$pageTitle = 'Журнал аудиту та Логи';
$activeTab = $_GET['tab'] ?? 'journal';

// Fetch Audit Logs
$logs = Audit::getRecentActivity(100);

// Fetch System Logs
$logFiles = [];
$searchPaths = [
    '' => ['error_log', 'ngrok.log'],
    'logs' => ['*.log']
];

foreach ($searchPaths as $dir => $patterns) {
    $baseDir = __DIR__ . '/../' . ($dir ? $dir . '/' : '');
    foreach ($patterns as $pattern) {
        $files = glob($baseDir . $pattern);
        foreach ($files as $file) {
            if (is_file($file)) {
                // Filter out empty files
                if (filesize($file) === 0) {
                    continue;
                }

                $relativePath = ($dir ? $dir . '/' : '') . basename($file);
                $logFiles[] = [
                    'path' => $relativePath,
                    'name' => basename($file),
                    'size_bytes' => filesize($file),
                    'size' => round(filesize($file) / 1024, 2) . ' KB',
                    'modified_ts' => filemtime($file),
                    'modified' => date('d.m.Y H:i:s', filemtime($file)),
                    'content' => ''
                ];
            }
        }
    }
}

// Sort by modified date descending
usort($logFiles, function ($a, $b) {
    return $b['modified_ts'] - $a['modified_ts'];
});

// Handle Viewing Specific Log File
$viewLogContent = '';
$viewLogFile = $_GET['view_log'] ?? '';
if ($viewLogFile) {
    $filePath = __DIR__ . '/../' . $viewLogFile;
    // Security check
    $validDataDir = realpath(__DIR__ . '/../logs');
    $realPath = realpath($filePath);
    $isAllowedRoot = in_array($viewLogFile, ['error_log', 'ngrok.log']);

    if (file_exists($filePath) && ($isAllowedRoot || (strpos($realPath, $validDataDir) === 0))) {
        // Read last 100 lines
        $lines = file($filePath);
        $viewLogContent = implode("", array_slice($lines, -100));
        $activeTab = 'system';
    }
}


include __DIR__ . '/../views/layout/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-shield-check me-2"></i>Журнал аудиту та Логи</h2>
    </div>

    <ul class="nav nav-tabs mb-4" id="logTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'journal' ? 'active' : '' ?>" id="journal-tab" data-bs-toggle="tab" data-bs-target="#journal" type="button" role="tab">
                <i class="bi bi-journal-text me-2"></i>Журнал змін
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'system' ? 'active' : '' ?>" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                <i class="bi bi-terminal me-2"></i>Системні логи
            </button>
        </li>
    </ul>

    <div class="tab-content" id="logTabsContent">
        <!-- Audit Journal Tab -->
        <div class="tab-pane fade <?= $activeTab === 'journal' ? 'show active' : '' ?>" id="journal" role="tabpanel">
            <div class="card bg-dark border-secondary">
                <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Останні дії в системі</h5>
                    <form method="POST" onsubmit="return confirm('Ви впевнені, що хочете очистити ВЕСЬ журнал аудиту? Цю дію неможливо відмінити!');">
                        <input type="hidden" name="action" value="clear_audit">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash me-2"></i>Очистити журнал
                        </button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Час</th>
                                    <th>Користувач</th>
                                    <th>Дія</th>
                                    <th>Об'єкт</th>
                                    <th>ID</th>
                                    <th>Деталі</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="small text-nowrap"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($log['user_name'] ?? 'Система') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold"><?= htmlspecialchars(Audit::getActionDescription($log['action'])) ?></span>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($log['action']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($log['table_name'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($log['record_id'] != 0 ? $log['record_id'] : '-') ?></td>
                                        <td class="small">
                                            <?php
                                            $details = [];
                                            if (!empty($log['old_value'])) {
                                                $details['before'] = json_decode($log['old_value'], true);
                                            }
                                            if (!empty($log['new_value'])) {
                                                $details['after'] = json_decode($log['new_value'], true);
                                            }
                                            ?>

                                            <?php if (!empty($details)): ?>
                                                <div style="max-height: 100px; overflow-y: auto; font-size: 0.75rem;">
                                                    <?php if (isset($details['before'])): ?>
                                                        <div class="text-danger mb-1">
                                                            <strong>Було:</strong>
                                                            <?= htmlspecialchars(json_encode($details['before'], JSON_UNESCAPED_UNICODE)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (isset($details['after'])): ?>
                                                        <div class="text-success">
                                                            <strong>Стало:</strong>
                                                            <?= htmlspecialchars(json_encode($details['after'], JSON_UNESCAPED_UNICODE)) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            Записів не знайдено
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Logs Tab -->
        <div class="tab-pane fade <?= $activeTab === 'system' ? 'show active' : '' ?>" id="system" role="tabpanel">
            <div class="row">
                <div class="col-md-4">
                    <div class="card bg-dark border-secondary mb-3">
                        <div class="card-header border-secondary">
                            <h5 class="mb-0">Файли логів</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($logFiles as $lFile): ?>
                                <a href="?tab=system&view_log=<?= urlencode($lFile['path']) ?>"
                                    class="list-group-item list-group-item-action bg-dark text-white border-secondary d-flex justify-content-between align-items-center <?= ($viewLogFile === $lFile['path']) ? 'active' : '' ?>">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($lFile['name']) ?></div>
                                        <small class="text-muted"><?= $lFile['size'] ?> | <?= $lFile['modified'] ?></small>
                                    </div>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($logFiles)): ?>
                                <div class="list-group-item bg-dark text-white border-secondary text-center text-muted">
                                    Логів не знайдено
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <?php if ($viewLogFile): ?>
                        <div class="card bg-dark border-secondary">
                            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Перегляд: <?= htmlspecialchars($viewLogFile) ?></h5>
                                <form method="POST" onsubmit="return confirm('Ви впевнені, що хочете очистити цей файл?');">
                                    <input type="hidden" name="action" value="clear_file">
                                    <input type="hidden" name="file" value="<?= htmlspecialchars($viewLogFile) ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-trash me-2"></i>Очистити файл
                                    </button>
                                </form>
                            </div>
                            <div class="card-body p-0">
                                <pre class="m-0 p-3 text-white" style="max-height: 600px; overflow-y: auto; font-size: 0.85rem; background-color: #1e1e1e;"><?= htmlspecialchars($viewLogContent ?: "Файл порожній або не вдалося прочитати.") ?></pre>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary text-center">
                            <i class="bi bi-arrow-left me-2"></i>Оберіть файл логів для перегляду
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>