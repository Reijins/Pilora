<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$item = is_array($item ?? null) ? $item : [];
$id = (int) ($item['id'] ?? 0);
$estMin = isset($item['estimatedTimeMinutes']) && $item['estimatedTimeMinutes'] !== null && $item['estimatedTimeMinutes'] !== ''
    ? (int) $item['estimatedTimeMinutes']
    : null;
$hoursDisplay = '';
if ($estMin !== null) {
    $h = $estMin / 60.0;
    $hoursDisplay = rtrim(rtrim(number_format($h, 4, '.', ''), '0'), '.');
    if ($hoursDisplay === '') {
        $hoursDisplay = '0';
    }
}
$st = (string) ($item['status'] ?? 'active');
$defVatDisp = '';
if (isset($item['defaultVatRate']) && is_numeric($item['defaultVatRate'])) {
    $defVatDisp = rtrim(rtrim(number_format((float) $item['defaultVatRate'], 2, '.', ''), '0'), '.');
    if ($defVatDisp === '') {
        $defVatDisp = '0';
    }
}
$returnSub = $st === 'inactive' ? 'inactive' : 'active';
$backHref = $basePath . '/price-library?sub=' . rawurlencode($returnSub);
?>
<section class="page">
    <div class="card">
        <div class="card-header card-header-with-back">
            <a
                class="link-back"
                href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>"
                aria-label="Retour à la bibliothèque"
                title="Retour à la bibliothèque"
            >
                <svg class="link-back__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
            <div class="card-header-with-back__main">
                <h2>Modifier la prestation</h2>
                <p class="muted">Mettez à jour les informations ou passez la ligne en inactif.</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom:16px;"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($basePath . '/price-library/update', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="return_sub" value="<?= htmlspecialchars($returnSub, ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="name">Nom</label>
                <input class="input" id="name" name="name" type="text" required autocomplete="off" value="<?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="description">Description</label>
                <input class="input" id="description" name="description" type="text" autocomplete="off" value="<?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="unit_label">Unité</label>
                <input class="input" id="unit_label" name="unit_label" type="text" placeholder="Ex: m²" autocomplete="off" value="<?= htmlspecialchars((string) ($item['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="unit_price">Prix unitaire (€)</label>
                <input class="input" id="unit_price" name="unit_price" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string) ($item['unitPrice'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>" required>

                <label class="label" for="default_vat_rate">TVA par défaut (%)</label>
                <input class="input" id="default_vat_rate" name="default_vat_rate" type="number" step="0.01" min="0" max="100" placeholder="Vide = aucune suggestion" value="<?= htmlspecialchars($defVatDisp, ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="default_revenue_account">Compte de vente (numéro)</label>
                <input class="input" id="default_revenue_account" name="default_revenue_account" type="text" maxlength="32" value="<?= htmlspecialchars(trim((string) ($item['defaultRevenueAccount'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="estimated_time_hours">Temps estimé (heures)</label>
                <input class="input" id="estimated_time_hours" name="estimated_time_hours" type="number" min="0" step="0.01" placeholder="Ex. 1,5" value="<?= htmlspecialchars($hoursDisplay, ENT_QUOTES, 'UTF-8') ?>">
                <p class="muted" style="margin:6px 0 0;font-size:13px;">Décimales autorisées (ex. 0,25 h = 15 min). Vide = aucun temps estimé.</p>

                <label class="label" for="status">Statut</label>
                <select class="input" id="status" name="status">
                    <option value="active" <?= $st !== 'inactive' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactive" <?= $st === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                </select>
                <p class="muted" style="margin:6px 0 0;">Les prestations inactives n’apparaissent plus dans les devis ; vous pourrez les supprimer définitivement depuis l’onglet « Inactives ».</p>

                <div style="margin-top:20px; display:flex; flex-wrap:wrap; gap:12px;">
                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>
