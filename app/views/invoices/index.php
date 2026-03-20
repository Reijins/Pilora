<?php
declare(strict_types=1);
// Variables: $permissionDenied, $invoices, $statusLabels, $statusFilter, $canMarkPaid, $canExport

use Core\Support\DateFormatter;
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Factures</h2>
            <p class="muted">Liste des factures (squelette). Conversion possible depuis les devis.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <form method="GET" action="<?= htmlspecialchars($basePath . '/invoices', ENT_QUOTES, 'UTF-8') ?>" class="search-bar">
                    <select class="input" name="status">
                        <option value="">Tous les statuts</option>
                        <?php foreach ($statusLabels as $code => $label): ?>
                            <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= ($statusFilter === $code) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit">Filtrer</button>
                    <?php if (!empty($canExport)): ?>
                        <a
                            class="btn btn-secondary"
                            href="<?= htmlspecialchars($basePath . '/invoices/export?status=' . urlencode((string) ($statusFilter ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                        >Exporter CSV</a>
                    <?php endif; ?>
                </form>

                <div style="height:12px;"></div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Titre</th>
                                <th>Échéance</th>
                                <th>Statut</th>
                                <th>Montant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($invoices)): ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <?php
                                        $code = (string) ($inv['status'] ?? '');
                                        $label = $statusLabels[$code] ?? $code;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($inv['invoiceNumber'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($inv['title'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(DateFormatter::frDate(isset($inv['dueDate']) ? (string) $inv['dueDate'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="badge"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars((string) ($inv['amountTotal'] ?? '0'), ENT_QUOTES, 'UTF-8') ?> €</td>
                                        <td>
                                            <?php
                                                $remaining = (float) ($inv['amountRemaining'] ?? 0);
                                                if ($remaining < 0) { $remaining = 0; }
                                                $hasRemaining = $remaining > 0;
                                            ?>
                                            <?php if (!empty($canMarkPaid) && !empty($inv['id']) && $hasRemaining): ?>
                                                <a class="link-action" href="<?= htmlspecialchars($basePath . '/payments/new?invoiceId=' . (int) ($inv['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Payer</a>
                                            <?php else: ?>
                                                <span class="muted"><?= $hasRemaining ? '—' : 'Payée' ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="muted">Aucune facture.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

