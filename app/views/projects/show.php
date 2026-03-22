<?php
declare(strict_types=1);

use Core\Support\DateFormatter;
// Variables: $permissionDenied, $project, $kpiQuoteAmount, $kpiInvoiceAmount, $kpiPaidAmount, $kpiRemainingAmount, $quotesCount, $quotes, $activeQuote, $quoteVersions, $quoteVersionIndex, $quoteItemsByQuoteId, $invoicesCount, $invoices, $reports, $photos

$projectStatusCode = (string) ($project['status'] ?? '');
$projectNotesRaw = (string) ($project['notes'] ?? '');
$projectStatusLabel = $projectStatusCode !== '' ? $projectStatusCode : '—';
if (str_contains($projectNotesRaw, '[STATUS:WAITING_PLANNING]')) {
    $projectStatusLabel = 'En attente de planification';
} elseif (str_contains($projectNotesRaw, '[STATUS:PLANNED]')) {
    $projectStatusLabel = 'Planifié';
} elseif ($projectStatusCode === 'planned') {
    $projectStatusLabel = 'Prévu';
} elseif ($projectStatusCode === 'in_progress') {
    $projectStatusLabel = 'En cours';
} elseif ($projectStatusCode === 'paused') {
    $projectStatusLabel = 'En pause';
} elseif ($projectStatusCode === 'completed') {
    $projectStatusLabel = 'Terminé';
}
?>
<section class="page">
    <div class="card">
        <div class="card-header sheet-header">
            <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
            <div class="sheet-headline">
                <div>
                    <h2 class="sheet-title"><?= htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="sheet-meta">
                        <span>Client : <strong><?= htmlspecialchars((string) ($project['clientName'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong></span>
                        <span class="chip chip-primary"><?= htmlspecialchars($projectStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients/show?clientId=' . (int) ($project['clientId'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Retour</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php if (is_string($flashMessage ?? null) && trim((string) $flashMessage) !== ''): ?>
                    <div class="alert alert-success" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div class="kpi-grid" style="margin-bottom:14px;">
                    <div class="kpi kpi-tint-1">
                        <div class="kpi-value"><?= htmlspecialchars(number_format((float) ($kpiQuoteAmount ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                        <div class="kpi-label">Total devis liés au client</div>
                    </div>
                    <div class="kpi kpi-tint-2">
                        <div class="kpi-value"><?= htmlspecialchars(number_format((float) ($kpiInvoiceAmount ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                        <div class="kpi-label">Total facturé</div>
                    </div>
                    <div class="kpi kpi-tint-3">
                        <div class="kpi-value"><?= htmlspecialchars(number_format((float) ($kpiPaidAmount ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                        <div class="kpi-label">Total encaissé</div>
                    </div>
                    <div class="kpi kpi-tint-4">
                        <div class="kpi-value"><?= htmlspecialchars(number_format((float) ($kpiRemainingAmount ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                        <div class="kpi-label">Reste à encaisser</div>
                    </div>
                </div>

                <div class="kv-grid" style="margin-bottom:14px;">
                    <div class="kv">
                        <div class="kv-label">Début prévu</div>
                        <div class="kv-value"><?= htmlspecialchars(DateFormatter::frDate(isset($project['plannedStartDate']) ? (string) $project['plannedStartDate'] : null), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="kv">
                        <div class="kv-label">Fin prévue</div>
                        <div class="kv-value"><?= htmlspecialchars(DateFormatter::frDate(isset($project['plannedEndDate']) ? (string) $project['plannedEndDate'] : null), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="kv">
                        <div class="kv-label">Nombre de devis</div>
                        <div class="kv-value"><?= (int) ($quotesCount ?? 0) ?></div>
                    </div>
                    <div class="kv">
                        <div class="kv-label">Nombre de factures</div>
                        <div class="kv-value"><?= (int) ($invoicesCount ?? 0) ?></div>
                    </div>
                    <div class="kv">
                        <div class="kv-label">Adresse chantier</div>
                        <div class="kv-value"><?= htmlspecialchars((string) (($project['siteAddress'] ?? '') !== '' ? $project['siteAddress'] : '—'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="kv">
                        <div class="kv-label">Ville / CP</div>
                        <div class="kv-value">
                            <?php
                                $city = trim((string) ($project['siteCity'] ?? ''));
                                $postal = trim((string) ($project['sitePostalCode'] ?? ''));
                                $cityPostal = trim($postal . ' ' . $city);
                            ?>
                            <?= htmlspecialchars($cityPostal !== '' ? $cityPostal : '—', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>

                <div class="sheet-stack">
                    <div class="section-card">
                        <h3 class="section-title">Devis et prestations</h3>
                        <div class="section-content quote-block">
                            <?php if (!empty($activeQuote)): ?>
                                <?php
                                    $activeQid = (int) ($activeQuote['id'] ?? 0);
                                    $activeItems = is_array($quoteItemsByQuoteId[$activeQid] ?? null) ? $quoteItemsByQuoteId[$activeQid] : [];
                                    $qTotal = 0.0;
                                    $qTotalMinutes = 0;
                                    foreach ($activeItems as $it2) {
                                        $qTotal += (float) ($it2['lineTotal'] ?? 0);
                                        $lineMinutes = is_numeric($it2['estimatedTimeMinutes'] ?? null) ? (int) $it2['estimatedTimeMinutes'] : 0;
                                        $lineQty = (float) ($it2['quantity'] ?? 0);
                                        if ($lineMinutes > 0 && $lineQty > 0) {
                                            $qTotalMinutes += (int) round($lineMinutes * $lineQty);
                                        }
                                    }
                                    $vatRate = is_numeric($vatRate ?? null) ? (float) $vatRate : 20.0;
                                    $qTotalVat = round($qTotal * ($vatRate / 100), 2);
                                    $qTotalTtc = round($qTotal + $qTotalVat, 2);
                                    $qTotalHours = $qTotalMinutes > 0 ? ($qTotalMinutes / 60.0) : 0.0;
                                    $versions = is_array($quoteVersions ?? null) ? $quoteVersions : [];
                                    $vIndex = (int) ($quoteVersionIndex ?? 0);
                                    $vCount = count($versions);
                                    $isFinal = $vCount > 0 && $vIndex === ($vCount - 1);
                                    $prevVersion = $vIndex > 0 ? $versions[$vIndex - 1] : null;
                                    $nextVersion = ($vIndex + 1) < $vCount ? $versions[$vIndex + 1] : null;
                                    $activeQuoteStatus = (string) ($activeQuote['status'] ?? '');
                                    $hasAcceptedQuote = false;
                                    foreach ($versions as $vq) {
                                        if ((string) ($vq['status'] ?? '') === 'accepte') {
                                            $hasAcceptedQuote = true;
                                            break;
                                        }
                                    }
                                    $canSendThisQuote = !$hasAcceptedQuote && in_array($activeQuoteStatus, ['brouillon', 'envoye'], true);
                                    $canValidateThisQuote = !$hasAcceptedQuote && $activeQuoteStatus === 'envoye';
                                    $isWaitingPlanning = str_contains($projectNotesRaw, '[STATUS:WAITING_PLANNING]');
                                ?>
                                <div class="quote-entry">
                                    <div class="quote-entry-section">
                                        <div class="quote-entry-row">
                                            <div class="quote-card-title-wrap">
                                                <h4 class="quote-card-title"><?= htmlspecialchars((string) ($activeQuote['title'] ?? ('Devis #' . $activeQid)), ENT_QUOTES, 'UTF-8') ?></h4>
                                                <div class="quote-card-meta">
                                                    <span class="muted">N° <?= htmlspecialchars((string) ($activeQuote['quoteNumber'] ?? ('DEV-' . $activeQid)), ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php
                                                        $quoteStatusCode = (string) ($activeQuote['status'] ?? '');
                                                        $quoteStatusClass = 'status-pill status-pill-neutral';
                                                        if ($quoteStatusCode === 'brouillon') $quoteStatusClass = 'status-pill status-pill-draft';
                                                        elseif ($quoteStatusCode === 'envoye') $quoteStatusClass = 'status-pill status-pill-sent';
                                                        elseif ($quoteStatusCode === 'accepte') $quoteStatusClass = 'status-pill status-pill-accepted';
                                                        elseif ($quoteStatusCode === 'refuse') $quoteStatusClass = 'status-pill status-pill-refused';
                                                    ?>
                                                    <?php
                                                        $statusLabelMap = ['brouillon' => 'Brouillon', 'envoye' => 'Envoyé', 'accepte' => 'Accepté', 'refuse' => 'Refusé'];
                                                        $statusLabel = $statusLabelMap[$quoteStatusCode] ?? ($quoteStatusCode !== '' ? $quoteStatusCode : '—');
                                                    ?>
                                                    <span class="<?= htmlspecialchars($quoteStatusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="status-pill">Version <?= $vCount > 0 ? ($vIndex + 1) : 1 ?>/<?= max(1, $vCount) ?></span>
                                                    <?php if ($isFinal): ?>
                                                        <span class="status-pill">Version finale</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="quote-version-nav">
                                                <?php if (is_array($prevVersion)): ?>
                                                    <a class="btn btn-secondary btn-icon" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . (int) ($project['id'] ?? 0) . '&quoteVersionId=' . (int) ($prevVersion['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" title="Version précédente" aria-label="Version précédente">
                                                        <span aria-hidden="true">&#8592;</span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="btn btn-secondary btn-icon quote-nav-disabled"><span aria-hidden="true">&#8592;</span></span>
                                                <?php endif; ?>
                                                <?php if (is_array($nextVersion)): ?>
                                                    <a class="btn btn-secondary btn-icon" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . (int) ($project['id'] ?? 0) . '&quoteVersionId=' . (int) ($nextVersion['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" title="Version suivante" aria-label="Version suivante">
                                                        <span aria-hidden="true">&#8594;</span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="btn btn-secondary btn-icon quote-nav-disabled"><span aria-hidden="true">&#8594;</span></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="table-wrap">
                                            <table class="table quote-items-table">
                                                <thead>
                                                    <tr>
                                                        <th>Prestation</th>
                                                        <th>Qté</th>
                                                        <th class="col-unit">Unité</th>
                                                        <th>Prix unitaire</th>
                                                        <th>Total ligne</th>
                                                        <th>Temps (h)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($activeItems)): ?>
                                                        <?php foreach ($activeItems as $it): ?>
                                                            <?php $uLab = trim((string) ($it['unitLabel'] ?? '')); ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars((string) ($it['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><?= htmlspecialchars((string) number_format((float) ($it['quantity'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="col-unit"><?= $uLab !== '' ? htmlspecialchars($uLab, ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                                                <td><?= htmlspecialchars((string) number_format((float) ($it['unitPrice'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                                <td><?= htmlspecialchars((string) number_format((float) ($it['lineTotal'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                                                <td><?php
                                                                    $lineM = is_numeric($it['estimatedTimeMinutes'] ?? null) ? (int) $it['estimatedTimeMinutes'] : 0;
                                                                    echo $lineM > 0
                                                                        ? htmlspecialchars(number_format($lineM / 60.0, 2, ',', ' '), ENT_QUOTES, 'UTF-8')
                                                                        : '—';
                                                                ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="6" class="muted quote-empty">Aucune prestation sur ce devis.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="quote-summary">
                                            <div class="quote-summary-item">
                                                <span class="quote-summary-label">Total HT</span>
                                                <strong class="quote-summary-value"><?= htmlspecialchars((string) number_format($qTotal, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</strong>
                                            </div>
                                            <div class="quote-summary-item">
                                                <span class="quote-summary-label">TVA (<?= htmlspecialchars((string) number_format($vatRate, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> %)</span>
                                                <strong class="quote-summary-value"><?= htmlspecialchars((string) number_format($qTotalVat, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</strong>
                                            </div>
                                            <div class="quote-summary-item">
                                                <span class="quote-summary-label">Total TTC</span>
                                                <strong class="quote-summary-value"><?= htmlspecialchars((string) number_format($qTotalTtc, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</strong>
                                            </div>
                                            <div class="quote-summary-item">
                                                <span class="quote-summary-label">Total temps</span>
                                                <strong class="quote-summary-value"><?= $qTotalMinutes > 0 ? htmlspecialchars(number_format($qTotalHours, 2, ',', ' '), ENT_QUOTES, 'UTF-8') . ' h' : '—' ?></strong>
                                            </div>
                                        </div>

                                        <?php if (!empty($canCreateQuote)): ?>
                                            <div style="padding:10px 12px 12px;">
                                                <div class="inline-actions inline-actions--quote-footer">
                                                    <?php if (!$hasAcceptedQuote && $activeQuoteStatus !== 'accepte'): ?>
                                                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/quotes/version/new?projectId=' . (int) ($project['id'] ?? 0) . '&sourceQuoteId=' . $activeQid, ENT_QUOTES, 'UTF-8') ?>">
                                                            Modifier ce devis (nouvelle version)
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($canSendQuote) && $canSendThisQuote): ?>
                                                        <form method="POST" action="<?= htmlspecialchars($basePath . '/projects/quotes/send', ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="project_id" value="<?= (int) ($project['id'] ?? 0) ?>">
                                                            <input type="hidden" name="quote_id" value="<?= $activeQid ?>">
                                                            <button class="btn btn-primary" type="submit">Envoyer</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if (!empty($activeQuote['proofFilePath'] ?? null)): ?>
                                                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . (string) $activeQuote['proofFilePath'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Preuve de commande</a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($canSendQuote) && $canValidateThisQuote): ?>
                                                        <form class="quote-validate-form" method="POST" action="<?= htmlspecialchars($basePath . '/projects/quotes/validate', ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="project_id" value="<?= (int) ($project['id'] ?? 0) ?>">
                                                            <input type="hidden" name="quote_id" value="<?= $activeQid ?>">
                                                            <div class="quote-validate-form__fields">
                                                                <div class="quote-validate-form__group">
                                                                    <label class="label" for="validate_invoice_due_date">Échéance facture</label>
                                                                    <input class="input" id="validate_invoice_due_date" name="invoice_due_date" type="date" value="<?= htmlspecialchars((string) ($defaultInvoiceDueDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                </div>
                                                                <?php if (!empty($proofRequired)): ?>
                                                                    <div class="quote-validate-form__group quote-validate-form__group--file">
                                                                        <label class="label" for="validate_proof_document">Preuve (PDF ou image)</label>
                                                                        <input class="input" id="validate_proof_document" type="file" name="proof_document" accept=".pdf,application/pdf,image/*" required>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="quote-validate-form__submit">
                                                                <button class="btn btn-validate" type="submit">Valider</button>
                                                            </div>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($hasAcceptedQuote): ?>
                                                        <span class="muted" style="align-self:center;">Un devis est accepté : la création de nouvelle version est verrouillée.</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="quote-empty-state">
                                    <p class="muted">Aucun devis lié à cette affaire.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="section-card">
                        <h3 class="section-title">Factures</h3>
                        <div class="section-content">
                            <?php if (!empty($invoices)): ?>
                                <div style="display:grid; gap:10px;">
                                    <?php foreach ($invoices as $inv): ?>
                                        <?php
                                            $invStatus = (string) ($inv['status'] ?? '');
                                            $invStatusLabel = match ($invStatus) {
                                                'brouillon' => 'Brouillon',
                                                'envoyee' => 'Envoyée',
                                                'partiellement_payee' => 'Partiellement payée',
                                                'payee' => 'Payée',
                                                'echue' => 'Échue',
                                                'annulee' => 'Annulée',
                                                default => ($invStatus !== '' ? $invStatus : '—'),
                                            };
                                            $invRemaining = (float) ($inv['amountRemaining'] ?? 0);
                                            if ($invRemaining < 0) {
                                                $invRemaining = 0.0;
                                            }
                                            $invBadgeClass = 'inv-badge inv-badge--' . preg_replace('/[^a-z_]/', '', $invStatus);
                                        ?>
                                        <div style="border:1px solid #dbe7ef; border-radius:12px; padding:12px; background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);">
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                                                <div>
                                                    <div style="font-weight:800; margin-bottom:4px;">
                                                        <?= htmlspecialchars((string) ($inv['invoiceNumber'] ?? ('FA-' . (int) ($inv['id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                    <div class="muted" style="font-size:12px;"><?= htmlspecialchars((string) ($inv['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                                </div>
                                                <span class="<?= htmlspecialchars($invBadgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($invStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div style="display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:10px; margin-top:10px;">
                                                <div class="kv" style="margin:0;">
                                                    <div class="kv-label">Montant TTC</div>
                                                    <div class="kv-value"><?= htmlspecialchars(number_format((float) ($inv['amountTotal'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                                                </div>
                                                <div class="kv" style="margin:0;">
                                                    <div class="kv-label">Reste à payer</div>
                                                    <div class="kv-value"><?= htmlspecialchars(number_format($invRemaining > 0 ? $invRemaining : 0, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                                                </div>
                                                <div class="kv" style="margin:0;">
                                                    <div class="kv-label">Échéance</div>
                                                    <div class="kv-value"><?= htmlspecialchars(DateFormatter::frDate(isset($inv['dueDate']) ? (string) $inv['dueDate'] : null), ENT_QUOTES, 'UTF-8') ?></div>
                                                </div>
                                            </div>
                                            <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; align-items:center;">
                                                <?php if (!empty($inv['paymentToken'])): ?>
                                                    <a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= htmlspecialchars($basePath . '/invoice/pay?token=' . urlencode((string) $inv['paymentToken']), ENT_QUOTES, 'UTF-8') ?>">Voir / payer en ligne</a>
                                                <?php endif; ?>
                                                <?php if (!empty($canInvoiceSend) && $invStatus === 'brouillon'): ?>
                                                    <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/send', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="project_id" value="<?= (int) ($project['id'] ?? 0) ?>">
                                                        <input type="hidden" name="invoice_id" value="<?= (int) ($inv['id'] ?? 0) ?>">
                                                        <button class="btn btn-primary" type="submit">Envoyer au client</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (!empty($canInvoiceSend) && in_array($invStatus, ['envoyee', 'partiellement_payee', 'echue'], true)): ?>
                                                    <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/resend', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="project_id" value="<?= (int) ($project['id'] ?? 0) ?>">
                                                        <input type="hidden" name="invoice_id" value="<?= (int) ($inv['id'] ?? 0) ?>">
                                                        <button class="btn btn-secondary" type="submit">Renvoyer la facture par e-mail</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (!empty($canInvoiceMarkPaid) && $invStatus !== 'annulee' && $invStatus !== 'payee' && $invRemaining > 0.009): ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-secondary js-open-invoice-payment"
                                                        data-invoice-id="<?= (int) ($inv['id'] ?? 0) ?>"
                                                        data-project-id="<?= (int) ($project['id'] ?? 0) ?>"
                                                        data-remaining="<?= htmlspecialchars((string) round($invRemaining, 2), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-label="<?= htmlspecialchars((string) ($inv['invoiceNumber'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    >Ajouter un paiement</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="muted">Aucune facture générée pour cette affaire.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($canPlanningCreate) && !empty($activeQuote) && !empty($hasAcceptedQuote) && !empty($isWaitingPlanning)): ?>
                        <div class="section-card" style="margin-top:12px;">
                            <h4 class="section-title" style="margin-bottom:8px;">Planifier l'affaire</h4>
                            <div class="section-content">
                                <form method="POST" action="<?= htmlspecialchars($basePath . '/projects/planify', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:none;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="project_id" value="<?= (int) ($project['id'] ?? 0) ?>">
                                    <div class="settings-grid" style="grid-template-columns: repeat(2, minmax(0,1fr));">
                                        <div>
                                            <label class="label" for="planned_start_date">Date de début</label>
                                            <input class="input" id="planned_start_date" name="planned_start_date" type="date" required value="<?= htmlspecialchars((string) ($project['plannedStartDate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                        <div>
                                            <label class="label" for="planned_end_date">Date de fin</label>
                                            <input class="input" id="planned_end_date" name="planned_end_date" type="date" required value="<?= htmlspecialchars((string) ($project['plannedEndDate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                    <label class="label" for="site_address">Adresse (précision chantier)</label>
                                    <input class="input" id="site_address" name="site_address" type="text" value="<?= htmlspecialchars((string) ($project['siteAddress'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="settings-grid" style="grid-template-columns: repeat(2, minmax(0,1fr));">
                                        <div>
                                            <label class="label" for="site_city">Ville</label>
                                            <input class="input" id="site_city" name="site_city" type="text" value="<?= htmlspecialchars((string) ($project['siteCity'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                        <div>
                                            <label class="label" for="site_postal_code">Code postal</label>
                                            <input class="input" id="site_postal_code" name="site_postal_code" type="text" value="<?= htmlspecialchars((string) ($project['sitePostalCode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                    <button class="btn btn-primary" type="submit">Planifier</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div id="invoice-payment-modal" class="status-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="invoice-payment-title">
                        <div class="status-modal" style="max-width:420px;">
                            <div class="status-modal-header">
                                <h3 class="status-modal-title" id="invoice-payment-title">Enregistrer un paiement</h3>
                                <button type="button" class="btn btn-secondary btn-icon js-close-invoice-payment" aria-label="Fermer">×</button>
                            </div>
                            <p class="status-modal-subtitle" id="invoice-payment-sub">Saisissez le montant reçu (virement, espèces, chèque).</p>
                            <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/payment/manual', ENT_QUOTES, 'UTF-8') ?>" class="status-modal-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="project_id" id="invoice-pay-project-id" value="">
                                <input type="hidden" name="invoice_id" id="invoice-pay-invoice-id" value="">
                                <label class="label" for="invoice-pay-amount">Montant TTC (€)</label>
                                <input class="input" type="number" name="amount" id="invoice-pay-amount" step="0.01" min="0.01" required placeholder="0,00">
                                <p class="muted" style="margin:0;font-size:12px;">Reste à payer : <strong id="invoice-pay-remaining">—</strong> € — le montant ne peut pas le dépasser.</p>
                                <div class="status-reason-actions">
                                    <button type="button" class="btn btn-secondary js-close-invoice-payment">Annuler</button>
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <script>
                    (function () {
                        var modal = document.getElementById('invoice-payment-modal');
                        var amt = document.getElementById('invoice-pay-amount');
                        var remEl = document.getElementById('invoice-pay-remaining');
                        var sub = document.getElementById('invoice-payment-sub');
                        if (!modal || !amt) return;
                        function closeM() {
                            modal.style.display = 'none';
                        }
                        document.querySelectorAll('.js-open-invoice-payment').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var rem = parseFloat(String(btn.getAttribute('data-remaining') || '0').replace(',', '.')) || 0;
                                document.getElementById('invoice-pay-project-id').value = btn.getAttribute('data-project-id') || '';
                                document.getElementById('invoice-pay-invoice-id').value = btn.getAttribute('data-invoice-id') || '';
                                remEl.textContent = rem.toFixed(2).replace('.', ',');
                                amt.max = rem > 0 ? rem.toFixed(2) : '';
                                amt.value = '';
                                var lab = btn.getAttribute('data-label') || '';
                                if (sub) sub.textContent = lab ? ('Facture ' + lab + ' — reste ' + rem.toFixed(2).replace('.', ',') + ' €') : 'Saisissez le montant reçu.';
                                modal.style.display = 'flex';
                                amt.focus();
                            });
                        });
                        document.querySelectorAll('.js-close-invoice-payment').forEach(function (b) {
                            b.addEventListener('click', closeM);
                        });
                        modal.addEventListener('click', function (e) {
                            if (e.target === modal) closeM();
                        });
                    })();
                    </script>

                    <div class="settings-grid">
                        <div class="section-card">
                            <div class="section-head">
                                <h3 class="section-title">Derniers rapports</h3>
                                <?php if (!empty($canReportCreate)): ?>
                                    <a class="btn btn-secondary" style="padding:4px 10px; min-height:30px;" href="<?= htmlspecialchars($basePath . '/project-reports?projectId=' . (int) ($project['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">+</a>
                                <?php endif; ?>
                            </div>
                            <div class="section-content">
                                <?php if (empty($canReportRead)): ?>
                                    <div class="alert alert-danger">Accès refusé.</div>
                                <?php elseif (!empty($reports)): ?>
                                    <div style="display:grid; gap:10px;">
                                        <?php foreach ($reports as $r): ?>
                                            <div style="border:1px solid #dbe7ef; border-radius:12px; padding:10px 12px; background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);">
                                                <div class="muted" style="font-size:12px; margin-bottom:4px;"><?= htmlspecialchars(DateFormatter::frDateTime(isset($r['createdAt']) ? (string) $r['createdAt'] : null), ENT_QUOTES, 'UTF-8') ?></div>
                                                <div style="font-weight:700;"><?= htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="muted">Aucun rapport.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="section-card">
                            <div class="section-head">
                                <h3 class="section-title">Dernières photos</h3>
                                <?php if (!empty($canPhotoUpload)): ?>
                                    <a class="btn btn-secondary" style="padding:4px 10px; min-height:30px;" href="<?= htmlspecialchars($basePath . '/project-photos?projectId=' . (int) ($project['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">+</a>
                                <?php endif; ?>
                            </div>
                            <div class="section-content">
                                <?php if (empty($canPhotoRead)): ?>
                                    <div class="alert alert-danger">Accès refusé.</div>
                                <?php elseif (!empty($photos)): ?>
                                    <div style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px;">
                                        <?php foreach ($photos as $p): ?>
                                            <?php
                                                $photoSrc = $basePath . (string) ($p['filePath'] ?? '');
                                                $photoCaption = (string) ($p['caption'] ?? ('Photo #' . (int) ($p['id'] ?? 0)));
                                            ?>
                                            <a
                                                href="<?= htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8') ?>"
                                                class="project-photo-thumb"
                                                data-full="<?= htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8') ?>"
                                                data-caption="<?= htmlspecialchars($photoCaption, ENT_QUOTES, 'UTF-8') ?>"
                                                style="display:block; border:1px solid #dbe7ef; border-radius:12px; overflow:hidden; text-decoration:none; color:inherit; background:#fff;"
                                            >
                                                <div style="aspect-ratio: 4 / 3; background:#f1f5f9;">
                                                    <img src="<?= htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($photoCaption, ENT_QUOTES, 'UTF-8') ?>" style="width:100%; height:100%; object-fit:cover;">
                                                </div>
                                                <div style="padding:8px 10px; font-size:12px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($photoCaption, ENT_QUOTES, 'UTF-8') ?></div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                    <div id="project-photo-lightbox" style="display:none; position:fixed; inset:0; background:rgba(2,6,23,.82); z-index:1000; padding:20px; align-items:center; justify-content:center;">
                                        <div style="max-width:90vw; max-height:90vh; display:flex; flex-direction:column; gap:8px;">
                                            <img id="project-photo-lightbox-image" src="" alt="Photo agrandie" style="max-width:90vw; max-height:82vh; object-fit:contain; border-radius:10px;">
                                            <div id="project-photo-lightbox-caption" style="color:#e2e8f0; font-size:13px;"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="muted">Aucune photo.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<script>
    (function () {
        var thumbs = document.querySelectorAll('.project-photo-thumb[data-full]');
        var lightbox = document.getElementById('project-photo-lightbox');
        var lightboxImage = document.getElementById('project-photo-lightbox-image');
        var lightboxCaption = document.getElementById('project-photo-lightbox-caption');
        if (!thumbs.length || !lightbox || !lightboxImage || !lightboxCaption) {
            return;
        }

        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function (event) {
                event.preventDefault();
                var full = thumb.getAttribute('data-full') || '';
                var caption = thumb.getAttribute('data-caption') || '';
                lightboxImage.setAttribute('src', full);
                lightboxCaption.textContent = caption;
                lightbox.style.display = 'flex';
            });
        });

        lightbox.addEventListener('click', function () {
            lightbox.style.display = 'none';
            lightboxImage.setAttribute('src', '');
            lightboxCaption.textContent = '';
        });
    })();
</script>

