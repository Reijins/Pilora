<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
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
                <h2>Créer une catégorie</h2>
                <p class="muted">TVA par défaut et compte comptable par défaut.</p>
            </div>
        </div>

        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom:16px;"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($basePath . '/price-library/category/create', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="name">Nom</label>
                <input class="input" id="name" name="name" type="text" required autocomplete="off">

                <label class="label" for="default_vat_rate">TVA par défaut (%)</label>
                <input class="input" id="default_vat_rate" name="default_vat_rate" type="number" step="0.01" min="0" max="100" value="20" placeholder="Ex: 20 — vide = aucune valeur par défaut">

                <label class="label" for="default_revenue_account">Compte comptable par défaut (numéro)</label>
                <input class="input" id="default_revenue_account" name="default_revenue_account" type="text" maxlength="32" placeholder="70600000">

                <label class="label" for="status">Statut</label>
                <select class="input" id="status" name="status">
                    <option value="active">Actif</option>
                    <option value="inactive">Inactif</option>
                </select>

                <div style="margin-top:20px; display:flex; flex-wrap:wrap; gap:12px;">
                    <button class="btn btn-primary" type="submit">Créer</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/price-library', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>

