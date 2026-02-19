<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta Tags -->
    <title><?= htmlspecialchars($pageTitle ?? 'Loja Oficial') ?> | Oferta Exclusiva</title>
    <meta name="description" content="<?= htmlspecialchars($seoData['description'] ?? '') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($seoData['url'] ?? '') ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="product">
    <meta property="og:url" content="<?= htmlspecialchars($seoData['url'] ?? '') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seoData['title'] ?? '') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoData['description'] ?? '') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($seoData['image'] ?? '') ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($seoData['url'] ?? '') ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($seoData['title'] ?? '') ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($seoData['description'] ?? '') ?>">
    <meta property="twitter:image" content="<?= htmlspecialchars($seoData['image'] ?? '') ?>">

    <!-- Critical CSS (Inline for Speed) -->
    <style>
        :root {
            --primary: #ffe600;
            --dark: #2d3436;
            --light: #f9f9f9;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--dark);
            text-decoration: none;
        }

        .btn-buy {
            background: #2968c8;
            color: white;
            padding: 15px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            display: inline-block;
            transition: background 0.2s;
        }

        .btn-buy:hover {
            background: #1a4c96;
        }

        footer {
            margin-top: 50px;
            padding: 30px 0;
            background: #fff;
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <a href="/" class="logo">⚡ ML Ofertas</a>
        </div>
    </header>

    <main class="container">
        <?= $content ?? '' ?>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ML Ofertas. Todos os direitos reservados.</p>
    </footer>

    <!-- Schema.org Product Data -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org/",
            "@type": "Product",
            "name": <?= json_encode($seoData['title'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
            "image": <?= json_encode($seoData['image'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
            "description": <?= json_encode($seoData['description'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
            "offers": {
                "@type": "Offer",
                "url": <?= json_encode($seoData['url'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
                "priceCurrency": <?= json_encode($seoData['currency'] ?? 'BRL', JSON_HEX_TAG) ?>,
                "price": <?= json_encode((float)($seoData['price'] ?? 0)) ?>,
                "availability": <?= json_encode('https://schema.org/' . ($seoData['availability'] ?? 'OutOfStock'), JSON_HEX_TAG) ?>
            }
        }
    </script>
</body>

</html>