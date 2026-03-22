<?php
declare(strict_types=1);
// Variables: permissionDenied, canRequest, canApprove, csrfToken, leaveRequests, flashMessage, flashError

use Core\Support\DateFormatter;
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>RH</h2>
            <p class="muted">Congés et absences (squelette fonctionnel).</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                        <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>

                <?php if (!empty($canRequest)): ?>
                    <div style="margin:0 0 14px;">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/hr/leave/new', ENT_QUOTES, 'UTF-8') ?>">Nouvelle demande</a>
                    </div>
                <?php endif; ?>

                <h3 style="margin:0 0 10px;">Demandes</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Collaborateur</th>
                                <th>Type</th>
                                <th>Période</th>
                                <th>Statut</th>
                                <th>Motif</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($leaveRequests)): ?>
                                <?php foreach ($leaveRequests as $lr): ?>
                                    <?php
                                        $statusCode = (string) ($lr['status'] ?? '');
                                        $statusLabelMap = [
                                            'pending' => 'En attente',
                                            'approved' => 'Approuvée',
                                            'rejected' => 'Refusée',
                                            'cancelled' => 'Annulée',
                                        ];
                                        $statusLabel = $statusLabelMap[$statusCode] ?? $statusCode;
                                        $typeCode = (string) ($lr['type'] ?? '');
                                        $typeLabel = $typeCode === 'conges' ? 'Congés' : ($typeCode === 'absence' ? 'Absence' : $typeCode);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($lr['userName'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($typeLabel !== '' ? $typeLabel : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?= htmlspecialchars(DateFormatter::frDate(isset($lr['startDate']) ? (string) $lr['startDate'] : null), ENT_QUOTES, 'UTF-8') ?>
                                            -
                                            <?= htmlspecialchars(DateFormatter::frDate(isset($lr['endDate']) ? (string) $lr['endDate'] : null), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><span class="badge"><?= htmlspecialchars($statusLabel !== '' ? $statusLabel : '—', ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars((string) ($lr['reason'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if (!empty($canApprove) && (string) ($lr['status'] ?? '') === 'pending'): ?>
                                                <form method="POST" action="<?= htmlspecialchars($basePath . '/hr/leave/approve', ENT_QUOTES, 'UTF-8') ?>" style="display:flex; gap:8px; flex-wrap:wrap;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="leave_request_id" value="<?= (int) ($lr['id'] ?? 0) ?>">
                                                    <button class="btn btn-primary" name="status" value="approved" type="submit">Approuver</button>
                                                    <button class="btn btn-secondary" name="status" value="rejected" type="submit">Refuser</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="muted">Aucune demande.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

