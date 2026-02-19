<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Mercado Livre Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f3f4f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-card {
            max-width: 500px;
            width: 100%;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .card-header {
            background: transparent;
            border-bottom: none;
            padding: 2rem 2rem 0;
            text-align: center;
        }
        .card-body {
            padding: 2rem;
        }
        .logo {
            font-size: 2rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="register-card card bg-white">
        <div class="card-header">
            <div class="logo">
                <i class="bi bi-box-seam"></i>
            </div>
            <h4 class="fw-bold">Criar Nova Conta</h4>
            <p class="text-muted">Preencha os dados abaixo para começar</p>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error'] ?>
                    <?php unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="/auth/register" method="POST">
                <input type="hidden" name="_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                <div class="mb-3">
                    <label for="name" class="form-label">Nome Completo</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="Seu nome">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="seu@email.com">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Senha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Mínimo 8 caracteres">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password_confirm" class="form-label">Confirmar Senha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required placeholder="Confirme a senha">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Criar Conta</button>
                    <a href="/login" class="btn btn-outline-secondary">Já tenho uma conta</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
        // Simple client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const pass = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            
            if (pass !== confirm) {
                e.preventDefault();
                alert('As senhas não coincidem!');
            }
        });
    </script>
</body>
</html>
