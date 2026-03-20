<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$rows = $auditRows ?? [];
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Journal d’audit</h2>
            <p class="muted"><a href="<?= htmlspecialchars($basePath . '/platform/companies', ENT_QUOTES, 'UTF-8') ?>">← Sociétés</a></p>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Acteur</th>
                            <th>Société (contexte)</th>
                            <th>Cible</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($r['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><code><?= htmlspecialchars((string) ($r['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td><?= htmlspecialchars((string) ($r['actorEmail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($r['companyName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($r['targetCompanyName'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($r['ipAddress'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="muted">Aucune entrée (ou table non migrée).</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
