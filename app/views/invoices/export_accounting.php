<?php
declare(strict_types=1);

use Core\Support\DateFormatter;

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$rows = is_array($exportableInvoices ?? null) ? $exportableInvoices : [];
$onlyPending = !empty($onlyPending);
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Export comptable (CSV)</h2>
            <p class="muted">Sélectionnez les factures à inclure dans le fichier d’écritures (statuts envoyée / partiellement payée / payée / échue).</p>
        </div>
        <div class="card-body">
            <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashError), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <p style="margin:0 0 12px;">
                <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/invoices/export-accounting?only_pending=' . ($onlyPending ? '0' : '1'), ENT_QUOTES, 'UTF-8') ?>">
                    <?= $onlyPending ? 'Afficher aussi les factures déjà exportées' : 'N’afficher que les non exportées' ?>
                </a>
            </p>

            <?php if ($rows === []): ?>
                <p class="muted">Aucune facture éligible pour cet export.</p>
            <?php else: ?>
                <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/export-accounting', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="only_pending" value="<?= $onlyPending ? '1' : '0' ?>">

                    <div class="table-wrap" style="margin-bottom:14px;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:40px;"><span class="muted">#</span></th>
                                    <th>N°</th>
                                    <th>Titre</th>
                                    <th>Date échéance</th>
                                    <th>Statut</th>
                                    <th>Montant TTC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $inv): ?>
                                    <?php $rid = (int) ($inv['id'] ?? 0); ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="invoice_id[]" value="<?= $rid ?>" checked>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($inv['invoiceNumber'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($inv['title'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(DateFormatter::frDate(isset($inv['dueDate']) ? (string) $inv['dueDate'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($inv['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(number_format((float) ($inv['amountTotal'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <label class="checkbox-item" style="padding:0; margin:0 0 12px;">
                        <input type="checkbox" name="mark_exported" value="1">
                        <span>Marquer ces factures comme exportées après téléchargement</span>
                    </label>

                    <button class="btn btn-primary" type="submit">Télécharger le CSV</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/invoices', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
