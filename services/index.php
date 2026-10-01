<?php

/**
 * DetalStudio - Services List
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$pageTitle = 'Послуги';

$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == 1;
$services = Service::getAll(!$showInactive);

include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-actions">
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group">
                            <a href="/services/categories.php" class="btn btn-outline-info" title="Категорії">
                                <i class="bi bi-tags"></i>
                            </a>
                            <?php if ($showInactive): ?>
                                <a href="/services/" class="btn btn-outline-secondary active" title="Приховати архівні">
                                    <i class="bi bi-eye-slash-fill me-2"></i>Приховати архів
                                </a>
                            <?php else: ?>
                                <a href="/services/?show_inactive=1" class="btn btn-outline-secondary" title="Показати архівні">
                                    <i class="bi bi-archive me-2"></i>Архів
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="/services/add.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Додати послугу
                    </a>
                </div>
            </div>
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead>
                            <tr>
                                <th>Назва</th>
                                <th>Категорія</th>
                                <th>Ціна</th>
                                <th>Час</th>
                                <th class="text-end">Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr class="<?= !$service['is_active'] ? 'table-secondary text-muted' : '' ?>">
                                    <td>
                                        <div class="fw-bold">
                                            <?= htmlspecialchars($service['name']) ?>
                                            <?php if (!$service['is_active']): ?>
                                                <span class="badge bg-secondary ms-2">Архів</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted d-block mb-1"><?= htmlspecialchars(mb_strimwidth($service['description'] ?? '', 0, 70, '...')) ?></small>

                                        <?php
                                        $custom = Service::getCustomOptions($service['id']);
                                        if (!empty($custom)): ?>
                                            <div class="mt-1">
                                                <?php foreach ($custom as $c): ?>
                                                    <span class="badge bg-dark border border-info text-info small me-1" title="Кастомне доповнення">
                                                        + <?= htmlspecialchars($c['name']) ?> (<?= format_currency($c['price']) ?>)
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $service['color_class'] ?? 'bg-secondary' ?>">
                                            <?= htmlspecialchars($service['category_name'] ?? 'Без категорії') ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold <?= $service['is_active'] ? 'text-info' : '' ?>"><?= format_currency($service['base_price']) ?></td>
                                    <td><i class="bi bi-clock me-1"></i><?= $service['duration_minutes'] ?> хв</td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="/services/edit.php?id=<?= $service['id'] ?>" class="btn btn-sm btn-outline-info" title="Редагувати">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <?php if ($service['is_active']): ?>
                                                <form id="deactivateService_<?= $service['id'] ?>" action="/services/status.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="id" value="<?= $service['id'] ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <?= CSRF::input() ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Деактивувати (Архівувати)" onclick="showConfirm({
                                                        title: 'Деактивація послуги',
                                                        message: 'Приховати цю послугу? Вона більше не буде доступна для вибору в нових замовленнях.',
                                                        confirmText: 'Приховати',
                                                        confirmButtonClass: 'btn-warning',
                                                        onConfirm: () => document.getElementById('deactivateService_<?= $service['id'] ?>').submit()
                                                    })">
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form id="activateService_<?= $service['id'] ?>" action="/services/status.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="id" value="<?= $service['id'] ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <?= CSRF::input() ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="Активувати" onclick="showConfirm({
                                                        title: 'Активація послуги',
                                                        message: 'Повернути послугу в активні?',
                                                        confirmText: 'Активувати',
                                                        confirmButtonClass: 'btn-success',
                                                        onConfirm: () => document.getElementById('activateService_<?= $service['id'] ?>').submit()
                                                    })">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form action="/services/delete.php" method="POST" class="d-inline" onsubmit="return confirmDelete('Ви впевнені, що хочете видалити цю послугу? УВАГА: Якщо послуга використовувалася в замовленнях, видалення неможливе - використовуйте Архів.', () => this.submit());">
                                                <input type="hidden" name="id" value="<?= $service['id'] ?>">
                                                <?= CSRF::input() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Видалити">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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