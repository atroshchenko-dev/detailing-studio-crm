<?php
ob_start();
/**
 * Backup Management Interface
 * View and download database backups
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../cron/backup_database.php';

use App\Auth;

Auth::requireAuth();
Auth::requireRole('director');

$pageTitle = 'Бекапи бази даних';

$backup = new DatabaseBackup();
$backups = $backup->getBackupList();

// Handle manual backup trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    \App\CSRF::verify();
    ob_start(); // Start output buffering to catch any echoed logs
    $result = $backup->createBackup();
    $debugOutput = ob_get_clean(); // Capture and discard it
    
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($result['success']) {
        $msg = "Бекап створено: {$result['filename']} ({$result['size']} MB)";
        if ($isAjax) {
            // Clean any previous output buffers to ensure valid JSON
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }
        \App\Flash::set('success', $msg);
    } else {
        $msg = "Помилка бекапу: {$result['error']}";
        if ($isAjax) {
            // Clean any previous output buffers to ensure valid JSON
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        \App\Flash::set('error', $msg);
    }
    
    header('Location: /admin/backups.php');
    exit;
}

// Handle backup deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_backup') {
    \App\CSRF::verify();
    $filename = $_POST['filename'] ?? '';
    ob_start(); // Catch echoing logs
    $result = $backup->deleteBackup($filename);
    $debugOutput = ob_get_clean(); // Discard logs
    
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($result['success']) {
        if ($isAjax) {
            // Clean any previous output buffers to ensure valid JSON
            while (ob_get_level()) { ob_end_clean(); }
            $response = ['success' => true, 'message' => "Бекап $filename видалено"];
            $json = json_encode($response);
            
            header('Content-Type: application/json');
            header('Content-Length: ' . strlen($json));
            header('Connection: close');
            echo $json;
            exit;
        }
        \App\Flash::set('success', "Бекап $filename видалено");
    } else {
        if ($isAjax) {
            // Clean any previous output buffers to ensure valid JSON
            while (ob_get_level()) { ob_end_clean(); }
            $response = ['success' => false, 'message' => "Помилка при видаленні: " . $result['error']];
            $json = json_encode($response);

            header('Content-Type: application/json');
            header('Content-Length: ' . strlen($json));
            header('Connection: close');
            echo $json;
            exit;
        }
        \App\Flash::set('error', "Помилка при видаленні: " . $result['error']);
    }
    
    header('Location: /admin/backups.php');
    exit;
}

include __DIR__ . '/../views/layout/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-hdd me-2"></i>Бекапи бази даних</h2>
        <form id="backupForm" method="POST">
            <?= \App\CSRF::input() ?>
            <input type="hidden" name="action" value="create_backup">
            <button type="submit" id="backupSubmitBtn" class="btn btn-primary">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                <i class="bi bi-download me-2 animate-icon"></i>Створити бекап зараз
            </button>
        </form>
    </div>

    <!-- Info Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-dark border-info">
                <div class="card-body">
                    <h6 class="text-info text-uppercase small mb-2">
                        <i class="bi bi-clock-history me-1"></i>Автоматичні бекапи
                    </h6>
                    <p class="mb-0">Щодня о 03:00</p>
                    <small class="text-muted">Налаштовано через Cron</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark border-success">
                <div class="card-body">
                    <h6 class="text-success text-uppercase small mb-2">
                        <i class="bi bi-database me-1"></i>Збережено
                    </h6>
                    <p class="mb-0"><?= count($backups) ?> бекапів</p>
                    <small class="text-muted">Останні 7 днів</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark border-warning">
                <div class="card-body">
                    <h6 class="text-warning text-uppercase small mb-2">
                        <i class="bi bi-cloud-upload me-1"></i>Google Drive
                    </h6>
                    <p class="mb-0">
                        <?= \App\GoogleIntegration::isConfigured() ? 'Активно' : 'Не налаштовано' ?>
                    </p>
                    <small class="text-muted">Автоматичне завантаження</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Backups List -->
    <div class="card bg-dark border-secondary">
        <div class="card-header border-secondary">
            <h5 class="mb-0">Доступні бекапи</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($backups)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox display-4 d-block mb-3"></i>
                    <p>Бекапів ще немає</p>
                    <p class="small">Створіть перший бекап вручну або зачекайте автоматичного</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Дата створення</th>
                                <th>Файл</th>
                                <th>Розмір</th>
                                <th>Google Drive</th>
                                <th class="text-end">Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $b): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-calendar3 me-2"></i>
                                    <?= date('d.m.Y H:i', strtotime($b['created_at'])) ?>
                                </td>
                                <td>
                                    <code class="small"><?= htmlspecialchars($b['filename']) ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= round($b['filesize'] / 1024 / 1024, 2) ?> MB
                                    </span>
                                </td>
                                <td>
                                    <?php if ($b['google_drive_id']): ?>
                                        <a href="https://drive.google.com/file/d/<?= $b['google_drive_id'] ?>/view" 
                                           target="_blank" class="btn btn-xs btn-outline-success">
                                            <i class="bi bi-cloud-check"></i> Відкрити
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/backups/database/<?= $b['filename'] ?>" 
                                           class="btn btn-outline-primary" 
                                           download title="Завантажити">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-delete-backup" 
                                                data-filename="<?= htmlspecialchars($b['filename']) ?>"
                                                title="Видалити">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Instructions -->
    <div class="card bg-dark border-secondary mt-4">
        <div class="card-header border-secondary">
            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Налаштування Cron</h5>
        </div>
        <div class="card-body">
            <p class="mb-3">Для автоматичних бекапів додайте в crontab:</p>
            <div class="bg-black p-3 rounded">
                <code class="text-success">
                    0 3 * * * php <?= realpath(__DIR__ . '/../cron/backup_database.php') ?> >> <?= realpath(__DIR__ . '/../logs/backup.log') ?> 2>&1
                </code>
            </div>
            <p class="mt-3 mb-0 small text-muted">
                Це виконає бекап щодня о 03:00 і збереже логи в <code>/logs/backup.log</code>
            </p>
            
            <!-- Windows Task Scheduler -->
            <div class="mt-4">
                <h6 class="text-info">Windows Task Scheduler:</h6>
                <div class="bg-black p-3 rounded">
                    <code class="text-success">
                        schtasks /create /tn "DetalStudio Backup" /tr "php <?= realpath(__DIR__ . '/../cron/backup_database.php') ?>" /sc daily /st 03:00
                    </code>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const backupForm = document.getElementById('backupForm');
    const backupBtn = document.getElementById('backupSubmitBtn');
    if (!backupForm || !backupBtn) return;

    backupForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Disable button and show spinner
        backupBtn.disabled = true;
        const spinner = backupBtn.querySelector('.spinner-border');
        const icon = backupBtn.querySelector('i');
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');

        const formData = new FormData(backupForm);
        
        fetch('/admin/backups.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text().then(text => {
            try {
                if (!text) {
                    throw new Error('Empty response from server');
                }
                return JSON.parse(text);
            } catch (e) {
                console.error('Server returned invalid JSON:', text);
                throw new Error('Invalid JSON response: ' + text.substring(0, 150) + (text.length > 150 ? '...' : ''));
            }
        }))
        .then(data => {
            if (data.success) {
                DetalStudio.showToast(data.message, 'success');
                // Reload page after a delay to show new backup in table
                setTimeout(() => window.location.reload(), 2000);
            } else {
                DetalStudio.showToast(data.message, 'danger');
                // Reset button
                backupBtn.disabled = false;
                spinner.classList.add('d-none');
                icon.classList.remove('d-none');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            DetalStudio.showToast('Виникла помилка: ' + error.message, 'danger');
            // Reset button
            backupBtn.disabled = false;
            spinner.classList.add('d-none');
            icon.classList.remove('d-none');
        });
    });

    // Handle backup deletion with event delegation
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete-backup');
        if (!btn) return;
        
        const filename = btn.getAttribute('data-filename');
        
        confirmDelete(`Ви дійсно хочете видалити бекап ${filename}?`, () => {
            const formData = new FormData();
            formData.append('action', 'delete_backup');
            formData.append('filename', filename);
            formData.append('csrf_token', '<?= \App\Csrf::getToken() ?>');

            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            fetch('/admin/backups.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text().then(text => {
                try {
                    if (!text) {
                        throw new Error('Empty response from server');
                    }
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Server returned invalid JSON:', text);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 150) + (text.length > 150 ? '...' : ''));
                }
            }))
            .then(data => {
                if (data.success) {
                    DetalStudio.showToast(data.message, 'success');
                    // Remove row from table
                    btn.closest('tr').remove();
                } else {
                    DetalStudio.showToast(data.message, 'danger');
                    btn.disabled = false;
                    btn.innerHTML = btn.dataset.originalHtml;
                }
            })
            .catch(err => {
                console.error('Delete error:', err);
                DetalStudio.showToast('Помилка при видаленні: ' + err.message, 'danger');
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml;
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
