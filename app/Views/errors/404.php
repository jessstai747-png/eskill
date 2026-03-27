<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página não encontrada | ML Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --text: #1e293b;
            --text-muted: #64748b;
            --bg: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            overflow: hidden;
        }

        .error-container {
            text-align: center;
            max-width: 500px;
            position: relative;
            z-index: 1;
        }

        /* Animated Background */
        .bg-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        .shape-1 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.08) 0%, rgba(22, 163, 74, 0.08) 100%);
            bottom: -50px;
            left: -50px;
            animation-delay: -5s;
        }

        .shape-3 {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(217, 119, 6, 0.08) 100%);
            top: 50%;
            left: 10%;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-20px) rotate(5deg); }
            50% { transform: translateY(0) rotate(0deg); }
            75% { transform: translateY(20px) rotate(-5deg); }
        }

        /* 404 Animation */
        .error-code {
            font-size: 10rem;
            font-weight: 700;
            line-height: 1;
            background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            display: inline-block;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        .error-code::after {
            content: '';
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            border-radius: 50%;
            filter: blur(15px);
        }

        /* Illustration */
        .error-illustration {
            width: 200px;
            height: 200px;
            margin: 0 auto 2rem;
            position: relative;
        }

        .astronaut {
            position: absolute;
            width: 100%;
            height: 100%;
            animation: float-astronaut 6s ease-in-out infinite;
        }

        @keyframes float-astronaut {
            0%, 100% { transform: translateY(0) rotate(-5deg); }
            50% { transform: translateY(-15px) rotate(5deg); }
        }

        .astronaut svg {
            width: 100%;
            height: 100%;
        }

        /* Content */
        .error-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.75rem;
        }

        .error-description {
            font-size: 1rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Buttons */
        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9375rem;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }

        .btn-secondary {
            background: #fff;
            color: var(--text);
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Search Box */
        .search-box {
            margin-top: 2.5rem;
            position: relative;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .search-box input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.9375rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #fff;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.25rem;
        }

        /* Quick Links */
        .quick-links {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }

        .quick-links-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }

        .quick-links-list {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.2s ease;
        }

        .quick-link:hover {
            color: var(--primary);
        }

        .quick-link i {
            font-size: 1rem;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .error-code {
                font-size: 6rem;
            }

            .error-illustration {
                width: 150px;
                height: 150px;
            }

            .error-title {
                font-size: 1.5rem;
            }

            .error-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f172a;
                --text: #f1f5f9;
                --text-muted: #94a3b8;
            }

            .btn-secondary {
                background: #1e293b;
                border-color: #334155;
            }

            .search-box input {
                background: #1e293b;
                border-color: #334155;
                color: var(--text);
            }

            .quick-links {
                border-color: #334155;
            }
        }
    </style>
