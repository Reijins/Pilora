<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$categories = is_array($categories ?? null) ? $categories : [];
?>
<section class="page">
    <div class="card">
        <div class="card-header card-header-with-back">
            <a
                class="link-back"
                href="<?= htmlspecialchars($basePath . '/price-library', ENT_QUOTES, 'UTF-8') ?>"
                aria-label="Retour à la bibliothèque"
                title="Retour à la bibliothèque"
            >
                <svg class="link-back__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
            <div class="card-header-with-back__main">
                <h2>Nouvelle prestation</h2>
                <p class="muted">Ajoutez une ligne réutilisable depuis vos devis.</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom:16px;"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($basePath . '/price-library/create', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="name">Nom</label>
                <input class="input" id="name" name="name" type="text" required autocomplete="off">

                <label class="label" for="description">Description</label>
                <input class="input" id="description" name="description" type="text" autocomplete="off">

                <label class="label" for="unit_label">Unité</label>
                <input class="input" id="unit_label" name="unit_label" type="text" placeholder="Ex: m²" autocomplete="off">

                <label class="label" for="unit_price">Prix unitaire (€)</label>
                <input class="input" id="unit_price" name="unit_price" type="number" step="0.01" min="0" value="0" required>

                <label class="label" for="category_id">Catégorie</label>
                <select class="input" id="category_id" name="category_id">
                    <option value="0">Aucune catégorie</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) ($cat['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="label" for="default_vat_rate">TVA par défaut (%)</label>
                <input class="input" id="default_vat_rate" name="default_vat_rate" type="number" step="0.01" min="0" max="100" placeholder="Ex. 20 — vide = société">
                <p class="muted" style="margin:4px 0 0;font-size:13px;">Optionnel: si vide, la valeur de la catégorie sera utilisée, sinon celle de la société.</p>

                <label class="label" for="default_revenue_account">Compte de vente (numéro)</label>
                <input class="input" id="default_revenue_account" name="default_revenue_account" type="text" maxlength="32" placeholder="70600000">
                <p class="muted" style="margin:4px 0 0;font-size:13px;">Optionnel: si vide, le compte de la catégorie s'applique.</p>

                <label class="label" for="estimated_time_hours">Temps estimé (heures)</label>
                <input class="input" id="estimated_time_hours" name="estimated_time_hours" type="number" min="0" step="0.01" placeholder="Ex. 1,5">
                <p class="muted" style="margin:6px 0 0;font-size:13px;">Vous pouvez saisir des décimales (ex. 0,25 pour 15 min). Laisser vide si non applicable.</p>

                <label class="label" for="status">Statut</label>
                <select class="input" id="status" name="status">
                    <option value="active">Actif</option>
                    <option value="inactive">Inactif</option>
                </select>

                <div style="margin-top:20px; display:flex; flex-wrap:wrap; gap:12px;">
                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/price-library', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>
