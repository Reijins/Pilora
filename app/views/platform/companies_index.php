<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$tab = isset($platformTab) && in_array($platformTab, ['companies', 'packs', 'audit', 'invoices', 'users', 'demos', 'settings'], true) ? $platformTab : 'companies';
$demoRequests = is_array($demoRequests ?? null) ? $demoRequests : [];
$platformBillingSettings = is_array($platformBillingSettings ?? null) ? $platformBillingSettings : [];
$platformSmtpSettings = is_array($platformSmtpSettings ?? null) ? $platformSmtpSettings : [];
$smtp = $platformSmtpSettings;
$companies = is_array($companies ?? null) ? $companies : [];
$packs = is_array($packs ?? null) ? $packs : [];
$auditRows = is_array($auditRows ?? null) ? $auditRows : [];
$invoiceTracking = is_array($invoiceTracking ?? null) ? $invoiceTracking : [];
$platformUsers = is_array($platformUsers ?? null) ? $platformUsers : [];
$canAudit = !empty($canAudit);
$canBilling = !empty($canBilling);
$canImpersonate = !empty($canImpersonate);
$currentUserId = (int) ($currentUserId ?? 0);
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Sociétés</h2>
            <p class="muted">Gestion multi-entreprise (back-office plateforme).</p>
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
            <p class="muted platform-nav-hint" style="margin:0 0 16px;">Utilisez le menu <strong>Plateforme</strong> dans la barre latérale pour changer de section.</p>

            <?php if ($tab === 'companies'): ?>
                <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                    <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/platform/companies/new', ENT_QUOTES, 'UTF-8') ?>">Nouvelle société</a>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>ID</th><th>Nom</th><th>Statut</th><th>Email facturation</th><th></th><th></th></tr></thead>
                        <tbody>
                        <?php if ($companies !== []): foreach ($companies as $c): ?>
                            <tr>
                                <td><?= (int) ($c['id'] ?? 0) ?></td>
                                <td><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($c['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($c['billingEmail'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><a class="link-action" href="<?= htmlspecialchars($basePath . '/platform/companies/show?id=' . (int) ($c['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Détails</a></td>
                                <td>
                                    <?php if ($canImpersonate): ?>
                                        <form method="post" action="<?= htmlspecialchars($basePath . '/platform/impersonate/start', ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="company_id" value="<?= (int) ($c['id'] ?? 0) ?>">
                                            <button class="btn btn-secondary btn-sm" type="submit">Se connecter en admin</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="muted">Aucune société.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($tab === 'settings'): ?>
                <?php
                $settingsSubRaw = (string) ($platformSettingsSub ?? 'legal');
                $settingsSub = in_array($settingsSubRaw, ['legal', 'smtp', 'email-billing', 'email-welcome', 'email-demo'], true)
                    ? $settingsSubRaw
                    : 'legal';
                if (!$canBilling && $settingsSub !== 'legal') {
                    $settingsSub = 'legal';
                }
                $settingsBase = $basePath . '/platform/companies?tab=settings';
                $smtp = $platformSmtpSettings;
                ?>
                <nav class="email-template-subtabs" role="tablist" aria-label="Paramètres Pilora" style="margin-bottom:20px;">
                    <a class="btn btn-secondary email-template-subtab <?= $settingsSub === 'legal' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($settingsBase . '&sub=legal', ENT_QUOTES, 'UTF-8') ?>">Coordonnées &amp; facturation</a>
                    <?php if ($canBilling): ?>
                        <a class="btn btn-secondary email-template-subtab <?= $settingsSub === 'smtp' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($settingsBase . '&sub=smtp', ENT_QUOTES, 'UTF-8') ?>">SMTP</a>
                        <a class="btn btn-secondary email-template-subtab <?= $settingsSub === 'email-billing' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($settingsBase . '&sub=email-billing', ENT_QUOTES, 'UTF-8') ?>">Email — Abonnement</a>
                        <a class="btn btn-secondary email-template-subtab <?= $settingsSub === 'email-welcome' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($settingsBase . '&sub=email-welcome', ENT_QUOTES, 'UTF-8') ?>">Email — Bienvenue</a>
                        <a class="btn btn-secondary email-template-subtab <?= $settingsSub === 'email-demo' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($settingsBase . '&sub=email-demo', ENT_QUOTES, 'UTF-8') ?>">Email — Démo</a>
                    <?php endif; ?>
                </nav>
                <?php
                if ($settingsSub === 'smtp') {
                    require __DIR__ . '/partials/platform_settings_smtp.php';
                } elseif ($settingsSub === 'email-billing') {
                    require __DIR__ . '/partials/platform_settings_email_billing.php';
                } elseif ($settingsSub === 'email-welcome') {
                    require __DIR__ . '/partials/platform_settings_email_welcome.php';
                } elseif ($settingsSub === 'email-demo') {
                    require __DIR__ . '/partials/platform_settings_email_demo.php';
                } else {
                    require __DIR__ . '/partials/platform_settings_legal.php';
                }
                ?>

            <?php elseif ($tab === 'demos'): ?>
                <p class="muted" style="margin-bottom:12px;">Demandes reçues depuis le formulaire public <a href="<?= htmlspecialchars($basePath . '/demo', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">/demo</a>.</p>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Contact</th>
                                <th>Entreprise</th>
                                <th>Message</th>
                                <th>Emails</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($demoRequests !== []): foreach ($demoRequests as $dr): ?>
                            <?php
                            $drId = (int) ($dr['id'] ?? 0);
                            $drStatus = (string) ($dr['status'] ?? 'new');
                            $drNotes = (string) ($dr['notes'] ?? '');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($dr['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <strong><?= htmlspecialchars((string) ($dr['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><br>
                                    <a href="mailto:<?= htmlspecialchars((string) ($dr['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($dr['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                                </td>
                                <td><?= htmlspecialchars((string) (($dr['companyName'] ?? '') !== '' ? $dr['companyName'] : '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="max-width:220px;white-space:pre-wrap;"><?= htmlspecialchars((string) (($dr['message'] ?? '') !== '' ? $dr['message'] : '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="muted" style="font-size:0.85rem;">
                                    <?php if (!empty($dr['ackSentAt'])): ?>Accusé<?php else: ?>Accusé —<?php endif; ?><br>
                                    <?php if (!empty($dr['notifySentAt'])): ?>Notif. équipe<?php else: ?>Notif. —<?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="<?= htmlspecialchars($basePath . '/platform/demo-requests/update', ENT_QUOTES, 'UTF-8') ?>" class="form" style="min-width:200px;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id" value="<?= $drId ?>">
                                        <select class="input" name="status" style="margin-bottom:6px;">
                                            <option value="new"<?= $drStatus === 'new' ? ' selected' : '' ?>>Nouveau</option>
                                            <option value="contacted"<?= $drStatus === 'contacted' ? ' selected' : '' ?>>Contacté</option>
                                            <option value="closed"<?= $drStatus === 'closed' ? ' selected' : '' ?>>Clôturé</option>
                                            <option value="spam"<?= $drStatus === 'spam' ? ' selected' : '' ?>>Spam</option>
                                        </select>
                                        <textarea class="input" name="notes" rows="2" placeholder="Notes internes"><?= htmlspecialchars($drNotes, ENT_QUOTES, 'UTF-8') ?></textarea>
                                        <button class="btn btn-secondary btn-sm" type="submit" style="margin-top:6px;">Enregistrer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="muted">Aucune demande pour le moment.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($tab === 'packs' && $canBilling): ?>
                <p class="muted" style="margin-bottom:12px;">Définissez vos offres (prix, cycle et date de prochain renouvellement). Un script cron peut ensuite déclencher l’envoi auto des factures.</p>
                <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px; align-items:center;">
                    <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/platform/packs/new', ENT_QUOTES, 'UTF-8') ?>">Nouveau pack</a>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Nom</th><th>Utilisateurs</th><th>Prix</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php if ($packs !== []): foreach ($packs as $p): ?>
                            <?php $packRowId = (int) ($p['id'] ?? 0); ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) ($p['maxUsers'] ?? 0) ?></td>
                                <td><?= htmlspecialchars((string) ($p['price'] ?? '0'), ENT_QUOTES, 'UTF-8') ?> €</td>
                                <td>
                                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                                        <?php if ($packRowId > 0): ?>
                                            <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($basePath . '/platform/packs/edit?id=' . $packRowId, ENT_QUOTES, 'UTF-8') ?>">Modifier</a>
                                        <?php endif; ?>
                                        <form method="post" action="<?= htmlspecialchars($basePath . '/platform/packs/delete', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="id" value="<?= $packRowId ?>">
                                            <button class="btn btn-danger btn-sm" type="submit">Supprimer</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" class="muted">Aucun pack.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($tab === 'audit' && $canAudit): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Action</th><th>Acteur</th><th>Société</th><th>Cible</th></tr></thead>
                        <tbody>
                        <?php if ($auditRows !== []): foreach ($auditRows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($r['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><code><?= htmlspecialchars((string) ($r['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><?= htmlspecialchars((string) ($r['actorEmail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($r['companyName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($r['targetCompanyName'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="muted">Aucune entrée.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($tab === 'invoices' && $canBilling): ?>
                <p class="muted" style="margin-bottom:12px;">Suivi global des factures par société pour identifier rapidement les retards.</p>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Société</th><th>Email facturation</th><th>Factures</th><th>Impayé total</th><th>Retards</th><th>Montant en retard</th><th>Plus ancien retard</th></tr></thead>
                        <tbody>
                        <?php if ($invoiceTracking !== []): foreach ($invoiceTracking as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($r['companyName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (($r['billingEmail'] ?? '') !== '' ? $r['billingEmail'] : '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) ($r['invoicesCount'] ?? 0) ?></td>
                                <td><?= htmlspecialchars(number_format((float) ($r['unpaidAmount'] ?? 0), 2, ',', ' ') . ' €', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) ($r['overdueCount'] ?? 0) ?></td>
                                <td><?= htmlspecialchars(number_format((float) ($r['overdueAmount'] ?? 0), 2, ',', ' ') . ' €', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (($r['oldestOverdueDate'] ?? '') !== '' ? $r['oldestOverdueDate'] : '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" class="muted">Aucune donnée facture.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($tab === 'users'): ?>
                <p class="muted" style="margin-bottom:12px;">Gestion des utilisateurs de la plateforme (société opératrice).</p>
                <div style="margin-bottom:12px;">
                    <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/platform/users/new', ENT_QUOTES, 'UTF-8') ?>">Nouvel utilisateur</a>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Nom</th><th>Email</th><th>Statut</th><th></th><th></th></tr></thead>
                        <tbody>
                        <?php if ($platformUsers !== []): foreach ($platformUsers as $u): ?>
                            <?php $uid = (int) ($u['id'] ?? 0); $ust = (string) ($u['status'] ?? 'active'); ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($u['fullName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ust, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><a class="link-action" href="<?= htmlspecialchars($basePath . '/platform/users/edit?id=' . $uid, ENT_QUOTES, 'UTF-8') ?>">Modifier</a></td>
                                <td>
                                    <?php if ($uid === $currentUserId): ?>
                                        <span class="muted">Compte courant</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= htmlspecialchars($basePath . '/platform/users/delete', ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Confirmer la suppression de cet utilisateur plateforme ?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <button class="btn btn-danger btn-sm" type="submit">Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="muted">Aucun utilisateur.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
