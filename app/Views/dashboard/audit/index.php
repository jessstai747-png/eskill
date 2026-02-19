<?php
$title = 'Trilha de Auditoria';
$subtitle = 'Logs de segurança e alterações no sistema';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';

// Prepare Logs for Display (passed from Controller as $logs)
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Registros Recentes</h5>
        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Recurso</th>
                        <th>IP</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-shield-check fs-1 d-block mb-3"></i>
                            Nenhum registro encontrado.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="ps-4 text-nowrap"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-1 me-2" style="width: 30px; height: 30px; text-align: center;">
                                        <i class="bi bi-person text-secondary"></i>
                                    </div>
                                    <div>
                                        <span class="d-block fw-bold"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                                        <small class="text-muted">ID: <?= $log['user_id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $badgeClass = match($log['action']) {
                                    'LOGIN' => 'bg-success',
                                    'DELETE' => 'bg-danger',
                                    'UPDATE' => 'bg-warning text-dark',
                                    default => 'bg-info'
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= $log['action'] ?></span>
                            </td>
                            <td><?= htmlspecialchars($log['resource']) ?></td>
                            <td><span class="font-monospace small"><?= $log['ip_address'] ?></span></td>
                            <td>
                                <?php if ($log['details']): ?>
                                    <small><?= htmlspecialchars($log['details']) ?></small>
                                <?php endif; ?>
                                <?php if ($log['old_value'] || $log['new_value']): ?>
                                    <button class="btn btn-link btn-sm p-0 ms-1" title="Ver Alterações">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
