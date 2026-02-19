<?php
/**
 * Standard Page Header Partial
 * Variables:
 * - $title (string): Page title
 * - $subtitle (string, optional): Page description
 * - $actions (string, optional): HTML for action buttons
 * - $breadcrumbs (array, optional): Array of ['label' => '', 'url' => '']
 */
?>
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <?php if (!empty($breadcrumbs)): ?>
            <nav aria-label="breadcrumb" class="mb-1">
                <ol class="breadcrumb breadcrumb-sm mb-0">
                    <li class="breadcrumb-item"><a href="/dashboard" class="text-muted"><i class="bi bi-house"></i></a></li>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <?php if (empty($crumb['url'])): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= $crumb['label'] ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?= $crumb['url'] ?>" class="text-decoration-none"><?= $crumb['label'] ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>
        
        <h4 class="mb-1 fw-bold text-gradient"><?= $title ?? 'Dashboard' ?></h4>
        <?php if (!empty($subtitle)): ?>
            <p class="text-muted mb-0 small"><?= $subtitle ?></p>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($actions)): ?>
        <div class="page-actions d-flex gap-2">
            <?= $actions ?>
        </div>
    <?php endif; ?>
</div>
