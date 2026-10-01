<?php
/**
 * DetalStudio - Add User
 * Додавання користувача
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireRole('director');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $validator = new Validator($_POST);
    $validator->required('username', 'Ім\'я користувача')
              ->required('email', 'Email')
              ->email('email', 'Email')
              ->required('password', 'Пароль')
              ->minLength('password', 'Пароль', 6)
              ->required('role', 'Роль')
              ->unique('username', 'users', 'username', 'Ім\'я користувача')
              ->unique('email', 'users', 'email', 'Email');
              
    if ($validator->isValid()) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $email = trim($_POST['email']);
        $role = trim($_POST['role']);
        $fullName = trim($_POST['full_name']);
        $phone = trim($_POST['phone'] ?? '');
        $telegramUsername = trim($_POST['telegram_username'] ?? '');
        
        $userId = Auth::createUser([
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'role' => $role,
            'full_name' => $fullName,
            'phone' => $phone,
            'telegram_username' => $telegramUsername
        ]);

        if ($userId) {
            Flash::success('Користувача успішно створено');
            redirect('/users/');
        } else {
            Flash::error('Помилка при створенні користувача');
        }
    } else {
        Flash::error('Будь ласка, виправте помилки');
        $errors = $validator->getErrors();
    }
}

$pageTitle = 'Додати користувача';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-person-plus me-2"></i>Додати користувача
                    </h5>
                    <a href="/users/" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Назад
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <?= CSRF::input() ?>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Повне ім'я *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label">Ім'я користувача (Login) *</label>
                        <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" id="username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback"><?= $errors['username'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?= $errors['email'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Пароль *</label>
                        <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" required>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?= $errors['password'] ?></div>
                        <?php endif; ?>
                        <div class="form-text">Мінімум 6 символів</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telegram_username" class="form-label">Telegram @Username</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control" id="telegram_username" name="telegram_username" placeholder="nick" value="<?= htmlspecialchars($_POST['telegram_username'] ?? '') ?>">
                            </div>
                            <div class="form-text">Нікнейм в Телеграм (без @)</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Роль *</label>
                        <select class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>" id="role" name="role" required>
                            <option value="">Оберіть роль...</option>
                            <option value="director" <?= ($_POST['role'] ?? '') == 'director' ? 'selected' : '' ?>>Директор (Повний доступ)</option>
                            <option value="manager" <?= ($_POST['role'] ?? '') == 'manager' ? 'selected' : '' ?>>Менеджер (Управління замовленнями)</option>
                            <option value="executor" <?= ($_POST['role'] ?? '') == 'executor' ? 'selected' : '' ?>>Виконавець (Перегляд та виконання завдань)</option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <div class="invalid-feedback"><?= $errors['role'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Створити
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
