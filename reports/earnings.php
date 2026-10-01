<?php
/**
 * DetalStudio - Earnings Report
 * Звіт по заробітку
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;

Auth::requireAuth();

$userId = Auth::getUserId();
$isManagerOrDirector = Auth::hasAnyRole(['director', 'manager']);
$isDirector = Auth::isDirector();
$reportUserId = ($isDirector && isset($_GET['user_id'])) ? intval($_GET['user_id']) : $userId;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_executor') {
    \App\CSRF::verify();
    if (!$isManagerOrDirector) {
        \App\Flash::error('У вас немає прав для виконання цієї дії.');
        redirect('/reports/earnings.php');
    }
    
    $executorId = intval($_POST['executor_id'] ?? 0);
    $payAmount = (float)($_POST['amount'] ?? 0);

    if ($executorId && $payAmount > 0) {
        require_once __DIR__ . '/../includes/Payments.php';
        \App\Payments::create([
            'user_id' => $executorId,
            'amount' => $payAmount,
            'payment_type' => 'salary',
            'payment_method' => 'cash',
            'payment_date' => date('Y-m-d'),
            'notes' => 'Виплата за задачі (Закриття дня/Аванс)'
        ]);

        \App\Flash::success("Виплату виконавцю проведено успішно");
        
        // Push notification
        $stmtUser = db()->prepare("SELECT telegram_chat_id FROM users WHERE id = ?");
        $stmtUser->execute([$executorId]);
        $executorChatId = $stmtUser->fetchColumn();

        if ($executorChatId) {
            require_once __DIR__ . '/../includes/Telegram.php';
            
            // Calculate remaining balance dynamically for the msg
            $stmtDebt = db()->prepare("
                SELECT 
                    COALESCE(SUM(t.commission_amount), 0) - COALESCE(
                        (SELECT SUM(amount) FROM employee_payments WHERE user_id = u.id AND payment_type = 'salary')
                    , 0) as total_unpaid
                FROM users u
                LEFT JOIN tasks t ON u.id = t.executor_id AND t.status IN ('completed', 'closed') AND t.is_deleted = 0
                WHERE u.id = ?
                GROUP BY u.id
            ");
            $stmtDebt->execute([$executorId]);
            $remainingDebt = (float)$stmtDebt->fetchColumn();

            $msg = "✅ <b>Вам нараховано виплату!</b>\n";
            $msg .= "💰 Сума: <b>" . format_currency($payAmount) . "</b>\n";
            $msg .= "📉 Залишок боргу: <b>" . format_currency($remainingDebt) . "</b>\n\n";
            $msg .= "<i>Деталі можна переглянути в боті через «" . \App\BotConstants::BTN_MY_PAYOUTS . "».</i>";

            \App\Telegram::sendMessage($executorChatId, $msg);
        }
    }
    redirect('/reports/earnings.php');
}

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get earnings
$stmt = db()->prepare("
    SELECT t.*, s.name as service_name, o.order_number 
    FROM tasks t
    LEFT JOIN services s ON t.service_id = s.id
    LEFT JOIN orders o ON t.order_id = o.id
    WHERE t.executor_id = ? AND t.status = 'completed' AND t.updated_at BETWEEN ? AND ?
    ORDER BY t.updated_at DESC
");
$stmt->execute([$reportUserId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$earnings = $stmt->fetchAll();

$totalEarnedDateRange = array_sum(array_column($earnings, 'commission_amount'));

// Selected period payments
$stmtPayments = db()->prepare("SELECT SUM(amount) FROM employee_payments WHERE user_id = ? AND payment_date BETWEEN ? AND ? AND payment_type = 'salary'");
$stmtPayments->execute([$reportUserId, $startDate, $endDate]);
$totalPaidDateRange = (float)$stmtPayments->fetchColumn();

// All-time debt
$stmtAllTimeEarned = db()->prepare("SELECT SUM(commission_amount) FROM tasks WHERE executor_id = ? AND status IN ('completed', 'closed') AND is_deleted = 0");
$stmtAllTimeEarned->execute([$reportUserId]);
$allTimeEarned = (float)$stmtAllTimeEarned->fetchColumn();

$stmtAllTimePaid = db()->prepare("SELECT SUM(amount) FROM employee_payments WHERE user_id = ? AND payment_type = 'salary'");
$stmtAllTimePaid->execute([$reportUserId]);
$allTimePaid = (float)$stmtAllTimePaid->fetchColumn();

$totalPending = $allTimeEarned - $allTimePaid;

$pageTitle = 'Мій заробіток';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-accent-cyan">
                    <i class="bi bi-wallet2 me-2"></i>ЗВІТ ПО ЗАРОБІТКУ
                </h5>
                <?php if ($isManagerOrDirector && \App\Models\Setting::get('feature_executor_payouts', '1') == '1'): ?>
                    <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#closeDayModal">
                        <i class="bi bi-cash-stack me-1"></i> Закрити день
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <?php if ($isDirector): ?>
                        <div class="col-md-4">
                            <label class="form-label">Співробітник</label>
                            <select name="user_id" class="form-select">
                                <?php foreach (Auth::getAllUsers() as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $reportUserId == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">З</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">По</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">Показати</button>
                    </div>
                </form>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-dark-accent rounded text-center">
                            <h4 class="text-white"><?= format_currency($totalEarnedDateRange) ?></h4>
                            <small class="text-muted text-uppercase">Нараховано за зміну</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-dark-accent rounded text-center border-start border-primary">
                            <h4 class="text-success"><?= format_currency($totalPaidDateRange) ?></h4>
                            <small class="text-muted text-uppercase">Виплачено за зміну</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-dark-accent rounded text-center border-start border-warning <?= $totalPending > 0 ? 'bg-opacity-10 bg-warning' : '' ?>">
                            <h4 class="text-warning"><?= format_currency($totalPending) ?></h4>
                            <small class="text-muted text-uppercase">Поточний загальний борг</small>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead>
                            <tr>
                                <th>Дата завершення</th>
                                <th>Замовлення</th>
                                <th>Послуга</th>
                                <th>Нараховано</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($earnings as $e): ?>
                                <tr>
                                    <td><?= format_date($e['updated_at']) ?></td>
                                    <td>
                                        <a href="/orders/view.php?id=<?= $e['order_id'] ?>" class="text-accent-cyan">
                                            #<?= htmlspecialchars($e['order_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($e['service_name'] ?: $e['title']) ?></td>
                                    <td class="fw-bold"><?= format_currency($e['commission_amount']) ?></td>
                                    <td>
                                    <td>
                                        <i class="bi bi-dash fw-bold text-muted"></i> <!-- Removed per-task status based on is_commission_paid -->
                                    </td>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isManagerOrDirector && \App\Models\Setting::get('feature_executor_payouts', '1') == '1'): 
    $stmtPayouts = db()->prepare("
        SELECT 
            u.id as executor_id,
            u.full_name as executor_name,
            COALESCE(SUM(t.commission_amount), 0) - COALESCE(
                (SELECT SUM(amount) FROM employee_payments WHERE user_id = u.id AND payment_type = 'salary')
            , 0) as total_payout
        FROM users u
        LEFT JOIN tasks t ON u.id = t.executor_id AND t.status IN ('completed', 'closed') AND t.is_deleted = 0 AND t.commission_amount > 0
        WHERE u.is_active = 1
        GROUP BY u.id
        HAVING total_payout > 0
    ");
    $stmtPayouts->execute();
    $dayPayouts = $stmtPayouts->fetchAll();
?>
<div class="modal fade" id="closeDayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-success"><i class="bi bi-cash-stack me-2"></i>Виплати на кінець дня</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <?php if (empty($dayPayouts)): ?>
                    <div class="p-4 text-center">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="mt-2 mb-0">Всі виплати закрито. Боргів немає!</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($dayPayouts as $p): ?>
                            <div class="list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 text-light"><?= htmlspecialchars($p['executor_name']) ?></h6>
                                    <div class="text-accent-cyan fw-bold fs-5"><?= format_currency($p['total_payout']) ?></div>
                                </div>
                                <form method="POST" class="m-0 p-0 d-flex align-items-center gap-2">
                                    <?= \App\CSRF::input() ?>
                                    <input type="hidden" name="action" value="pay_executor">
                                    <input type="hidden" name="executor_id" value="<?= $p['executor_id'] ?>">
                                    <input type="number" step="0.01" max="<?= $p['total_payout'] ?>" name="amount" class="form-control form-control-sm text-end" style="width: 100px" value="<?= $p['total_payout'] ?>" required>
                                    <button type="submit" class="btn btn-sm btn-success px-3">
                                        Виплатити
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="p-3 bg-dark-accent border-top border-secondary small text-muted">
                        <i class="bi bi-info-circle me-1"></i>Введіть суму авансу або залиште суму всієї заборгованості за замовчуванням.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php 
$additionalScripts = <<<HTML
<script>
    $(document).ready(function() {
        $(".mark-paid-btn").on("click", function() {
            const btn = $(this);
            const taskId = btn.data("id");
            
            showConfirm({
                title: 'Підтвердження виплати',
                message: 'Позначити цей заробіток як виплачений?',
                confirmText: 'Так, позначити',
                confirmButtonClass: 'btn-success',
                onConfirm: () => {
                    btn.prop("disabled", true).html("<span class=\"spinner-border spinner-border-sm\"></span>");
                    
                    $.ajax({
                        url: "/api/mark_commission_paid.php",
                        method: "POST",
                        data: { 
                            task_id: taskId,
                            csrf_token: "<?= CSRF::getToken() ?>"
                        },
                        success: function(response) {
                            if (response.success) {
                                $(".status-badge-" + taskId).removeClass("bg-warning text-dark").addClass("bg-success text-white").text("Виплачено");
                                btn.remove();
                            } else {
                                alert("Помилка: " + (response.error || "Невідома помилка"));
                                btn.prop("disabled", false).html("<i class=\"bi bi-check2\"></i>");
                            }
                        },
                        error: function() {
                            alert("Помилка зв'язку з сервером");
                            btn.prop("disabled", false).html("<i class=\"bi bi-check2\"></i>");
                        }
                    });
                }
            });
        });
    });
</script>
HTML;
include __DIR__ . '/../views/layout/footer.php'; ?>
