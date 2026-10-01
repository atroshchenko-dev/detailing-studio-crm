<?php

/**
 * Standalone Validator Test Runner
 * Useful for shared hosting without SSH or PHPUnit
 */

require_once __DIR__ . '/../config/config.php';
// If config.php doesn't load autoloader, we might need to require Validator manually
// But usually config.php or index.php setup autoloading. 
// Just in case, let's try to load Validator directly if class not found.
if (!class_exists('App\Validator')) {
    // Attempt to match structure
    // If Validator is in includes/Validator.php
    $path = __DIR__ . '/../includes/Validator.php';
    if (file_exists($path)) {
        require_once $path;
    }
}

use App\Validator;

header('Content-Type: text/plain; charset=utf-8');

echo "=== Running Validator Tests ===\n\n";

$tests = [
    'test_required' => function () {
        $v = new Validator(['name' => '']);
        $v->required('name', 'Name');
        if ($v->isValid()) return "FAIL: Empty required field passed";
        if (empty($v->getErrors()['name'])) return "FAIL: No error message for empty required field";

        $v2 = new Validator(['name' => 'Ok']);
        $v2->required('name', 'Name');
        if (!$v2->isValid()) return "FAIL: Valid required field failed";

        return "PASS";
    },
    'test_email' => function () {
        $v = new Validator(['email' => 'bad-email']);
        $v->email('email', 'Email');
        if ($v->isValid()) return "FAIL: Invalid email passed";

        $v2 = new Validator(['email' => 'test@example.com']);
        $v2->email('email', 'Email');
        if (!$v2->isValid()) return "FAIL: Valid email failed";

        return "PASS";
    },
    'test_numeric' => function () {
        $v = new Validator(['age' => 'abc']);
        $v->numeric('age', 'Age');
        if ($v->isValid()) return "FAIL: Non-numeric passed";

        $v2 = new Validator(['age' => '123']);
        $v2->numeric('age', 'Age');
        if (!$v2->isValid()) return "FAIL: Numeric failed";

        return "PASS";
    }
];

$passed = 0;
$total = count($tests);

foreach ($tests as $name => $test) {
    try {
        $result = $test();
        echo "TEST [$name]: $result\n";
        if ($result === "PASS") $passed++;
    } catch (Throwable $e) {
        echo "TEST [$name]: EXCEPTION - " . $e->getMessage() . "\n";
    }
}

echo "\nResult: $passed / $total passed.\n";

if ($passed === $total) {
    echo "\n[SUCCESS] Validator is working correctly on this server.";
} else {
    echo "\n[FAILURE] Some tests failed. Check file integrity.";
}
