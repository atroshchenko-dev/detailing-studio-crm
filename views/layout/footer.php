<?php
/**
 * DetalStudio - Layout Footer
 * Підвал сторінки
 */
?>
    </main>
    
    <!-- Global Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-warning border-opacity-50">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-warning" id="confirmTitle">Підтвердження</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-0 text-main fs-5" id="confirmMessage">Ви впевнені?</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="button" class="btn btn-sm btn-warning px-4" id="confirmButton">Так, продовжити</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Telegram Instructions Modal -->
    <div class="modal fade" id="telegramInstructionsModal" tabindex="-1" aria-labelledby="telegramInstructionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="telegramInstructionsModalLabel">
                        <i class="bi bi-telegram me-2 text-info"></i>Інструкція по боту
                    </h5>
                    <button type="button" class="btn-close" data-bs-toggle="modal" data-bs-target="#telegramInstructionsModal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="display-6 text-info mb-2"><i class="bi bi-robot"></i></div>
                        <h6>@DetalStudioBot</h6>
                    </div>
                    
                    <h6 class="text-accent-orange mb-3">Як підключити сповіщення:</h6>
                    <ol class="small text-muted">
                        <li class="mb-2">Переконайтеся, що адміністратор додав ваш <b>Telegram @Username</b> у ваш профіль на сайті.</li>
                        <li class="mb-2">Натисніть на кнопку <b>«Бот для майстрів»</b> у шапці сайту або знайдіть бота в Telegram за назвою <b>@DetalStudioBot</b>.</li>
                        <li class="mb-2">Натисніть кнопку <b>«Розпочати» (Start)</b> в чаті з ботом.</li>
                        <li class="mb-2">Бот підтвердить успішну прив'язку акаунту.</li>
                    </ol>
                    
                    <h6 class="text-accent-orange mt-4 mb-3">Можливості бота:</h6>
                    <ul class="small text-muted">
                        <li class="mb-1"><i class="bi bi-check2-circle text-success me-2"></i>Миттєві сповіщення про нові задачі</li>
                        <li class="mb-1"><i class="bi bi-check2-circle text-success me-2"></i>Керування статусом задачі прямо з Telegram:
                            <ul class="mt-1">
                                <li><b>Прийняти</b> — підтвердити отримання</li>
                                <li><b>В роботу</b> — почати виконання</li>
                                <li><b>Готово</b> — завершити задачу</li>
                            </ul>
                        </li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Зрозуміло</button>
                    <a href="https://t.me/DetalStudioBot" target="_blank" class="btn btn-sm btn-primary">
                        <i class="bi bi-telegram me-2"></i>Перейти до бота
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/uk.js"></script>
    
    <!-- Tom Select -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <script>
        // Scripts moved to /assets/js/main.js
    </script>
    
    <?php if (isset($additionalScripts)): ?>
        <?= $additionalScripts ?>
    <?php endif; ?>
    
    <footer class="mt-5 text-center text-muted">
        <small>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Version <?= APP_VERSION ?></small>
        <?php if (Auth::hasRole('director')): ?>
            <div class="mt-2">
                <a href="/admin/telegram_test.php" class="text-muted text-decoration-none small">
                    <i class="bi bi-gear me-1"></i>Telegram Settings
                </a>
            </div>
        <?php endif; ?>
    </footer>
</body>
</html>