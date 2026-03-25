<?php
declare(strict_types=1);

use Core\Support\DateFormatter;

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$periodMode = (string) ($periodMode ?? 'month');
$subTab = (string) ($subTab ?? 'a_renseigner');
$pendingCount = (int) ($pendingCount ?? 0);
$periodMonth = substr((string) ($periodStart ?? ''), 0, 7);
$periodYear = substr((string) ($periodStart ?? date('Y-m-d')), 0, 4);
$periodParams = 'period=' . urlencode($periodMode);
if ($periodMode === 'month') {
    $periodParams .= '&month=' . urlencode($periodMonth);
} elseif ($periodMode === 'year') {
    $periodParams .= '&year=' . urlencode($periodYear);
}
?>
<section class="page">
    <div class="card rentability-page">
        <div class="card-header rentability-head">
            <div>
                <h2>Rentabilité des chantiers</h2>
                <p class="muted">Suivi du chiffre d’affaires et du bénéfice sur les affaires terminées.</p>
            </div>
            <a class="btn btn-secondary rentability-head__back" href="<?= htmlspecialchars($basePath . '/projects', ENT_QUOTES, 'UTF-8') ?>">Retour aux affaires</a>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé.</div>
            <?php else: ?>
                <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                    <div class="alert alert-danger rentability-alert"><?= htmlspecialchars(rawurldecode((string) $flashError), ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (is_string($flashMessage ?? null) && trim((string) $flashMessage) !== ''): ?>
                    <div class="alert alert-success rentability-alert"><?= htmlspecialchars(rawurldecode((string) $flashMessage), ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="get" action="<?= htmlspecialchars($basePath . '/projects/rentability', ENT_QUOTES, 'UTF-8') ?>" class="rentability-filters">
                    <input type="hidden" name="sub" value="<?= htmlspecialchars($subTab, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field rentability-filters__field">
                        <label class="label" for="period_mode">Période</label>
                        <select class="input" id="period_mode" name="period" onchange="this.form.submit()">
                            <option value="current" <?= $periodMode === 'current' ? 'selected' : '' ?>>Mois en cours</option>
                            <option value="month" <?= $periodMode === 'month' ? 'selected' : '' ?>>Un mois</option>
                            <option value="year" <?= $periodMode === 'year' ? 'selected' : '' ?>>Une année</option>
                        </select>
                    </div>
                    <?php if ($periodMode === 'month'): ?>
                        <div class="field rentability-filters__field">
                            <label class="label" for="month_p">Mois</label>
                            <input class="input" id="month_p" type="month" name="month" value="<?= htmlspecialchars($periodMonth, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                        </div>
                    <?php endif; ?>
                    <?php if ($periodMode === 'year'): ?>
                        <div class="field rentability-filters__field">
                            <label class="label" for="year_p">Année</label>
                            <input class="input" id="year_p" type="number" name="year" min="2000" max="2100" value="<?= (int) $periodYear ?>" onchange="this.form.submit()">
                        </div>
                    <?php endif; ?>
                </form>

                <div class="kpi-grid rentability-kpi">
                    <div class="kpi kpi-tint-1 rentability-kpi__card">
                        <div class="kpi-value"><?= htmlspecialchars(number_format((float) ($kpiCa ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                        <div class="kpi-label">Chiffre d’affaires (HT facturé)</div>
                    </div>
                    <div class="kpi kpi-tint-2 rentability-kpi__card">
                        <div class="kpi-value"><?= htmlspecialchars(number_format((float) ($kpiBenefice ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</div>
                        <div class="kpi-label">Bénéfice (affaires avec rentabilité renseignée)</div>
                    </div>
                </div>
                <p class="muted rentability-period-note">
                    Période du <strong><?= htmlspecialchars(DateFormatter::frDate((string) ($periodStart ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                    au <strong><?= htmlspecialchars(DateFormatter::frDate((string) ($periodEnd ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                    (date de fin réelle).
                    <?php if ($pendingCount > 0): ?>
                        <span class="rentability-period-note__pending"><?= (int) $pendingCount ?> affaire<?= $pendingCount > 1 ? 's' : '' ?> terminée<?= $pendingCount > 1 ? 's' : '' ?> en attente.</span>
                    <?php endif; ?>
                </p>

                <div class="settings-tabs rentability-tabs">
                    <a class="btn btn-secondary settings-tab rentability-tab <?= $subTab === 'a_renseigner' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/projects/rentability?sub=a_renseigner&' . $periodParams, ENT_QUOTES, 'UTF-8') ?>">
                        À renseigner
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge rentability-tab__badge"><?= (int) $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="btn btn-secondary settings-tab rentability-tab <?= $subTab === 'historique' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/projects/rentability?sub=historique&' . $periodParams, ENT_QUOTES, 'UTF-8') ?>">Historique</a>
                </div>

                <div class="rentability-table-card">
                    <?php if ($subTab === 'a_renseigner'): ?>
                    <div class="table-wrap rentability-table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Affaire</th>
                                    <th>Client</th>
                                    <th>Fin réelle</th>
                                    <th>Montant facturé HT</th>
                                    <th>Matériaux</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($pendingRows ?? []) as $row): ?>
                                    <tr>
                                        <td class="rentability-col-title"><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="rentability-col-client"><?= htmlspecialchars((string) ($row['clientName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(DateFormatter::frDate(isset($row['actualEndDate']) ? (string) $row['actualEndDate'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= isset($row['montantFactureHt']) && is_numeric($row['montantFactureHt']) ? htmlspecialchars(number_format((float) $row['montantFactureHt'], 2, ',', ' '), ENT_QUOTES, 'UTF-8') . ' €' : '—' ?></td>
                                        <td><?= isset($row['coutMateriauxTotal']) && is_numeric($row['coutMateriauxTotal']) ? htmlspecialchars(number_format((float) $row['coutMateriauxTotal'], 2, ',', ' '), ENT_QUOTES, 'UTF-8') . ' €' : '—' ?></td>
                                        <td class="rentability-col-action">
                                            <?php if (!empty($canUpdateProject)): ?>
                                                <a class="link-action" href="<?= htmlspecialchars($basePath . '/projects/rentability/form?projectId=' . (int) ($row['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Saisir la rentabilité</a>
                                            <?php else: ?>
                                                <span class="muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (($pendingRows ?? []) === []): ?>
                                    <tr><td colspan="6" class="muted rentability-empty">Aucune affaire en attente.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="table-wrap rentability-table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Affaire</th>
                                    <th>Client</th>
                                    <th>Fin réelle</th>
                                    <th>Facturé HT</th>
                                    <th>Bénéfice</th>
                                    <th>Marge</th>
                                    <th>Renseigné le</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($historyRows ?? []) as $row): ?>
                                    <tr>
                                        <td class="rentability-col-title"><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="rentability-col-client"><?= htmlspecialchars((string) ($row['clientName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(DateFormatter::frDate(isset($row['actualEndDate']) ? (string) $row['actualEndDate'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= isset($row['montantFactureHt']) && is_numeric($row['montantFactureHt']) ? htmlspecialchars(number_format((float) $row['montantFactureHt'], 2, ',', ' '), ENT_QUOTES, 'UTF-8') . ' €' : '—' ?></td>
                                        <td class="<?= isset($row['beneficeTotal']) && is_numeric($row['beneficeTotal']) && (float) $row['beneficeTotal'] < 0 ? 'rentability-negative' : 'rentability-positive' ?>"><?= isset($row['beneficeTotal']) && is_numeric($row['beneficeTotal']) ? htmlspecialchars(number_format((float) $row['beneficeTotal'], 2, ',', ' '), ENT_QUOTES, 'UTF-8') . ' €' : '—' ?></td>
                                        <td class="<?= isset($row['margePercent']) && is_numeric($row['margePercent']) && (float) $row['margePercent'] < 0 ? 'rentability-negative' : '' ?>"><?= isset($row['margePercent']) && is_numeric($row['margePercent']) ? htmlspecialchars(number_format((float) $row['margePercent'], 2, ',', ' '), ENT_QUOTES, 'UTF-8') . ' %' : '—' ?></td>
                                        <td><?= htmlspecialchars(DateFormatter::frDateTime(isset($row['rentabiliteRenseigneeAt']) ? (string) $row['rentabiliteRenseigneeAt'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="rentability-col-action">
                                            <?php if (!empty($canUpdateProject)): ?>
                                                <a class="link-action" href="<?= htmlspecialchars($basePath . '/projects/rentability/form?projectId=' . (int) ($row['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Voir / modifier</a>
                                            <?php else: ?>
                                                <span class="muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (($historyRows ?? []) === []): ?>
                                    <tr><td colspan="8" class="muted rentability-empty">Aucun historique pour l’instant.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
