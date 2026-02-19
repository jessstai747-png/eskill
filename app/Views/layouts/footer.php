<?php
/**
 * Footer layout - Rodapé HTML padrão
 */
?>
    <!-- Bootstrap JS -->
    <script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- CSRF Token Helper -->
    <script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
        window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        // Helper para requisições fetch com CSRF
        window.fetchWithCsrf = async (url, options = {}) => {
            options.headers = {
                ...options.headers,
                'X-CSRF-TOKEN': window.csrfToken,
                'Content-Type': 'application/json',
            };
            return fetch(url, options);
        };
    </script>
    
    <!-- Custom JS -->
    <script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>" src="/js/app.js"></script>
</body>
</html>
