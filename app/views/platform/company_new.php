<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$packs = is_array($packs ?? null) ? $packs : [];
?>
<section class="page">
    <div class="content-inner company-create">
        <div class="card company-create-card">
            <div class="company-create-header-strip" aria-hidden="true"></div>
            <div class="card-header card-header-with-back">
                <a class="link-back" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=companies', ENT_QUOTES, 'UTF-8') ?>" aria-label="Retour à la liste des sociétés" title="Retour à la liste">
                    <svg class="link-back__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="card-header-with-back__main">
                    <h2 class="company-create-title">Nouvelle société</h2>
                    <p class="muted company-create-sub">Créez un espace client (tenant), choisissez l’offre et, si besoin, un premier compte administrateur.</p>
                </div>
            </div>
            <div class="card-body company-create-body">
                <?php if (!empty($error)): ?>
                    <div class="company-create-alert alert alert-danger" role="alert">
                        <span class="company-create-alert__icon" aria-hidden="true">!</span>
                        <span><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= htmlspecialchars($basePath . '/platform/companies/create', ENT_QUOTES, 'UTF-8') ?>" class="company-create-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <div class="company-create-section">
                        <div class="company-create-section__head">
                            <span class="company-create-section__badge" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            </span>
                            <div>
                                <h3 class="company-create-section__title" id="section-identity">Identité &amp; accès</h3>
                                <p class="company-create-section__hint">Informations de la société cliente et statut du contrat.</p>
                            </div>
                        </div>
                        <div class="company-create-grid" role="group" aria-labelledby="section-identity">
                            <div class="field field--full">
                                <label class="label" for="cc-name">Raison sociale <span class="label-required" title="Obligatoire">*</span></label>
                                <input class="input" id="cc-name" type="text" name="name" required maxlength="255" placeholder="Ex. Bâtiments Martin SARL" autocomplete="organization">
                            </div>
                            <div class="field">
                                <label class="label" for="cc-billing">Email facturation</label>
                                <input class="input" id="cc-billing" type="email" name="billing_email" maxlength="255" placeholder="compta@client.fr" autocomplete="email" inputmode="email">
                                <span class="field-help">Pour les relances et la facturation SaaS.</span>
                            </div>
                            <div class="field">
                                <label class="label" for="cc-status">Statut du compte</label>
                                <select class="input input-select" id="cc-status" name="status" aria-describedby="hint-status">
                                    <option value="active">Actif</option>
                                    <option value="suspended">Suspendu</option>
                                    <option value="disabled">Désactivé</option>
                                </select>
                                <span class="field-help" id="hint-status">Contrôle l’accès tenant dans l’application.</span>
                            </div>
                        </div>
                    </div>

                    <div class="company-create-section">
                        <div class="company-create-section__head">
                            <span class="company-create-section__badge company-create-section__badge--accent" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                            </span>
                            <div>
                                <h3 class="company-create-section__title" id="section-pack">Offre souscrite</h3>
                                <p class="company-create-section__hint">Pack tarifaire appliqué à cette société (cycle et renouvellement selon configuration).</p>
                            </div>
                        </div>
                        <div class="field field--full" role="group" aria-labelledby="section-pack">
                            <label class="label" for="cc-pack">Pack <span class="label-required" title="Obligatoire">*</span></label>
                            <select class="input input-select" id="cc-pack" name="pack_id" required>
                                <option value="">Choisir un pack…</option>
                                <?php foreach ($packs as $p): ?>
                                    <?php $pid = (int) ($p['id'] ?? 0); if ($pid <= 0) {
                                        continue;
                                    } ?>
                                    <option value="<?= $pid ?>">
                                        <?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        — <?= htmlspecialchars(number_format((float) ($p['price'] ?? 0), 2, ',', ' ') . ' €', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ((float) ($p['price'] ?? 0) <= 0): ?> (essai gratuit 7 j.)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="company-create-section company-create-section--last">
                        <div class="company-create-section__head">
                            <span class="company-create-section__badge" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            <div>
                                <h3 class="company-create-section__title" id="section-admin">Premier administrateur <span class="company-create-optional">(optionnel)</span></h3>
                                <p class="company-create-section__hint">Crée un utilisateur avec accès à l’espace de cette société. Vous pouvez laisser vide et inviter plus tard.</p>
                            </div>
                        </div>

                        <div class="company-create-nested" role="group" aria-labelledby="section-admin">
                            <div class="company-create-grid">
                                <div class="field field--full">
                                    <label class="label" for="cc-iname">Nom complet</label>
                                    <input class="input" id="cc-iname" type="text" name="initial_user_name" maxlength="255" placeholder="Prénom Nom" autocomplete="name">
                                </div>
                                <div class="field">
                                    <label class="label" for="cc-iemail">Email de connexion</label>
                                    <input class="input" id="cc-iemail" type="email" name="initial_user_email" maxlength="255" placeholder="admin@client.fr" autocomplete="email">
                                </div>
                                <div class="field">
                                    <label class="label" for="cc-ipwd">Mot de passe</label>
                                    <input class="input" id="cc-ipwd" type="password" name="initial_user_password" minlength="8" placeholder="Au moins 8 caractères" autocomplete="new-password">
                                    <span class="field-help">Renseigné uniquement si vous créez le compte maintenant.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="company-create-actions">
                        <button class="btn btn-primary company-create-submit" type="submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            Créer la société
                        </button>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=companies', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
