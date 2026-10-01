<?php
/**
 * DetalStudio - Service Status Toggle
 * Зміна статусу послуги (активна/неактивна)
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();
Auth::requireAnyRole(['manager', 'director']);

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^(activate|deactivate)$/']]);

if (!$id || !$action) {
    redirect('/services/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verify();
    
    $isActive = ($action === 'activate') ? 1 : 0;
    
    // We can use Service::update to set is_active
    if (Service::update($id, ['is_active' => $isActive])) {
        Flash::success($isActive ? 'Послугу активовано' : 'Послугу деактивовано (приховано)');
    } else {
        Flash::error('Помилка при зміні статусу послуги');
    }
}

// Redirect back preserving filters if possible (simple for now)
redirect('/services/' . ($isActive ? '?show_inactive=1' : ''));
