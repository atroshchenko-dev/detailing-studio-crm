<?php
/**
 * DetalStudio - Edit User
 * Редагування користувача
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireRole('director');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('/users/');
}

// Check if user exists
$stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    Flash::error('Користувача не знайдено');
    redirect('/users/');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $validator = new Validator($_POST);
    $validator->required('username', 'Ім\'я користувача')
              ->required('email', 'Email')
              ->email('email', 'Email')
              ->required('role', 'Роль')
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
            $role = trim($_POST['role']);


            $updateData = [
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'full_name' => trim($_POST['full_name']),
                'phone' => trim($_POST['phone'] ?? ''),
                'telegram_username' => trim($_POST['telegram_username'] ?? ''),
                'password' => !empty($_POST['password']) ? $_POST['password'] : null
            ];
            
            if (Auth::updateUser($id, $updateData)) {
                Flash::success('Дані користувача оновлено');
                // Refresh user data
                $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
            } else {
                Flash::error('Помилка при оновленні користувача');
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

$pageTitle = 'Редагувати користувача';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil me-2"></i>Редагувати користувача
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
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Новий пароль (необов'язково)</label>
                        <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="Залиште порожнім, якщо не змінюєте">
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?= $errors['password'] ?></div>
                        <?php endif; ?>
                        <div class="form-text">Мінімум 6 символів, якщо змінюєте</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Роль *</label>
                        <select class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>" id="role" name="role" required>
                             <option value="director" <?= $user['role'] == 'director' ? 'selected' : '' ?>>Директор (Повний доступ)</option>
                            <option value="manager" <?= $user['role'] == 'manager' ? 'selected' : '' ?>>Менеджер (Управління замовленнями)</option>
                            <option value="executor" <?= $user['role'] == 'executor' ? 'selected' : '' ?>>Виконавець (Перегляд та виконання завдань)</option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <div class="invalid-feedback"><?= $errors['role'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telegram_username" class="form-label">Telegram @Username</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control" id="telegram_username" name="telegram_username" placeholder="nick" value="<?= htmlspecialchars($user['telegram_username'] ?? '') ?>">
                            </div>
                            <div class="form-text">Нікнейм в Телеграм (без @)</div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Зберегти зміни
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
