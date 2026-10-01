<?php
/**
 * DetalStudio - Suppliers List
 * Список постачальників
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$pageTitle = 'Постачальники';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-people me-2"></i>Постачальники
                </h5>
                <div>
                     <a href="/warehouse/" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left me-2"></i>Назад до складу
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                        <i class="bi bi-plus-lg me-2"></i>Додати постачальника
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead>
                            <tr>
                                <th>Назва</th>
                                <th>Контактна особа</th>
                                <th>Телефон</th>
                                <th>Email / Адреса</th>
                                <th>Примітка</th>
                                <th class="text-end">Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $suppliers = Supplier::getAll();
                            foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($supplier['name']) ?></td>
                                    <td><?= htmlspecialchars($supplier['contact_person']) ?></td>
                                    <td><?= htmlspecialchars($supplier['phone']) ?></td>
                                    <td>
                                        <?php if ($supplier['email']): ?>
                                            <div><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($supplier['email']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($supplier['address']): ?>
                                            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($supplier['address']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($supplier['notes']) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-info" title="Редагувати" 
                                                    onclick="editSupplier(<?= htmlspecialchars(json_encode($supplier)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
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

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/warehouse/suppliers_save.php" method="POST">
            <?= CSRF::input() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Додати постачальника</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Назва компанії *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Контактна особа</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Адреса</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примітки</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Зберегти</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/warehouse/suppliers_save.php" method="POST">
            <?= CSRF::input() ?>
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редагувати постачальника</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Назва компанії *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Контактна особа</label>
                        <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Адреса</label>
                        <input type="text" name="address" id="edit_address" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примітки</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="submit" class="btn btn-primary">Зберегти</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function editSupplier(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_contact_person').value = data.contact_person || '';
    document.getElementById('edit_phone').value = data.phone || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_address').value = data.address || '';
    document.getElementById('edit_notes').value = data.notes || '';
    
    new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
}
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
