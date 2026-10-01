<?php
/**
 * DetalStudio - Save Supplier
 * Збереження постачальника
 */

require_once __DIR__ . '/../config/config.php';



Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    if (empty($data['name'])) {
        Flash::error('Назва обовʼязкова');
        redirect('/warehouse/suppliers.php');
    }
    
    if ($id) {
        if (Supplier::update($id, $data)) {
            Flash::success('Постачальника оновлено');
        } else {
            Flash::error('Помилка при оновленні');
        }
    } else {
        if (Supplier::create($data)) {
            Flash::success('Постачальника додано');
        } else {
            Flash::error('Помилка при створенні');
        }
    }
    
    redirect('/warehouse/suppliers.php');
}

redirect('/warehouse/suppliers.php');
