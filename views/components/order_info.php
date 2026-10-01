<?php

/**
 * Order Info Component for view.php
 *
 * @var array $order
 * @var array $client
 * @var array $car
 */
?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-receipt me-2"></i>Замовлення #<?= $order['order_number'] ?>
            <span class="badge <?= get_status_badge_class($order['status']) ?> ms-2">
                <?= \App\Models\Order::getStatusName($order['status']) ?>
            </span>
        </h5>
    </div>
    <div class="card-body">
        <!-- Order Status Stepper -->
        <?php
        $orderSteps = [
            'new' => ['label' => 'Нове', 'icon' => 'bi-plus-circle'],
            'in_progress' => ['label' => 'В роботі', 'icon' => 'bi-gear'],
            'ready' => ['label' => 'Готово', 'icon' => 'bi-check-circle'],
            'closed' => ['label' => 'Закрито', 'icon' => 'bi-lock'],
        ];
        $stepKeys = array_keys($orderSteps);
        $currentStepIndex = array_search($order['status'], $stepKeys);
        // Handle cancelled/waiting_client as special
        $isCancelled = ($order['status'] === 'cancelled');
        $isWaiting = ($order['status'] === 'waiting_client');
        if ($currentStepIndex === false) $currentStepIndex = -1;
        ?>
        <div class="d-flex justify-content-between align-items-center mb-3 position-relative" style="padding: 0 10px;">
            <!-- Connecting line -->
            <div style="position: absolute; top: 50%; left: 30px; right: 30px; height: 2px; background: var(--border-color, #444); z-index: 0;"></div>
            <?php foreach ($orderSteps as $stepKey => $step):
                $stepIndex = array_search($stepKey, $stepKeys);
                $isCompleted = !$isCancelled && $currentStepIndex >= 0 && $stepIndex < $currentStepIndex;
                $isCurrent = !$isCancelled && $stepKey === $order['status'];
            ?>
                <div class="text-center position-relative" style="z-index: 1;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-1 <?php
                                                                                                            if ($isCurrent) echo 'bg-primary text-white shadow';
                                                                                                            elseif ($isCompleted) echo 'bg-success text-white';
                                                                                                            else echo 'bg-dark text-muted border border-secondary';
                                                                                                            ?>" style="width: 36px; height: 36px; font-size: 1rem;">
                        <i class="bi <?= $isCompleted ? 'bi-check-lg' : $step['icon'] ?>"></i>
                    </div>
                    <div class="small <?= $isCurrent ? 'fw-bold text-primary' : ($isCompleted ? 'text-success' : 'text-muted') ?>">
                        <?= $step['label'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($isCancelled): ?>
            <div class="alert alert-danger py-1 px-3 text-center small mb-3">
                <i class="bi bi-x-circle me-1"></i>Замовлення скасовано
            </div>
        <?php elseif ($isWaiting): ?>
            <div class="alert alert-warning py-1 px-3 text-center small mb-3">
                <i class="bi bi-hourglass-split me-1"></i>Очікує на клієнта
            </div>
        <?php endif; ?>

        <?php if (isset($totalTasks) && $totalTasks > 0): ?>
            <?php
            $completedTasks = count(array_filter($relatedTasks, fn($t) => $t['status'] === 'completed'));
            $progressPercent = round(($completedTasks / $totalTasks) * 100);
            $progressColor = $progressPercent < 50 ? 'bg-info' : ($progressPercent < 100 ? 'bg-primary' : 'bg-success');
            ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted">Виконання задач: <?= $completedTasks ?> з <?= $totalTasks ?></small>
                    <small class="fw-bold text-accent-cyan"><?= $progressPercent ?>%</small>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar <?= $progressColor ?>" role="progressbar" style="width: <?= $progressPercent ?>%"></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <h6>Інформація про клієнта</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Ім'я:</strong></td>
                        <td>
                            <a href="/clients/view.php?id=<?= $client['id'] ?>" class="text-accent-cyan text-decoration-none fw-bold">
                                <?= htmlspecialchars($client['name']) ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Телефон:</strong></td>
                        <td><?= \App\Utils\Helper::displayPhone($client['phone']) ?></td>
                    </tr>
                    <?php if (!empty($client['email'])): ?>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?= htmlspecialchars($client['email']) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Інформація про авто</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Марка:</strong></td>
                        <td><?= htmlspecialchars($car['make']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Модель:</strong></td>
                        <td><?= htmlspecialchars($car['model']) ?></td>
                    </tr>
                    <?php if (!empty($car['year'])): ?>
                        <tr>
                            <td><strong>Рік:</strong></td>
                            <td><?= $car['year'] ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($car['license_plate'])): ?>
                        <tr>
                            <td><strong>Номер:</strong></td>
                            <td><?= htmlspecialchars($car['license_plate']) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php if (!empty($order['notes'])): ?>
            <div class="mt-3">
                <h6>Примітки</h6>
                <p class="text-muted"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>