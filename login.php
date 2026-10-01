<?php
/**
 * DetalStudio - Login Page
 * Сторінка входу в систему
 */

require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    redirect('/index.php');
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'Будь ласка, заповніть всі поля';
    } else {
        if (Auth::login($username, $password, isset($_POST['remember']))) {
            redirect('/index.php');
        } else {
            $error = 'Невірний логін або пароль';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вхід - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= CACHE_VERSION ?>">
    <style>
        body {
            background-color: var(--primary-bg);
            background-image: radial-gradient(circle at 50% 50%, #111 0%, #000 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-wrapper {
            max-width: 400px;
            width: 100%;
            padding: 20px;
        }
        .form-card {
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
            margin-bottom: 30px;
            position: relative;
            text-align: center;
        }
        .form-card::before {
            display: none;
        }
        .form-label {
            color: var(--accent-cyan);
            text-transform: uppercase;
            font-weight: 700;
            font-size: 11px;
            letter-spacing: 1px;
            margin-bottom: 5px;
            display: block;
        }
        .input-group {
            max-width: 300px;
            margin: 0 auto;
        }
        .input-group-text {
            background-color: var(--secondary-bg);
            border-color: var(--border-color);
            color: var(--accent-cyan);
        }
        .custom-btn {
            color: #eee;
            border: 1px solid #444;
            background: rgba(255,255,255,0.05);
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-radius: 20px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            padding: 10px 30px;
        }
        .custom-btn:hover {
            background: rgba(255,255,255,0.1);
            border-color: var(--accent-cyan);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(31, 190, 228, 0.2);
        }
    </style>
</head>
<body>
    <div class="login-wrapper fade-in">
        <div class="login-logo">
            <div class="logo-text" style="font-weight: 700; font-size: 1.5rem; letter-spacing: 0.04em;">DetalStudio</div>
        </div>
        
        <div class="form-card">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="LOGIN" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="PASSWORD" required>
                    </div>
                </div>
                
                <div class="mb-3 d-flex justify-content-center">
                    <div class="form-check text-start" style="width: 300px;">
                        <input class="form-check-input bg-dark border-secondary" type="checkbox" name="remember" id="rememberMe">
                        <label class="form-check-label text-muted small" for="rememberMe" style="cursor: pointer; letter-spacing: 0.5px;">
                            Запам'ятати мене
                        </label>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="custom-btn">
                        Увійти
                    </button>
                </div>
            </form>
        </div>

        <div class="text-center mb-4">
            <a href="/tracking/" class="custom-btn">
                <i class="bi bi-search me-1"></i> Відстежити замовлення
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>