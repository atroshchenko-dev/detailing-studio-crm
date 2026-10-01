<?php
/**
 * DetalStudio - Create Purchase Request
 * Створення заявки на закупівлю
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $data = [
        'supplier_id' => $_POST['supplier_id'] ?? null,
        'notes' => $_POST['notes'] ?? null,
        'items' => []
    ];
    
    $itemIds = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    
    foreach ($itemIds as $index => $itemId) {
        if ($itemId && !empty($quantities[$index])) {
            $data['items'][] = [
                'item_id' => $itemId,
                'quantity' => max(0.01, floatval($quantities[$index])),
                'unit_price' => max(0, floatval($prices[$index] ?? 0))
            ];
        }
    }
    
    if (!$data['supplier_id']) {
        $error = "Будь ласка, виберіть постачальника";
    } elseif (empty($data['items'])) {
        $error = "Будь ласка, додайте хоча б один товар";
    } else {
        $requestId = PurchaseRequest::create($data);
        if ($requestId) {
            header("Location: /purchases/view.php?id=" . $requestId . "&success=1");
            exit;
        } else {
            $error = "Помилка при створенні заявки";
        }
    }
}

$suppliers = Supplier::getAll();
$warehouseItems = WarehouseItem::getAll();

$pageTitle = 'Нова закупівля';
include __DIR__ . '/../views/layout/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-cart-plus me-2"></i>Нова закупівля
                </h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" id="purchaseForm">
                    <?= CSRF::input() ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Постачальник <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select select2" required>
                                <option value="">-- Виберіть постачальника --</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>" <?= (($_POST['supplier_id'] ?? '') == $supplier['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <a href="/warehouse/suppliers.php" target="_blank" class="text-decoration-none">
                                    <i class="bi bi-plus-circle me-1"></i>Додати нового постачальника
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Примітки</label>
                            <textarea name="notes" class="form-control" rows="1"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <h6 class="border-bottom pb-2 mb-3">Товари</h6>
                    <div id="itemsContainer">
                        <div class="item-row mb-3 border-bottom pb-3">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small text-muted">Товар</label>
                                    <select name="item_id[]" class="form-select item-select" required>
                                        <option value="">-- Виберіть товар --</option>
                                        <?php foreach ($warehouseItems as $item): ?>
                                            <option value="<?= $item['id'] ?>" data-price="<?= $item['avg_price'] ?>" data-unit="<?= $item['unit'] ?>">
                                                <?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['sku']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted">Кількість</label>
                                    <div class="input-group">
                                        <input type="number" name="quantity[]" class="form-control qty-input" step="0.01" min="0.01" required>
                                        <span class="input-group-text unit-label">шт</span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted">Ціна за од.</label>
                                    <input type="number" name="price[]" class="form-control price-input" step="0.01" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted">Усього</label>
                                    <input type="text" class="form-control-plaintext fw-bold total-row" readonly value="0.00">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-row" style="display: none;">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addRow">
                            <i class="bi bi-plus-lg me-1"></i>Додати рядок
                        </button>
                    </div>

                    <div class="row justify-content-end">
                        <div class="col-md-4">
                            <div class="card bg-dark-accent p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h6 mb-0">Загальна сума:</span>
                                    <span class="h4 mb-0 text-accent-cyan" id="grandTotal">0.00 ₴</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary px-5">
                            Створити замовлення
                        </button>
                        <a href="/purchases/" class="btn btn-outline-secondary ms-2">Скасувати</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
$additionalScripts = '
<script>
$(document).ready(function() {
    function calculateTotals() {
        let grandTotal = 0;
        $(".item-row").each(function() {
            const qty = parseFloat($(this).find(".qty-input").val()) || 0;
            const price = parseFloat($(this).find(".price-input").val()) || 0;
            const total = qty * price;
            $(this).find(".total-row").val(total.toFixed(2));
            grandTotal += total;
        });
        $("#grandTotal").text(grandTotal.toLocaleString("uk-UA", { minimumFractionDigits: 2 }) + " ₴");
    }

    $(document).on("change", ".item-select", function() {
        const row = $(this).closest(".row");
        const selected = $(this).find("option:selected");
        const price = selected.data("price") || 0;
        const unit = selected.data("unit") || "шт";
        
        row.find(".price-input").val(price);
        row.find(".unit-label").text(unit);
        calculateTotals();
    });

    $(document).on("input", ".qty-input, .price-input", function() {
        calculateTotals();
    });

    $("#addRow").click(function() {
        const newRow = $(".item-row:first").clone();
        newRow.find("input").val("");
        newRow.find(".total-row").val("0.00");
        newRow.find(".unit-label").text("шт");
        newRow.find(".remove-row").show();
        $("#itemsContainer").append(newRow);
    });

    $(document).on("click", ".remove-row", function() {
        $(this).closest(".item-row").remove();
        calculateTotals();
    });
});
</script>
';

include __DIR__ . '/../views/layout/footer.php'; ?>
