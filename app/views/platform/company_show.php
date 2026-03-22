<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$c = $company ?? [];
$id = (int) ($c['id'] ?? 0);
$canBilling = !empty($canBilling);
$canImpersonate = !empty($canImpersonate);
$canManageCompany = !empty($canManageCompany);
$tab = isset($companyTab) && in_array($companyTab, ['general', 'billing', 'users'], true) ? $companyTab : 'general';
$companyUsers = is_array($companyUsers ?? null) ? $companyUsers : [];
$renews = isset($c['subscriptionRenewsAt']) && $c['subscriptionRenewsAt'] !== null && $c['subscriptionRenewsAt'] !== ''
    ? (string) $c['subscriptionRenewsAt']
    : '';
if ($renews !== '' && strlen($renews) >= 10) {
    $renews = substr($renews, 0, 10);
}
?>
<section class="page">
    <div class="card">
        <div class="card-header card-header-with-back">
            <a class="link-back" href="<?= htmlspecialchars($basePath . '/platform/companies?tab=companies', ENT_QUOTES, 'UTF-8') ?>" aria-label="Retour à la liste" title="Retour à la liste">
                <svg class="link-back__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <div class="card-header-with-back__main">
                <h2><?= htmlspecialchars((string) ($c['name'] ?? 'Société'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="muted">Gestion détaillée de la société.</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (!empty($flashMessage)): ?>
                <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                    <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <div class="email-template-subtabs" role="tablist" aria-label="Sous-onglets société">
                <a class="btn btn-secondary email-template-subtab <?= $tab === 'general' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/platform/companies/show?id=' . $id . '&tab=general', ENT_QUOTES, 'UTF-8') ?>">Société</a>
                <?php if ($canBilling): ?><a class="btn btn-secondary email-template-subtab <?= $tab === 'billing' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/platform/companies/show?id=' . $id . '&tab=billing', ENT_QUOTES, 'UTF-8') ?>">Facturation</a><?php endif; ?>
                <a class="btn btn-secondary email-template-subtab <?= $tab === 'users' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/platform/companies/show?id=' . $id . '&tab=users', ENT_QUOTES, 'UTF-8') ?>">Utilisateurs</a>
            </div>

            <?php if ($tab === 'general'): ?>
            <h3 class="h3-section">Informations générales</h3>
            <?php if ($canManageCompany): ?>
                <form method="post" action="<?= htmlspecialchars($basePath . '/platform/companies/update', ENT_QUOTES, 'UTF-8') ?>" class="form-stack">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <label class="label">Nom</label>
                    <input class="input" type="text" name="name" required maxlength="255" value="<?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                    <label class="label">Email facturation</label>
                    <input class="input" type="email" name="billing_email" maxlength="255" value="<?= htmlspecialchars((string) ($c['billingEmail'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                    <label class="label">Statut</label>
                    <?php $st = (string) ($c['status'] ?? 'active'); ?>
                    <select class="input" name="status">
                        <?php foreach (['active', 'suspended', 'disabled'] as $opt): ?>
                            <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" <?= $st === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div style="margin-top:16px;">
                        <button class="btn btn-primary" type="submit">Enregistrer</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            <tr><th>Nom</th><td><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
                            <tr><th>Email facturation</th><td><?= htmlspecialchars((string) (($c['billingEmail'] ?? '') !== '' ? $c['billingEmail'] : '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                            <tr><th>Statut</th><td><?= htmlspecialchars((string) ($c['status'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($canBilling && $tab === 'billing'): ?>
                <div style="margin-top:24px; padding-top:24px; border-top:1px solid var(--border);">
                    <h3 class="h3-section">Facturation / abonnement</h3>
                    <div class="table-wrap">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th>Abonnement</th>
                                    <td><?= htmlspecialchars((string) (($c['billingPlan'] ?? '') !== '' ? $c['billingPlan'] : '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <tr>
                                    <th>Mode de renouvellement</th>
                                    <?php $bc = (string) ($c['billingCycle'] ?? ''); $cycleLabel = $bc === 'annual' ? 'Annuel' : ($bc === 'monthly' ? 'Mensuel' : '—'); ?>
                                    <td><?= htmlspecialchars($cycleLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <tr>
                                    <th>Prochaine échéance</th>
                                    <td><?= htmlspecialchars($renews !== '' ? $renews : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <tr>
                                    <th>Statut</th>
                                    <td><?= htmlspecialchars((string) (($c['billingStatus'] ?? '') !== '' ? $c['billingStatus'] : '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'users'): ?>
                <div style="margin-top:24px; padding-top:24px; border-top:1px solid var(--border);">
                    <h3 class="h3-section">Utilisateurs</h3>
                    <div style="margin-bottom:12px;">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/platform/companies/users/new?company_id=' . $id, ENT_QUOTES, 'UTF-8') ?>">Nouvel utilisateur</a>
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Nom</th><th>Email</th><th>Statut</th><th style="width:220px;"></th></tr></thead>
                            <tbody>
                            <?php if ($companyUsers !== []): foreach ($companyUsers as $u): ?>
                                <?php $uid = (int) ($u['id'] ?? 0); $ust = (string) ($u['status'] ?? 'active'); ?>
                                <tr>
                                    <td colspan="4">
                                        <form method="post" action="<?= htmlspecialchars($basePath . '/platform/companies/users/update', ENT_QUOTES, 'UTF-8') ?>" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="company_id" value="<?= $id ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input class="input" type="text" name="full_name" value="<?= htmlspecialchars((string) ($u['fullName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="min-width:180px;">
                                            <input class="input" type="email" name="email" value="<?= htmlspecialchars((string) ($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="min-width:220px;">
                                            <select class="input" name="status">
                                                <?php foreach (['active','inactive','pending','invited','disabled'] as $s): ?>
                                                    <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $ust === $s ? 'selected' : '' ?>><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-secondary btn-sm" type="submit">Modifier</button>
                                        </form>
                                        <form method="post" action="<?= htmlspecialchars($basePath . '/platform/companies/users/delete', ENT_QUOTES, 'UTF-8') ?>" style="margin-top:6px;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="company_id" value="<?= $id ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <button class="btn btn-danger btn-sm" type="submit">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="4" class="muted">Aucun utilisateur.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
