        <!-- TODAY'S STATS (New Widget) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card theme-card border-secondary">
                    <div class="card-body py-3">
                        <div class="row align-items-center">
                            <div class="col-md-3 border-md-end border-bottom border-md-bottom-0 border-secondary pb-3 pb-md-0 mb-3 mb-md-0">
                                <span class="text-muted text-uppercase small" style="font-size: 0.7rem;">Сьогодні</span>
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="mb-0 theme-text"><?= number_format($todayStats['new_orders'] ?? 0) ?></h4>
                                    <span class="badge bg-soft-info text-info rounded-pill start-100">+Нових</span>
                                </div>
                            </div>
                            <div class="col-md-3 border-md-end border-bottom border-md-bottom-0 border-secondary pb-3 pb-md-0 mb-3 mb-md-0">
                                <span class="text-muted text-uppercase small" style="font-size: 0.7rem;">Виконано</span>
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="mb-0 text-success"><?= number_format($todayStats['completed_orders'] ?? 0) ?></h4>
                                    <small class="text-muted">замовлень</small>
                                </div>
                            </div>
                            <div class="col-md-3 border-md-end border-bottom border-md-bottom-0 border-secondary pb-3 pb-md-0 mb-3 mb-md-0">
                                <span class="text-muted text-uppercase small" style="font-size: 0.7rem;">Каса сьогодні</span>
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="mb-0 text-accent-cyan"><?= format_currency($todayStats['revenue'] ?? 0) ?></h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted text-uppercase small" style="font-size: 0.7rem;">Дедлайни</span>
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="mb-0 text-warning"><?= number_format($todayStats['tasks_due'] ?? 0) ?></h4>
                                    <small class="text-muted">задач</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- UPCOMING DEADLINES WIDGET -->
        <div class="row mb-4" id="deadlinesWidget" style="display: none;">
            <div class="col-12">
                <div class="card theme-card border-warning">
                    <div class="card-header border-warning d-flex justify-content-between align-items-center py-2">
                        <h6 class="mb-0"><i class="bi bi-alarm text-warning me-2"></i>Найближчі дедлайни (24 год)</h6>
                        <a href="/tasks/?filter=urgent" class="btn btn-sm btn-outline-warning py-0">Всі задачі</a>
                    </div>
                    <div class="card-body p-0" id="deadlinesList">
                        <div class="text-center py-3 text-muted">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            Завантаження...
                        </div>
                    </div>
                </div>
            </div>
        </div>