</head>
<body>
    <!-- Background Shapes -->
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <div class="error-container">
        <!-- Illustration -->
        <div class="error-illustration">
            <div class="astronaut">
                <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Planet -->
                    <circle cx="100" cy="150" r="40" fill="url(#planetGrad)" opacity="0.3"/>
                    <ellipse cx="100" cy="150" rx="50" ry="8" fill="url(#ringGrad)" opacity="0.2"/>

                    <!-- Astronaut Body -->
                    <ellipse cx="100" cy="85" rx="35" ry="40" fill="#fff" stroke="#e2e8f0" stroke-width="2"/>

                    <!-- Helmet -->
                    <circle cx="100" cy="55" r="30" fill="#fff" stroke="#e2e8f0" stroke-width="2"/>
                    <circle cx="100" cy="55" r="22" fill="url(#visorGrad)"/>

                    <!-- Reflection -->
                    <ellipse cx="93" cy="50" rx="8" ry="5" fill="#fff" opacity="0.6"/>

                    <!-- Arms -->
                    <ellipse cx="60" cy="80" rx="12" ry="8" fill="#fff" stroke="#e2e8f0" stroke-width="2" transform="rotate(-20 60 80)"/>
                    <ellipse cx="140" cy="80" rx="12" ry="8" fill="#fff" stroke="#e2e8f0" stroke-width="2" transform="rotate(20 140 80)"/>

                    <!-- Legs -->
                    <ellipse cx="85" cy="120" rx="10" ry="15" fill="#fff" stroke="#e2e8f0" stroke-width="2"/>
                    <ellipse cx="115" cy="120" rx="10" ry="15" fill="#fff" stroke="#e2e8f0" stroke-width="2"/>

                    <!-- Backpack -->
                    <rect x="120" y="70" width="15" height="25" rx="3" fill="#667eea" opacity="0.8"/>

                    <!-- Stars -->
                    <circle cx="30" cy="30" r="2" fill="#667eea" opacity="0.6"/>
                    <circle cx="170" cy="40" r="1.5" fill="#764ba2" opacity="0.6"/>
                    <circle cx="50" cy="150" r="1" fill="#22c55e" opacity="0.6"/>
                    <circle cx="160" cy="120" r="2" fill="#f59e0b" opacity="0.6"/>
                    <circle cx="20" cy="100" r="1.5" fill="#667eea" opacity="0.6"/>
                    <circle cx="180" cy="80" r="1" fill="#764ba2" opacity="0.6"/>

                    <defs>
                        <linearGradient id="planetGrad" x1="60" y1="110" x2="140" y2="190">
                            <stop offset="0%" stop-color="#667eea"/>
                            <stop offset="100%" stop-color="#764ba2"/>
                        </linearGradient>
                        <linearGradient id="ringGrad" x1="50" y1="150" x2="150" y2="150">
                            <stop offset="0%" stop-color="#667eea" stop-opacity="0"/>
                            <stop offset="50%" stop-color="#667eea"/>
                            <stop offset="100%" stop-color="#667eea" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="visorGrad" x1="78" y1="33" x2="122" y2="77">
                            <stop offset="0%" stop-color="#667eea"/>
                            <stop offset="100%" stop-color="#764ba2"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>

        <!-- Error Code -->
        <div class="error-code">404</div>

        <!-- Content -->
        <h1 class="error-title">Página não encontrada</h1>
        <p class="error-description">
            Ops! Parece que você se perdeu no espaço. A página que você está procurando não existe ou foi movida para outro lugar.
        </p>

        <!-- Actions -->
        <div class="error-actions">
            <a href="/dashboard" class="btn btn-primary">
                <i class="bi bi-house"></i>
                Voltar ao Dashboard
            </a>
            <button onclick="history.back()" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Página Anterior
            </button>
        </div>

        <!-- Search -->
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Buscar no sistema..." id="searchInput">
        </div>

        <!-- Quick Links -->
        <div class="quick-links">
            <p class="quick-links-title">Links Rápidos</p>
            <div class="quick-links-list">
                <a href="/dashboard" class="quick-link">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
                <a href="/dashboard/items" class="quick-link">
                    <i class="bi bi-box-seam"></i>
                    Produtos
                </a>
                <a href="/dashboard/orders" class="quick-link">
                    <i class="bi bi-receipt"></i>
                    Pedidos
                </a>
                <a href="/dashboard/seo-killer" class="quick-link">
                    <i class="bi bi-fire"></i>
                    SEO
                </a>
                <a href="/dashboard/help" class="quick-link">
                    <i class="bi bi-question-circle"></i>
                    Ajuda
                </a>
            </div>
        </div>
    </div>

    <script nonce="<?= CSP_NONCE ?>">
        // Search functionality
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && this.value.trim()) {
                window.location.href = '/dashboard/search?q=' + encodeURIComponent(this.value.trim());
            }
        });

        // Add some interactivity to shapes
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const x = (e.clientX / window.innerWidth - 0.5) * 20;
            const y = (e.clientY / window.innerHeight - 0.5) * 20;

            shapes.forEach((shape, i) => {
                const factor = (i + 1) * 0.5;
                shape.style.transform = `translate(${x * factor}px, ${y * factor}px)`;
            });
        });
    </script>
</body>
</html>
