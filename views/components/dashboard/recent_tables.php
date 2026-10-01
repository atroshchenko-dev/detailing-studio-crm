        <!-- Analytics Row (Top Services & Clients) -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Останні замовлення</h5>
                        <a href="/orders/index.php" class="btn btn-sm btn-outline-primary">Всі замовлення</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Клієнт</th>
                                        <th>Статус</th>
                                        <th>Сума</th>
                                        <th>Дата</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentOrders)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">Немає замовлень</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <a href="/orders/view.php?id=<?= $order['id'] ?>" class="fw-bold text-decoration-none">
                                                        <?= $order['order_number'] ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span><?= htmlspecialchars($order['client_name'] ?? 'Клієнт') ?></span>
                                                        <small class="text-muted"><?= htmlspecialchars(($order['make'] ?? '') . ' ' . ($order['model'] ?? '')) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge rounded-pill <?= function_exists('get_status_badge_class') ? get_status_badge_class($order['status'] ?? '') : 'bg-secondary' ?>">
                                                        <?= Order::getStatusName($order['status'] ?? 'new') ?>
                                                    </span>
                                                </td>
                                                <td><?= format_currency($order['total_amount']) ?></td>
                                                <td><?= format_date($order['created_at'], 'd.m') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Актуальні задачі</h5>
                        <a href="/tasks/index.php" class="btn btn-sm btn-outline-primary">Всі задачі</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Задача</th>
                                        <th>Пріоритет</th>
                                        <th>Статус</th>
                                        <th>Дедлайн</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentTasks)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-3 text-muted">Немає задач</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentTasks as $task): ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <a href="/tasks/view.php?id=<?= $task['id'] ?>" class="fw-bold text-decoration-none text-truncate d-inline-block" style="max-width: 200px;">
                                                        <?= htmlspecialchars($task['title']) ?>
                                                    </a>
                                                    <?php if ($task['order_number']): ?>
                                                        <div class="small text-muted">
                                                            <i class="bi bi-car-front"></i> <?= $task['order_number'] ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?= function_exists('get_priority_badge_class') ? get_priority_badge_class($task['priority'] ?? 'medium') : 'bg-secondary' ?>">
                                                        <?= ucfirst($task['priority'] ?? 'medium') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge rounded-pill <?= function_exists('get_status_badge_class') ? get_status_badge_class($task['status'] ?? '') : 'bg-secondary' ?>">
                                                        <?= Task::getStatusName($task['status'] ?? 'created') ?>
                                                    </span>
                                                </td>
                                                <td class="<?= (!empty($task['deadline']) && strtotime($task['deadline']) < time() && $task['status'] != 'completed') ? 'text-danger fw-bold' : '' ?>">
                                                    <?= format_date($task['deadline'] ?? null, 'd.m H:i') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
