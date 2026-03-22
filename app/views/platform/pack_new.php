<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$pack = isset($pack) && is_array($pack) ? $pack : null;
$isEdit = $pack !== null && (int) ($pack['id'] ?? 0) > 0;
$pid = $isEdit ? (int) ($pack['id'] ?? 0) : 0;
$nameVal = $isEdit ? (string) ($pack['name'] ?? '') : '';
$maxUsersVal = $isEdit ? (int) ($pack['maxUsers'] ?? 1) : '';
$priceVal = $isEdit ? (string) ($pack['price'] ?? '0') : '';
?>
<section class="page">
    <div class="card">
        <div class="card-header card-header-with-back">
            <a class="link-back" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=packs', ENT_QUOTES, 'UTF-8') ?>" aria-label="Retour aux packs" title="Retour aux packs">
                <svg class="link-back__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
            <div class="card-header-with-back__main">
                <h2><?= $isEdit ? 'Modifier le pack' : 'Nouveau pack' ?></h2>
                <p class="muted">Définissez l'offre (utilisateurs, prix, cycle, renouvellement).</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars($basePath . '/platform/packs/upsert', ENT_QUOTES, 'UTF-8') ?>" class="sheet-stack">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= $pid ?>">
                <?php endif; ?>

                <section class="section-card">
                    <h3 class="section-title">Identité du pack</h3>
                    <div class="section-content">
                        <div class="contact-form-grid">
                            <div class="field contact-field-full">
                                <label class="label">Nom du pack</label>
                                <input class="input" type="text" name="name" required maxlength="120" placeholder="Ex: Pro BTP" value="<?= htmlspecialchars($nameVal, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field">
                                <label class="label">Nombre d'utilisateurs</label>
                                <input class="input" type="number" name="max_users" min="1" required placeholder="Ex: 10" value="<?= $isEdit ? (int) $maxUsersVal : '' ?>">
                            </div>
                            <div class="field">
                                <label class="label">Prix</label>
                                <input class="input" type="number" step="0.01" min="0" name="price" required placeholder="Ex: 129.00" value="<?= $isEdit ? htmlspecialchars($priceVal, ENT_QUOTES, 'UTF-8') : '' ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="section-card">
                    <h3 class="section-title">Renouvellement</h3>
                    <div class="section-content">
                        <p class="muted" style="margin:0;">
                            Le cycle (mensuel/annuel) et la prochaine date de renouvellement sont définis au niveau de la société lors de la souscription.
                        </p>
                    </div>
                </section>

                <div class="inline-actions">
                    <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer le pack' ?></button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=packs', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>
