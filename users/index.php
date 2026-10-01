<?php
/**
 * DetalStudio - Users Management
 * Управління користувачами
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireRole('director');

$pageTitle = 'Користувачі';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="card-header-actions">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-gear me-1"></i> Управління користувачами
                    </div>
                    <a href="/users/add.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Додати користувача
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead>
                            <tr>
                                <th>Ім'я</th>
                                <th>Email</th>
                                <th>Роль</th>
                                <th>Статус</th>
                                <th class="text-end">Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $users = Auth::getAllUsers();
                            foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($user['username']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <?php
                                            $badges = [
                                                'director' => 'bg-danger',
                                                'manager' => 'bg-primary',
                                                'mechanic' => 'bg-info',
                                                'washer' => 'bg-secondary',
                                                'executor' => 'bg-dark-accent'
                                            ];
                                            $badgeClass = $badges[$user['role']] ?? 'bg-dark-accent';
                                            $roleName = Auth::getRoleLabel($user['role']);
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= $roleName ?></span>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                                <span class="badge bg-success">Активний</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Неактивний</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <a href="/users/edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Редагувати">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($user['id'] != Auth::getUserId()): ?>
                                                    <?php if ($user['is_active']): ?>
                                                        <form id="deleteUserForm_<?= $user['id'] ?>" action="/users/delete.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                            <?= CSRF::input() ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Деактивувати" onclick="confirmDelete('Ви впевнені, що хочете деактивувати цього користувача?', () => document.getElementById('deleteUserForm_<?= $user['id'] ?>').submit())">
                                                                <i class="bi bi-person-x"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form id="activateUserForm_<?= $user['id'] ?>" action="/users/activate.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                            <?= CSRF::input() ?>
                                                            <button type="button" class="btn btn-sm btn-outline-success" title="Активувати" onclick="showConfirm({
                                                                title: 'Активація користувача',
                                                                message: 'Активувати цього користувача?',
                                                                confirmText: 'Активувати',
                                                                confirmButtonClass: 'btn-success',
                                                                onConfirm: () => document.getElementById('activateUserForm_<?= $user['id'] ?>').submit()
                                                            })">
                                                                <i class="bi bi-person-check"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <form id="hardDeleteUserForm_<?= $user['id'] ?>" action="/users/hard_delete.php" method="POST" class="d-inline ms-1">
                                                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                            <?= CSRF::input() ?>
                                                            <button type="button" class="btn btn-sm btn-danger" title="Видалити назавжди" onclick="showConfirm({
                                                                title: 'ПОВНЕ ВИДАЛЕННЯ',
                                                                message: 'Ви впевнені? Це дію неможливо скасувати! Користувача буде повністю видалено з бази даних.',
                                                                confirmText: 'ВИДАЛИТИ НАЗАВЖДИ',
                                                                confirmButtonClass: 'btn-danger',
                                                                onConfirm: () => document.getElementById('hardDeleteUserForm_<?= $user['id'] ?>').submit()
                                                            })">
                                                                <i class="bi bi-trash-fill"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>