<?php
declare(strict_types=1);
// Variables:
// $permissionDenied, $csrfToken, $flashMessage, $flashError
// $users, $roles, $permissions, $roleIdToEdit, $permissionsForRole, $smtpSettings, $activeTab
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Paramètres</h2>
            <p class="muted">Configuration entreprise : SMTP, utilisateurs et permissions.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <?php $currentTab = is_string($activeTab ?? null) ? (string) $activeTab : 'smtp'; ?>
                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                        <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div class="settings-tabs">
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'general' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=general', ENT_QUOTES, 'UTF-8') ?>">Paramètres généraux</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'smtp' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=smtp', ENT_QUOTES, 'UTF-8') ?>">SMTP</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'email_templates' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=email_templates', ENT_QUOTES, 'UTF-8') ?>">Modèles emails</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'users' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=users', ENT_QUOTES, 'UTF-8') ?>">Utilisateurs</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'rbac' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=rbac', ENT_QUOTES, 'UTF-8') ?>">Rôles & permissions</a>
                </div>

                <?php if ($currentTab === 'general'): ?>
                    <div class="settings-panel">
                        <h3 style="margin:0 0 10px;">Paramètres généraux</h3>
                        <?php $smtp = is_array($smtpSettings ?? null) ? $smtpSettings : []; ?>
                        <?php $logoPath = trim((string) ($smtp['company_logo_path'] ?? '')); ?>
                        <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/smtp/update', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:none;" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="settings_tab" value="general">
                            <div class="contact-form-grid">
                                <div class="field contact-field-full">
                                    <label class="label" for="company_logo">Logo entreprise (navbar)</label>
                                    <?php if ($logoPath !== ''): ?>
                                        <div style="margin-bottom:8px;">
                                            <img src="<?= htmlspecialchars($basePath . $logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Logo actuel" style="max-height:48px;object-fit:contain;">
                                        </div>
                                    <?php endif; ?>
                                    <input class="input" id="company_logo" name="company_logo" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                                    <p class="muted" style="margin:4px 0 0;">JPG, PNG, WebP ou GIF — max 2 Mo. Laisser vide pour conserver le logo actuel.</p>
                                </div>
                                <div class="field">
                                    <label class="label" for="vat_rate">Taux de TVA (%)</label>
                                    <input class="input" id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" value="<?= htmlspecialchars((string) ($smtp['vat_rate'] ?? 20), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="field">
                                    <label class="label" for="proof_required">Preuve de commande obligatoire (validation devis)</label>
                                    <select class="input" id="proof_required" name="proof_required">
                                        <option value="0" <?= (string) ($smtp['proof_required'] ?? '0') === '0' ? 'selected' : '' ?>>Non</option>
                                        <option value="1" <?= (string) ($smtp['proof_required'] ?? '0') === '1' ? 'selected' : '' ?>>Oui</option>
                                    </select>
                                </div>
                            </div>
                            <button class="btn btn-primary" type="submit">Enregistrer</button>
                        </form>
                    </div>
                <?php elseif ($currentTab === 'smtp'): ?>
                    <div class="settings-panel">
                        <h3 style="margin:0 0 10px;">Configuration SMTP</h3>
                        <?php $smtp = is_array($smtpSettings ?? null) ? $smtpSettings : []; ?>
                        <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/smtp/update', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:none;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="settings_tab" value="smtp">
                            <input type="hidden" name="vat_rate" value="<?= htmlspecialchars((string) ($smtp['vat_rate'] ?? 20), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="proof_required" value="<?= htmlspecialchars((string) ($smtp['proof_required'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">

                            <div class="contact-form-grid">
                                <div class="field">
                                    <label class="label" for="smtp_host">Serveur SMTP</label>
                                    <input class="input" id="smtp_host" name="smtp_host" type="text" value="<?= htmlspecialchars((string) ($smtp['host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="field">
                                    <label class="label" for="smtp_port">Port</label>
                                    <input class="input" id="smtp_port" name="smtp_port" type="number" min="1" value="<?= (int) ($smtp['port'] ?? 587) ?>" required>
                                </div>
                                <div class="field">
                                    <label class="label" for="smtp_username">Utilisateur</label>
                                    <input class="input" id="smtp_username" name="smtp_username" type="text" value="<?= htmlspecialchars((string) ($smtp['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="field">
                                    <label class="label" for="smtp_auth_enabled">Authentification SMTP</label>
                                    <?php $authEnabled = (string) ($smtp['auth_enabled'] ?? '1'); ?>
                                    <select class="input" id="smtp_auth_enabled" name="smtp_auth_enabled">
                                        <option value="1" <?= $authEnabled !== '0' ? 'selected' : '' ?>>Oui</option>
                                        <option value="0" <?= $authEnabled === '0' ? 'selected' : '' ?>>Non</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="label" for="smtp_password">Mot de passe</label>
                                    <input class="input" id="smtp_password" name="smtp_password" type="password" value="<?= htmlspecialchars((string) ($smtp['password'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="field">
                                    <label class="label" for="smtp_encryption">Sécurité</label>
                                    <select class="input" id="smtp_encryption" name="smtp_encryption">
                                        <?php $enc = (string) ($smtp['encryption'] ?? 'tls'); ?>
                                        <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>Aucune</option>
                                        <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                        <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="label" for="smtp_from_email">Email expéditeur</label>
                                    <input class="input" id="smtp_from_email" name="smtp_from_email" type="email" value="<?= htmlspecialchars((string) ($smtp['from_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="field contact-field-full">
                                    <label class="label" for="smtp_from_name">Nom expéditeur</label>
                                    <input class="input" id="smtp_from_name" name="smtp_from_name" type="text" value="<?= htmlspecialchars((string) ($smtp['from_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                            <div class="inline-actions">
                                <button class="btn btn-primary" type="submit">Enregistrer SMTP</button>
                            </div>
                        </form>
                        <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/smtp/test', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:none; margin-top:12px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="contact-form-grid">
                                <div class="field contact-field-full">
                                    <label class="label" for="smtp_test_email">Email de test</label>
                                    <input class="input" id="smtp_test_email" name="smtp_test_email" type="email" value="<?= htmlspecialchars((string) ($smtp['from_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                            <button class="btn btn-secondary" type="submit">Tester SMTP</button>
                        </form>
                    </div>
                <?php elseif ($currentTab === 'email_templates'): ?>
                    <div class="settings-panel">
                        <h3 style="margin:0 0 10px;">Modèles d'emails</h3>
                        <?php $smtp = is_array($smtpSettings ?? null) ? $smtpSettings : []; ?>
                        <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/smtp/update', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:none;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="settings_tab" value="email_templates">
                            <input type="hidden" name="smtp_host" value="<?= htmlspecialchars((string) ($smtp['host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="smtp_port" value="<?= (int) ($smtp['port'] ?? 587) ?>">
                            <input type="hidden" name="smtp_username" value="<?= htmlspecialchars((string) ($smtp['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="smtp_auth_enabled" value="<?= htmlspecialchars((string) ($smtp['auth_enabled'] ?? '1'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="smtp_password" value="<?= htmlspecialchars((string) ($smtp['password'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="smtp_encryption" value="<?= htmlspecialchars((string) ($smtp['encryption'] ?? 'tls'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="smtp_from_email" value="<?= htmlspecialchars((string) ($smtp['from_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="smtp_from_name" value="<?= htmlspecialchars((string) ($smtp['from_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="vat_rate" value="<?= htmlspecialchars((string) ($smtp['vat_rate'] ?? 20), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="proof_required" value="<?= htmlspecialchars((string) ($smtp['proof_required'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="contact-form-grid">
                                <div class="field contact-field-full">
                                    <label class="label" for="quote_email_subject">Objet email devis</label>
                                    <input class="input" id="quote_email_subject" name="quote_email_subject" type="text" value="<?= htmlspecialchars((string) ($smtp['quote_email_subject'] ?? 'Votre devis {{quote_number}}'), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="field contact-field-full">
                                    <label class="label" for="quote_email_body">Modèle email devis</label>
                                    <textarea class="input" id="quote_email_body" name="quote_email_body" style="min-height:160px; resize:vertical;"><?= htmlspecialchars((string) ($smtp['quote_email_body'] ?? "Bonjour,\n\nVeuillez trouver votre devis en pièce jointe (PDF).\nVous pouvez aussi le consulter en ligne : {{quote_link}}\n\nCordialement,\n{{company_name}}"), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <p class="muted" style="margin:4px 0 0;">Variables: {{company_name}}, {{client_name}}, {{quote_number}}, {{quote_title}}, {{quote_total_ht}}, {{quote_link}}</p>
                                </div>
                            </div>
                            <button class="btn btn-primary" type="submit">Enregistrer modèles</button>
                        </form>
                    </div>
                <?php elseif ($currentTab === 'users'): ?>
                    <div class="settings-panel">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
                            <h3 style="margin:0;">Utilisateurs</h3>
                            <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/settings/users/new', ENT_QUOTES, 'UTF-8') ?>">Créer un utilisateur</a>
                        </div>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Statut</th>
                                        <th>Rôles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($users ?? []) as $user): ?>
                                        <?php
                                            $rolesNames = [];
                                            foreach (($user['roles'] ?? []) as $r) {
                                                $rolesNames[] = (string) ($r['name'] ?? '');
                                            }
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($user['fullName'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($user['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($user['status'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php if (!empty($rolesNames)): ?>
                                                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                                        <?php foreach ($rolesNames as $name): ?>
                                                            <span class="badge"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="settings-panel">
                        <h3 style="margin:0 0 10px;">Rôles & permissions</h3>
                        <div class="card-inner-subtle" style="padding:12px; border:1px solid var(--border); border-radius:14px; background:#fff; margin-bottom:12px;">
                            <form method="GET" action="<?= htmlspecialchars($basePath . '/settings', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="tab" value="rbac">
                                <label class="label" for="role_select">Rôle à modifier</label>
                                <select class="input" id="role_select" name="roleId" onchange="this.form.submit()">
                                    <?php foreach (($roles ?? []) as $role): ?>
                                        <?php $roleId = (int) ($role['id'] ?? 0); ?>
                                        <option value="<?= $roleId ?>" <?= ((int) ($roleIdToEdit ?? 0) === $roleId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>

                        <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/roles/permissions', ENT_QUOTES, 'UTF-8') ?>" class="role-permissions-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="role_id" value="<?= (int) ($roleIdToEdit ?? 0) ?>">

                            <div class="permissions-list">
                                <?php
                                    $permissionsForRoleSet = [];
                                    foreach (($permissionsForRole ?? []) as $pid) {
                                        $permissionsForRoleSet[(int) $pid] = true;
                                    }
                                ?>
                                <?php foreach (($permissions ?? []) as $perm): ?>
                                    <?php $permId = (int) ($perm['id'] ?? 0); ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="permission_ids[]" value="<?= $permId ?>" <?= isset($permissionsForRoleSet[$permId]) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars((string) ($perm['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <button class="btn btn-primary" type="submit" style="margin-top:12px;">Enregistrer permissions</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

