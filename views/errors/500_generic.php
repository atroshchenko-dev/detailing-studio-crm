<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Внутрішня помилка | DetalStudio</title>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0a0c10;
            --card-dark: #12151c;
            --primary: #1fade4;
            --danger: #ff4d4d;
            --text-main: #e2e8f0;
            --text-dim: #94a3b8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .error-container {
            position: relative;
            text-align: center;
            padding: 3rem;
            background: var(--card-dark);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 500px;
            width: 90%;
            z-index: 10;
        }

        .error-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, transparent, rgba(31, 173, 228, 0.3), transparent);
            border-radius: 26px;
            z-index: -1;
        }

        .glow-circle {
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(31, 173, 228, 0.15) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
            pointer-events: none;
        }

        h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: 8rem;
            margin: 0;
            line-height: 0.8;
            background: linear-gradient(to bottom, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
            letter-spacing: -2px;
        }

        .status-badge {
            display: inline-block;
            background: rgba(255, 77, 77, 0.1);
            color: var(--danger);
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 77, 77, 0.2);
        }

        h2 {
            font-family: 'Rajdhani', sans-serif;
            font-size: 2rem;
            margin: 1rem 0 0.5rem;
            color: #fff;
        }

        p {
            color: var(--text-dim);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            background: var(--primary);
            color: #000;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 1px;
        }

        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(31, 173, 228, 0.4);
            filter: brightness(1.1);
        }

        .decor-line {
            width: 40px;
            height: 2px;
            background: var(--primary);
            margin: 0 auto 2rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="glow-circle"></div>
    <div class="error-container">
        <div class="status-badge">System Failure</div>
        <h1>500</h1>
        <div class="decor-line"></div>
        <h2>Ого! Щось пішло не так</h2>
        <p>
            Ми зіткнулися з внутрішньою помилкою сервера. <br>
            Наша команда вже отримала сповіщення та займається вирішенням проблеми.
        </p>
        <a href="/" class="btn-home">Повернутися до роботи</a>
    </div>
</body>
</html>
