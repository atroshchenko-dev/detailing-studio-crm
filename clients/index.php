<?php
/**
 * DetalStudio - Clients List
 * Список клієнтів
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$pageTitle = 'Клієнти';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-header-actions justify-content-end">
                    <a href="/clients/add.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Додати клієнта
                    </a>
                </div>
            </div>
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead>
                            <tr>
                                <th>Ім'я</th>
                                <th>Телефон</th>
                                <th>Email</th>
                                <th>Автомобілі</th>
                                <th class="text-end">Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $clients_list = Client::getAll();
                            foreach ($clients_list as $client): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-white"><?= htmlspecialchars($client['name']) ?></div>
                                            <small class="text-muted">ID: <?= $client['id'] ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($client['phone']) ?></td>
                                        <td><?= htmlspecialchars($client['email'] ?? '-') ?></td>
                                        <td>
                                            <?php 
                                            // Fallback if cars_count is not in the query yet
                                            $cars_count = $client['cars_count'] ?? count(Car::getByClient($client['id']));
                                            if ($cars_count > 0): 
                                            ?>
                                                <span class="badge bg-info text-dark"><?= $cars_count ?> авто</span>
                                            <?php else: ?>
                                                <span class="text-muted small">Немає авто</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <a href="/clients/view.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-light" title="Переглянути">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="/clients/edit.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-info" title="Редагувати">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form action="/clients/delete.php" method="POST" class="d-inline" onsubmit="return confirmDelete('Ви впевнені, що хочете видалити цього клієнта?', () => this.submit());">
                                                    <input type="hidden" name="id" value="<?= $client['id'] ?>">
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