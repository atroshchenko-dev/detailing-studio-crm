<?php

/**
 * DetalStudio - User Profile
 * Профіль користувача
 */

require_once __DIR__ . '/config/config.php';

Auth::requireAuth();

$id = Auth::getUserId();
$user = Auth::getUserById($id);

if (!$user) {
    Auth::logout(); // Should not happen if requiredAuth passed, but safety first
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();

    $validator = new Validator($_POST);
    $validator->required('username', 'Ім\'я користувача')
        ->required('email', 'Email')
        ->email('email', 'Email')
        ->unique('username', 'users', 'username', 'Ім\'я користувача', $id)
        ->unique('email', 'users', 'email', 'Email', $id);

    // Password validation only if provided
    if (!empty($_POST['password'])) {
        $validator->minLength('password', 'Пароль', 6);
    }

    $validator->required('full_name', 'Повне ім\'я');

    if ($validator->isValid()) {
        try {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);

            $updateData = [
                'username' => $username,
                'email' => $email,
                'full_name' => trim($_POST['full_name']),
                'phone' => trim($_POST['phone'] ?? ''),
                'telegram_username' => trim($_POST['telegram_username'] ?? ''),
                'notification_settings' => json_encode([
                    'new_order' => isset($_POST['notification_settings']['new_order']),
                    'task_status' => isset($_POST['notification_settings']['task_status']),
                    'comments' => isset($_POST['notification_settings']['comments'])
                ]),
                'password' => !empty($_POST['password']) ? $_POST['password'] : null
            ];

            if (Auth::updateUser($id, $updateData)) {
                Flash::success('Профіль оновлено успішно');
                // Refresh user data
                $user = Auth::getUserById($id);
                // Update session name if changed
                if (isset($_SESSION['user_name'])) {
                    $_SESSION['user_name'] = $updateData['full_name'];
                }
            } else {
                Flash::error('Помилка при оновленні профілю');
            }
        } catch (Exception $e) {
            Flash::error('Помилка бази даних: ' . $e->getMessage());
        }
    } else {
        Flash::error('Будь ласка, виправте помилки');
        $errors = $validator->getErrors();
        $user = array_merge($user, $_POST); // Keep input values
    }
}

$pageTitle = 'Мій профіль';
include __DIR__ . '/views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-person-circle me-2"></i>Мій профіль
                    </h5>
                    <a href="/" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-speedometer2 me-1"></i>На головну
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?= CSRF::input() ?>

                    <div class="text-center mb-4">
                        <div class="avatar rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2.5rem;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <h4 class="mb-1"><?= htmlspecialchars($user['full_name']) ?></h4>
                        <span class="badge bg-secondary rounded-pill"><?= Auth::getRoleLabel($user['role']) ?></span>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Повне ім'я *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars($user['full_name']) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label">Ім'я користувача (Login) *</label>
                        <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" id="username" name="username" required value="<?= htmlspecialchars($user['username']) ?>">
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback"><?= $errors['username'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?= $errors['email'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telegram_username" class="form-label">Telegram Username</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control" id="telegram_username" name="telegram_username" placeholder="nick" value="<?= htmlspecialchars($user['telegram_username'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-3 text-muted"><i class="bi bi-bell me-2"></i>Налаштування сповіщень Telegram</h6>

                    <?php
                    $settings = json_decode($user['notification_settings'] ?? '{}', true) ?: [];
                    // Defaults
                    $notifyNewOrder = $settings['new_order'] ?? true;
                    $notifyOrderStatus = $settings['order_status'] ?? true;
                    $notifyTaskStatus = $settings['task_status'] ?? true;
                    $notifyComments = $settings['comments'] ?? true;
                    ?>

                    <div class="card bg-light border-0">
                        <div class="card-body">
                            <?php if (Auth::isManager() || Auth::isDirector()): ?>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="notify_new_order" name="notification_settings[new_order]" value="1" <?= $notifyNewOrder ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="notify_new_order">Нові замовлення</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="notify_order_status" name="notification_settings[order_status]" value="1" <?= $notifyOrderStatus ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="notify_order_status">Зміна статусу замовлень</label>
                                </div>
                            <?php endif; ?>

                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="notify_task_status" name="notification_settings[task_status]" value="1" <?= $notifyTaskStatus ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_task_status">Зміна статусу моїх задач</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="notify_comments" name="notification_settings[comments]" value="1" <?= $notifyComments ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_comments">Нові коментарі та згадки</label>
                            </div>
                        </div>
                    </div>

                    <?php
                    $settings = json_decode($user['notification_settings'] ?? '{}', true);
                    ?>
                    <hr class="my-4">
                    <h6 class="mb-3">Налаштування сповіщень (Telegram)</h6>
                    <div class="mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="notify_new_order" name="notification_settings[new_order]" value="1" <?= ($settings['new_order'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_new_order">Нові замовлення</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="notify_task_status" name="notification_settings[task_status]" value="1" <?= ($settings['task_status'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_task_status">Зміна статусу моїх задач/замовлень</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notify_comments" name="notification_settings[comments]" value="1" <?= ($settings['comments'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_comments">Коментарі</label>
                        </div>
                        <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i> Щоб отримувати сповіщення, переконайтесь, що бот підключено.</div>
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-3 text-muted">Зміна паролю</h6>

                    <div class="mb-3">
                        <label for="password" class="form-label">Новий пароль</label>
                        <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="Залиште порожнім, якщо не змінюєте" autocomplete="new-password">
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?= $errors['password'] ?></div>
                        <?php endif; ?>
                        <div class="form-text">Мінімум 6 символів. Залиште порожнім, щоб не змінювати.</div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Зберегти зміни
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/views/layout/footer.php'; ?>