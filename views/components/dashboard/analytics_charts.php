        <!-- Content Rows -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up me-2"></i>Аналітика доходів та задач
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary active">Останні 6 місяців</button>
                        </div>
                    </div>
                    <div class="card-body position-relative">
                        <div id="chartSkeleton" class="chart-loader">
                            <div class="skeleton" style="height: 300px;"></div>
                        </div>
                        <!-- Debug Info Container (Hidden by default, shown on error) -->
                        <div id="chartDebug" class="d-none p-3 small font-monospace text-warning border-top border-secondary" style="background: rgba(var(--secondary-bg-rgb), 0.3); max-height: 150px; overflow-y: auto;">
                            <div class="fw-bold mb-1 border-bottom border-secondary pb-1 text-uppercase" style="font-size: 0.65rem;">System Logs (Debug):</div>
                        </div>
                        <canvas id="dashboardChart" style="max-height: 350px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Клієнтська база</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column h-100 justify-content-center">
                            <div class="text-center mb-4">
                                <h2 id="retentionPercent" class="display-4 fw-bold text-accent-cyan">0%</h2>
                                <p class="text-muted">Повертаємість клієнтів (LTV)</p>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Нові клієнти</span>
                                    <span id="newClientsCount" class="fw-bold">0</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div id="newClientsProgress" class="progress-bar bg-info" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Постійні клієнти</span>
                                    <span id="returningClientsCount" class="fw-bold">0</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div id="returningClientsProgress" class="progress-bar bg-accent-cyan" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="mt-auto pt-4 border-top border-secondary">
                                <small class="text-muted d-block text-center italic">Дані за поточний календарний місяць</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Дохід за послугами</h5>
                    </div>
                    <div class="card-body" style="height: 300px;">
                        <canvas id="serviceRevenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Ефективність менеджерів</h5>
                    </div>
                    <div class="card-body" style="height: 300px;">
                        <canvas id="managerRevenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
