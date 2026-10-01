<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Сторінку не знайдено | DetalStudio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f131a;
            --accent-orange: #ff6b35;
            --accent-cyan: #00d2ff;
        }
        body {
            background-color: var(--bg-dark);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow: hidden;
        }
        .error-container {
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .error-code {
            font-size: 10rem;
            font-weight: 900;
            line-height: 1;
            margin: 0;
            background: linear-gradient(45deg, var(--accent-orange), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            opacity: 0.2;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: -1;
        }
        .error-icon {
            font-size: 4rem;
            color: var(--accent-orange);
            margin-bottom: 1rem;
        }
        .btn-premium {
            background: linear-gradient(45deg, var(--accent-orange), #ff9e7d);
            border: none;
            color: #fff;
            padding: 12px 35px;
            border-radius: 50px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 10px 20px rgba(255, 107, 53, 0.3);
        }
        .btn-premium:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 30px rgba(255, 107, 53, 0.4);
            color: #fff;
        }
        .glow {
            position: absolute;
            width: 300px;
            height: 300px;
            background: var(--accent-orange);
            filter: blur(150px);
            opacity: 0.1;
            border-radius: 50%;
            z-index: -2;
        }
    </style>
</head>
<body>
    <div class="glow"></div>
    <div class="error-container p-4">
        <div class="error-code">404</div>
        <div class="error-icon">
            <i class="bi bi-compass"></i>
        </div>
        <h1 class="fw-bold mb-3">ЗБИЛИСЯ З КУРСУ?</h1>
        <p class="text-muted fs-5 mb-5">Сторінку, яку ви шукаєте, не вдалося знайти і очистити до блиску.</p>
        <a href="/" class="btn-premium">
            <i class="bi bi-house-door me-2"></i>Повернутися на базу
        </a>
    </div>
</body>
</html>