<?php
/**
 * Class Aliases for Backward Compatibility
 * Allows using classes without full namespace in view files
 */

use App\Auth;
use App\Audit;
use App\Database;
use App\Logger;
use App\Reports;
use App\Validator;
use App\Flash;
use App\Csrf;
use App\Models\Order;
use App\Models\Task;
use App\Models\Client;
use App\Models\Car;
use App\Models\Service;
use App\Models\WarehouseItem;
use App\Models\Stall;
use App\Models\User;

// Core Library Aliases
$coreAliases = [
    'App\Auth' => 'Auth',
    'App\Audit' => 'Audit',
    'App\Database' => 'Database',
    'App\Logger' => 'Logger',
    'App\Reports' => 'Reports',
    'App\Validator' => 'Validator',
    'App\Flash' => 'Flash',
    'App\Csrf' => 'Csrf',
    'App\Telegram' => 'Telegram'
];

foreach ($coreAliases as $class => $alias) {
    if (class_exists($class) && !class_exists($alias)) {
        class_alias($class, $alias);
    }
}

// Models - Manual Inclusion & Aliasing (Most Robust)
$models = [
    'Order', 'Task', 'Client', 'Car', 'Service', 'WarehouseItem', 
    'Stall', 'Expense', 'PurchaseRequest', 'ServiceCategory', 
    'Supplier', 'Setting'
];

foreach ($models as $modelName) {
    $file = __DIR__ . "/Models/$modelName.php";
    if (file_exists($file)) {
        require_once $file;
        $fullClass = "App\Models\\$modelName";
        if (!class_exists($modelName, false)) {
            class_alias($fullClass, $modelName);
        }
    }
}

// Core Component: Database
if (file_exists(__DIR__ . "/Database.php")) {
    require_once __DIR__ . "/Database.php";
    if (!class_exists('Database', false)) {
        class_alias('App\Database', 'Database');
    }
}
