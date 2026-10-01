        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('dashboardChart');
            const skeleton = document.getElementById('chartSkeleton');
            const debugEl = document.getElementById('chartDebug');

            const debugLog = (msg, isError = false) => {
                if (!debugEl) return;
                if (isError) debugEl.classList.remove('d-none');
                const line = document.createElement('div');
                line.className = isError ? 'text-danger fw-bold' : '';
                line.innerHTML = `[${new Date().toLocaleTimeString()}] ${msg}`;
                debugEl.appendChild(line);
                debugEl.scrollTop = debugEl.scrollHeight;
                console.log(`[DashboardDebug] ${msg}`);
            };

            // CountUp Logic
            const animateValue = (obj, start, end, duration, isCurrency = false) => {
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    const value = Math.floor(progress * (end - start) + start);

                    if (isCurrency) {
                        obj.innerHTML = new Intl.NumberFormat('uk-UA', {
                            style: 'currency',
                            currency: 'UAH',
                            maximumFractionDigits: 0
                        }).format(value);
                    } else {
                        obj.innerHTML = new Intl.NumberFormat('uk-UA').format(value);
                    }

                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            };

            document.querySelectorAll('.count-up').forEach(el => {
                const value = parseFloat(el.getAttribute('data-value'));
                const isCurrency = el.getAttribute('data-is-currency') === 'true';
                if (value > 0) {
                    animateValue(el, 0, value, 1000, isCurrency);
                } else {
                    // handle zero
                    if (isCurrency) {
                        el.innerText = new Intl.NumberFormat('uk-UA', {
                            style: 'currency',
                            currency: 'UAH',
                            maximumFractionDigits: 0
                        }).format(0);
                    } else {
                        el.innerText = '0';
                    }
                }
            });

            debugLog('Dashboard init sequence started');

            if (!canvas) {
                debugLog('ERROR: Canvas element #dashboardChart not found', true);
                return;
            }

            if (typeof Chart === 'undefined') {
                debugLog('ERROR: Chart.js library not loaded!', true);
                if (skeleton) skeleton.innerHTML = '<div class="text-danger p-4">Помилка: Бібліотека графіків не завантажена. Перевірте з\'єднання.</div>';
                return;
            }

            debugLog('Chart.js found successfully');
            const ctx = canvas.getContext('2d');

            // Theme-aware colors
            const getThemeColors = () => {
                const isLight = document.documentElement.getAttribute('data-theme') === 'light';
                return {
                    textColor: isLight ? '#666' : '#aaa',
                    gridColor: isLight ? 'rgba(0,0,0,0.05)' : 'rgba(255, 255, 255, 0.05)',
                    tooltipBg: isLight ? '#fff' : '#111',
                    tooltipText: isLight ? '#333' : '#f2f2f2',
                    tooltipTitle: isLight ? '#0d6efd' : '#ffcb00',
                    tooltipBorder: isLight ? '#ddd' : '#333'
                };
            };

            let mainChart = null;
            let serviceChart = null;
            let managerChart = null;

            const initMainChart = (data) => {
                const colors = getThemeColors();

                if (mainChart) {
                    mainChart.destroy();
                }

                mainChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                                label: 'Дохід (грн)',
                                data: data.revenue,
                                borderColor: '#1fade4',
                                backgroundColor: 'rgba(31, 190, 228, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Виконано задач',
                                data: data.tasks,
                                borderColor: '#ffcb00',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                tension: 0.4,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                grid: {
                                    color: colors.gridColor
                                },
                                ticks: {
                                    color: colors.textColor
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false,
                                },
                                ticks: {
                                    color: colors.textColor
                                }
                            },
                            x: {
                                grid: {
                                    color: colors.gridColor
                                },
                                ticks: {
                                    color: colors.textColor
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: colors.textColor,
                                    font: {
                                        family: "'Rajdhani', sans-serif",
                                        size: 14
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: colors.tooltipBg,
                                titleColor: colors.tooltipTitle,
                                bodyColor: colors.tooltipText,
                                borderColor: colors.tooltipBorder,
                                borderWidth: 1
                            }
                        }
                    }
                });
            };

            const initServiceChart = (data) => {
                const colors = getThemeColors();
                const ctxS = document.getElementById('serviceRevenueChart').getContext('2d');
                if (serviceChart) serviceChart.destroy();

                serviceChart = new Chart(ctxS, {
                    type: 'bar',
                    data: {
                        labels: data.map(i => i.label),
                        datasets: [{
                            label: 'Дохід',
                            data: data.map(i => i.value),
                            backgroundColor: 'rgba(31, 190, 228, 0.7)',
                            borderColor: '#1fade4',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                grid: {
                                    color: colors.gridColor
                                },
                                ticks: {
                                    color: colors.textColor
                                }
                            },
                            y: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: colors.textColor
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: colors.tooltipBg,
                                titleColor: colors.tooltipTitle,
                                bodyColor: colors.tooltipText
                            }
                        }
                    }
                });
            };

            const initManagerChart = (data) => {
                const colors = getThemeColors();
                const ctxM = document.getElementById('managerRevenueChart').getContext('2d');
                if (managerChart) managerChart.destroy();

                managerChart = new Chart(ctxM, {
                    type: 'doughnut',
                    data: {
                        labels: data.map(i => i.label),
                        datasets: [{
                            data: data.map(i => i.value),
                            backgroundColor: [
                                '#1fade4', '#ffcb00', '#00ff88', '#ff4400', '#aa00ff'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    color: colors.textColor,
                                    font: {
                                        family: "'Rajdhani', sans-serif"
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: colors.tooltipBg,
                                titleColor: colors.tooltipTitle,
                                bodyColor: colors.tooltipText
                            }
                        }
                    }
                });
            };

            const updateRetentionWidget = (stats) => {
                const total = (parseInt(stats.new) || 0) + (parseInt(stats.returning) || 0);
                const retention = total > 0 ? Math.round((stats.returning / total) * 100) : 0;
                const newPercent = total > 0 ? Math.round((stats.new / total) * 100) : 0;
                const retPercent = total > 0 ? 100 - newPercent : 0;

                const el = document.getElementById('retentionPercent');
                if (el) el.innerText = retention + '%';

                const nc = document.getElementById('newClientsCount');
                if (nc) nc.innerText = stats.new;

                const rc = document.getElementById('returningClientsCount');
                if (rc) rc.innerText = stats.returning;

                const np = document.getElementById('newClientsProgress');
                if (np) np.style.width = newPercent + '%';

                const rp = document.getElementById('returningClientsProgress');
                if (rp) rp.style.width = retPercent + '%';
            };

            debugLog('Requesting chart data from /api/get_chart_data.php...');
            fetch('/api/get_chart_data.php')
                .then(response => {
                    debugLog(`Response received: ${response.status} ${response.statusText}`);
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.error || 'Server error');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    debugLog(`Data parsed successfully`);
                    initMainChart(data);
                    if (data.serviceRevenue) initServiceChart(data.serviceRevenue);
                    if (data.managerRevenue) initManagerChart(data.managerRevenue);
                    if (data.clientStats) updateRetentionWidget(data.clientStats);

                    debugLog('All charts initialization completed');
                    if (skeleton) skeleton.classList.add('d-none');
                })
                .catch(err => {
                    debugLog(`ERROR: ${err.message}`, true);
                    console.error('Error loading chart data:', err);
                    if (skeleton) {
                        skeleton.classList.remove('d-none');
                        skeleton.innerHTML = `
                    <div class="text-danger text-center p-4">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                        <p class="mt-2 text-uppercase fw-bold">Помилка завантаження даних</p>
                        <div class="small theme-card p-2 border border-danger text-start">
                            <code>${err.message}</code><br>
                            <small class="text-muted">Спробуйте оновити сторінку через Ctrl+F5</small>
                        </div>
                    </div>`;
                    }
                });

            // Listen for theme changes to update chart
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === "attributes" && mutation.attributeName === "data-theme") {
                        // Re-init chart with new colors if data exists
                        const newColors = getThemeColors();
                        [mainChart, serviceChart, managerChart].forEach(chart => {
                            if (chart) {
                                if (chart.options.scales) {
                                    if (chart.options.scales.x) {
                                        chart.options.scales.x.grid.color = newColors.gridColor;
                                        chart.options.scales.x.ticks.color = newColors.textColor;
                                    }
                                    if (chart.options.scales.y) {
                                        chart.options.scales.y.grid.color = newColors.gridColor;
                                        chart.options.scales.y.ticks.color = newColors.textColor;
                                    }
                                    if (chart.options.scales.y1) {
                                        chart.options.scales.y1.ticks.color = newColors.textColor;
                                    }
                                }
                                if (chart.options.plugins) {
                                    if (chart.options.plugins.legend) {
                                        chart.options.plugins.legend.labels.color = newColors.textColor;
                                    }
                                    if (chart.options.plugins.tooltip) {
                                        chart.options.plugins.tooltip.backgroundColor = newColors.tooltipBg;
                                        chart.options.plugins.tooltip.titleColor = newColors.tooltipTitle;
                                        chart.options.plugins.tooltip.bodyColor = newColors.tooltipText;
                                        chart.options.plugins.tooltip.borderColor = newColors.tooltipBorder;
                                    }
                                }
                                chart.update();
                            }
                        });
                    }
                });
            });

            // --- Load Upcoming Deadlines Widget ---
            const deadlinesWidget = document.getElementById('deadlinesWidget');
            const deadlinesList = document.getElementById('deadlinesList');

            if (deadlinesWidget && deadlinesList) {
                fetch('/api/get_upcoming_deadlines.php')
                    .then(r => r.json())
                    .then(tasks => {
                        if (tasks.length === 0) {
                            deadlinesWidget.style.display = 'none';
                            return;
                        }

                        deadlinesWidget.style.display = 'block';

                        let html = '<div class="deadline-container">';

                        tasks.forEach(task => {
                            const indicatorClass = {
                                'overdue': 'deadline-urgent',
                                'critical': 'deadline-urgent',
                                'warning': 'deadline-warning',
                                'normal': 'deadline-today'
                            } [task.urgency] || 'deadline-today';

                            const deadlineTime = new Date(task.deadline).toLocaleTimeString('uk-UA', {
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            const deadlineDate = new Date(task.deadline).toLocaleDateString('uk-UA', {
                                day: '2-digit',
                                month: '2-digit'
                            });

                            const isOverdueClass = task.urgency === 'overdue' ? 'is-overdue' : '';
                            const multiplierHtml = task.task_count > 1 ? `<span class="deadline-multiplier">Задач x${task.task_count}</span>` : '';

                            html += `
                                <div class="deadline-item ${isOverdueClass}">
                                    <div class="deadline-indicator ${indicatorClass}"></div>
                                    <div class="deadline-info">
                                        <div class="d-flex align-items-center mb-1">
                                            <a href="/tasks/view.php?id=${task.id}" class="text-decoration-none deadline-title">${task.title}</a>
                                            ${multiplierHtml}
                                        </div>
                                        <div class="deadline-meta">
                                            ${task.order_number ? `<span><i class="bi bi-car-front me-1"></i>${task.order_number}</span>` : ''}
                                            <span><i class="bi bi-person me-1"></i>${task.executor_name || 'Не призначено'}</span>
                                        </div>
                                    </div>
                                    <div class="deadline-time-block">
                                        <span class="deadline-time ${task.urgency === 'overdue' || task.urgency === 'critical' ? 'text-danger' : 'text-accent-cyan'}">${deadlineTime}</span>
                                        <span class="deadline-date">${deadlineDate}</span>
                                    </div>
                                </div>
                            `;
                        });

                        html += '</div>';
                        deadlinesList.innerHTML = html;
                    })
                    .catch(err => {
                        console.error('Failed to load deadlines:', err);
                        deadlinesWidget.style.display = 'none';
                    });
            }

            observer.observe(document.documentElement, {
                attributes: true
            });
        });
