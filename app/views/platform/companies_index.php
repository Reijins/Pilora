<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$perms = (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) ? $_SESSION['permissions'] : [];
$canAudit = in_array('platform.audit.read', $perms, true);
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Sociétés</h2>
            <p class="muted">Gestion multi-entreprise (back-office plateforme).</p>
        </div>
        <div class="card-body">
            <p class="muted" style="margin-bottom:12px;">
                Exécutez <code>scripts/migrate_company_billing_audit.php</code> si les colonnes billing / la table <code>AuditLog</code> manquent.
            </p>
            <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/platform/companies/new', ENT_QUOTES, 'UTF-8') ?>">Nouvelle société</a>
                <?php if ($canAudit): ?>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/platform/audit', ENT_QUOTES, 'UTF-8') ?>">Journal d’audit</a>
                <?php endif; ?>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Statut</th>
                            <th>Email facturation</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($companies)): ?>
                            <?php foreach ($companies as $c): ?>
                                <tr>
                                    <td><?= (int) ($c['id'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($c['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($c['billingEmail'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <a class="link-action" href="<?= htmlspecialchars($basePath . '/platform/companies/show?id=' . (int) ($c['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Détails</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="muted">Aucune société.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
