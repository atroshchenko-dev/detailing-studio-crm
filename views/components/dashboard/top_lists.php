        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Популярні послуги</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($topServices)): ?>
                                <li class="list-group-item text-center text-muted py-3">Немає даних</li>
                            <?php else: ?>
                                <?php foreach ($topServices as $service): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-medium"><?= htmlspecialchars($service['service_name']) ?></span>
                                            <div class="small text-muted"><?= $service['times_ordered'] ?> замовлень</div>
                                        </div>
                                        <span class="text-accent-cyan"><?= format_currency($service['total_revenue']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Топ клієнтів</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($topClients)): ?>
                                <li class="list-group-item text-center text-muted py-3">Немає даних</li>
                            <?php else: ?>
                                <?php foreach ($topClients as $client): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-soft-primary text-primary me-3">
                                                <?= mb_strtoupper(mb_substr($client['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <a href="/clients/view.php?id=<?= $client['id'] ?>" class="fw-medium text-decoration-none text-body">
                                                    <?= htmlspecialchars($client['name']) ?>
                                                </a>
                                                <div class="small text-muted"><?= $client['orders_count'] ?> замовлень</div>
                                            </div>
                                        </div>
                                        <span class="fw-bold"><?= format_currency($client['total_spent']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
