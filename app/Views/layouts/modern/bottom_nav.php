<!-- Mobile Bottom Navigation -->
<div class="bottom-nav d-md-none fixed-bottom bg-white border-top shadow-lg d-flex justify-content-around align-items-center py-2" style="z-index: 1050; padding-bottom: max(0.5rem, env(safe-area-inset-bottom));">
    <a href="/dashboard" class="text-decoration-none text-center flex-grow-1 <?php echo str_contains($_SERVER['REQUEST_URI'] ?? '', '/dashboard') && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'questions') ? 'text-primary' : 'text-muted'; ?>">
        <i class="bi bi-speedometer2 fs-4"></i>
        <div style="font-size: 0.7rem;">Início</div>
    </a>

    <a href="/dashboard/questions" class="text-decoration-none text-center flex-grow-1 <?php echo str_contains($_SERVER['REQUEST_URI'] ?? '', '/questions') ? 'text-primary' : 'text-muted'; ?>">
        <div class="position-relative d-inline-block">
            <i class="bi bi-chat-dots fs-4"></i>
            <!-- Unanswered Count Badger placeholder -->
            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" id="mobileUnansweredBadge" style="display: none;"></span>
        </div>
        <div style="font-size: 0.7rem;">Perguntas</div>
    </a>

    <a href="/dashboard/orders" class="text-decoration-none text-center flex-grow-1 <?php echo str_contains($_SERVER['REQUEST_URI'] ?? '', '/orders') ? 'text-primary' : 'text-muted'; ?>">
        <i class="bi bi-box-seam fs-4"></i>
        <div style="font-size: 0.7rem;">Vendas</div>
    </a>

    <a href="/dashboard/account-health" class="text-decoration-none text-center flex-grow-1 <?php echo str_contains($_SERVER['REQUEST_URI'] ?? '', '/account-health') ? 'text-primary' : 'text-muted'; ?>">
        <i class="bi bi-heart-pulse fs-4"></i>
        <div style="font-size: 0.7rem;">Diagnóstico</div>
    </a>

    <button class="btn btn-link text-decoration-none text-center flex-grow-1 text-muted" type="button" id="mobileMenuBtn">
        <i class="bi bi-list fs-4"></i>
        <div style="font-size: 0.7rem;">Menu</div>
    </button>
</div>

<script nonce="<?= $cspNonce ?>">
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('open');
        // Also add overlay if needed, or close on outside click logic
    });
    // Close sidebar when clicking a link on mobile
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 768) {
                document.querySelector('.sidebar').classList.remove('open');
            }
        });
    });
</script>
