<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro 500 - Mercado Livre Manager</title>
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
        .error-card {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <i class="bi bi-exclamation-triangle display-1 text-danger"></i>
        <h1 class="mt-3">Erro 500</h1>
        <p class="text-muted">Ocorreu um erro interno no servidor.</p>
        <p>Nossa equipe foi notificada e está trabalhando para resolver o problema.</p>
        <a href="/dashboard" class="btn btn-primary mt-3">
            <i class="bi bi-house"></i> Voltar ao Dashboard
        </a>
    </div>
</body>
</html>
