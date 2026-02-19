<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manutenção - Mercado Livre Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .maintenance-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
        }
        .maintenance-icon {
            font-size: 5rem;
            color: #ffc107;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <i class="bi bi-tools maintenance-icon"></i>
        <h1>Manutenção em Andamento</h1>
        <p class="text-muted mt-3">
            Estamos realizando uma manutenção programada para melhorar nossos serviços.
        </p>
        <p class="text-muted">
            O sistema estará disponível em breve. Agradecemos sua compreensão.
        </p>
        <div class="mt-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
        <p class="text-muted mt-3 small">
            Se você é administrador, verifique o arquivo <code>storage/maintenance.lock</code>
        </p>
    </div>
</body>
</html>
