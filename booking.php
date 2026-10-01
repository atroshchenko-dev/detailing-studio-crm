<?php

/**
 * DetalStudio - Online Booking
 * Сторінка онлайн-запису
 */

require_once __DIR__ . '/config/config.php';

// Get all active services for dropdown
$services = \App\Models\Service::getAll(true);
?>
<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Онлайн-запис | DetalStudio</title>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1fade4">

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <link rel="stylesheet" href="/assets/css/main.css">

    <style>
        body {
            background-color: #000000;
            background-image: radial-gradient(circle at 50% 50%, #111 0%, #000 100%);
            color: #e0e0e0;
            font-family: 'Rajdhani', sans-serif;
            min-height: 100vh;
        }

        .booking-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .booking-card {
            background: transparent;
            border: none;
            box-shadow: none;
            max-width: 500px;
            width: 100%;
        }

        .form-control,
        .form-select {
            background-color: #111;
            border-color: #222;
            color: #fff;
            padding: 12px;
            text-align: center;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: #111;
            border-color: #1fade4;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(31, 173, 228, 0.25);
        }

        .input-group-text {
            background-color: #222;
            border-color: #222;
            color: #1fade4;
        }

        .custom-btn {
            color: #eee;
            border: 1px solid #444;
            background: rgba(255, 255, 255, 0.05);
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-radius: 20px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            padding: 10px 30px;
            cursor: pointer;
            width: 100%;
        }

        .custom-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #1fade4;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(31, 190, 228, 0.2);
        }

        .form-label {
            color: #888;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: block;
            text-align: center;
        }

        ::placeholder {
            color: #555 !important;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        /* Fix Flatpickr Dark Theme override */
        .flatpickr-calendar {
            background: #1e1e1e;
            border-color: #333;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>

<body>

    <div class="booking-container">

        <div class="booking-card">

            <div class="text-center mb-4">
                <div class="logo-text" style="font-weight: 700; font-size: 1.5rem; letter-spacing: 0.04em;">DetalStudio</div>
                <p class="text-muted mt-2" style="font-size: 0.9rem; letter-spacing: 1px; text-transform: uppercase;">Онлайн-запис на сервіс</p>
            </div>

            <form id="bookingForm">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <input type="text" name="name" class="form-control" placeholder="Ваше ім'я" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <input type="tel" name="phone" class="form-control" placeholder="Телефон" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <input type="text" name="car_make" class="form-control" placeholder="Марка авто (Audi)" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <input type="text" name="car_model" class="form-control" placeholder="Модель (Q8)">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Послуги</label>
                    <input type="hidden" name="service" id="serviceInput">

                    <div class="d-grid">
                        <button type="button" class="btn btn-outline-secondary text-start d-flex justify-content-between align-items-center" data-bs-toggle="modal" data-bs-target="#servicesModal" style="background: #111; border-color: #222; color: #aaa;">
                            <span id="serviceBtnText">Оберіть послуги</span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>

                    <div id="selectedServicesPreview" class="d-flex flex-wrap gap-2 mt-2">
                        <!-- Selected pills will appear here -->
                    </div>
                </div>

                <div class="mb-3">
                    <input type="text" name="date" class="form-control flatpickr" placeholder="Бажана дата та час">
                </div>

                <div class="mb-4">
                    <textarea name="notes" class="form-control" rows="2" placeholder="Додаткові коментарі..."></textarea>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="custom-btn">
                        Надіслати заявку
                    </button>
                </div>
            </form>

            <div id="successMessage" class="text-center d-none py-4">
                <i class="bi bi-check-circle-fill" style="font-size: 4rem; color: #1fade4;"></i>
                <h3 class="mt-3 text-white text-uppercase" style="font-weight: 600; letter-spacing: 1px;">Дякуємо!</h3>
                <p class="text-muted">Ваша заявка прийнята. Менеджер зв'яжеться з вами найближчим часом.</p>
                <a href="/" class="custom-btn mt-3" style="width: auto; padding: 10px 40px;">На головну</a>
            </div>

            <div class="text-center mt-5">
                <p class="text-muted small mb-0">
                    <i class="bi bi-geo-alt me-1"></i>м. Київ, вул. Прикладна, 1
                </p>
                <p class="text-muted small">
                    <i class="bi bi-telephone me-1"></i>+380 00 000 00 00
                </p>
            </div>

        </div>

    </div>

    <!-- Service Selection Modal -->
    <div class="modal fade" id="servicesModal" tabindex="-1" aria-labelledby="servicesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="background: #191919; color: #fff; border: 1px solid #333;">
                <div class="modal-header border-bottom border-secondary" style="border-color: #333 !important;">
                    <h5 class="modal-title fs-5" id="servicesModalLabel">Оберіть послуги</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                    $currentCategory = null;
                    foreach ($services as $service):
                        $categoryName = $service['category_name'] ?? 'Додаткові послуги';
                        if ($categoryName !== $currentCategory):
                            $currentCategory = $categoryName;
                    ?>
                            <h6 class="text-uppercase small fw-bold mt-3 mb-2" style="color: #1fade4; letter-spacing: 1px;"><?= htmlspecialchars($currentCategory) ?></h6>
                        <?php endif; ?>

                        <div class="form-check py-2 border-bottom" style="border-color: rgba(255,255,255,0.05) !important;">
                            <input class="form-check-input service-checkbox" type="checkbox" value="<?= htmlspecialchars($service['name']) ?>" id="srv_<?= $service['id'] ?>" style="background-color: #2c2c2c; border-color: #444; cursor: pointer;">
                            <label class="form-check-label w-100 stretched-link" for="srv_<?= $service['id'] ?>" style="cursor: pointer;">
                                <?= htmlspecialchars($service['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>

                    <div class="form-check py-2 mt-2">
                        <input class="form-check-input service-checkbox" type="checkbox" value="other" id="srv_other" style="background-color: #2c2c2c; border-color: #444; cursor: pointer;">
                        <label class="form-check-label w-100 stretched-link" for="srv_other" style="cursor: pointer;">
                            Інше / Не знаю
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary" style="border-color: #333 !important;">
                    <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal" style="background: linear-gradient(45deg, #1fade4, #005cbf); border: none;">Зберегти вибір</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/uk.js"></script>
    <script>
        // Init Datepicker
        flatpickr(".flatpickr", {
            locale: "uk",
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            time_24hr: true,
            disableMobile: "true"
        });

        // Service Selection Logic
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.service-checkbox');
            const previewContainer = document.getElementById('selectedServicesPreview');
            const serviceInput = document.getElementById('serviceInput');
            const serviceBtnText = document.getElementById('serviceBtnText');

            function updateSelectedServices() {
                const selected = [];
                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        selected.push(cb.value);
                    }
                });

                // Update hidden input
                serviceInput.value = selected.join(', ');

                // Update Chips Preview
                previewContainer.innerHTML = '';
                if (selected.length > 0) {
                    selected.forEach(val => {
                        const chip = document.createElement('span');
                        chip.className = 'badge rounded-pill fw-normal';
                        chip.style.backgroundColor = 'rgba(31, 173, 228, 0.15)';
                        chip.style.color = '#1fade4';
                        chip.style.border = '1px solid rgba(31, 173, 228, 0.3)';
                        chip.style.padding = '8px 12px';
                        chip.innerHTML = val === 'other' ? 'Інше' : val;
                        previewContainer.appendChild(chip);
                    });
                    serviceBtnText.innerText = `Обрано послуг: ${selected.length}`;
                    serviceBtnText.classList.add('text-white');
                } else {
                    serviceBtnText.innerText = 'Оберіть послуги';
                    serviceBtnText.classList.remove('text-white');
                }
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectedServices);
            });
        });

        // Form Submission
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;

            // Check if service is selected
            if (!document.getElementById('serviceInput').value && !document.querySelector('[name="notes"]').value) {
                alert('Будь ласка, оберіть послугу або додайте коментар');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Відправка...';

            const formData = new FormData(this);

            fetch('/api/create_booking.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('bookingForm').classList.add('d-none');
                        document.getElementById('successMessage').classList.remove('d-none');
                    } else {
                        alert('Помилка: ' + (data.error || 'Щось пішло не так'));
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Помилка з\'єднання');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });
    </script>
</body>

</html>