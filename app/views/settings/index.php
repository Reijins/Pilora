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
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashError), ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                        <?= htmlspecialchars(rawurldecode((string) $flashMessage), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div class="settings-tabs">
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'general' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=general', ENT_QUOTES, 'UTF-8') ?>">Paramètres généraux</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'smtp' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=smtp', ENT_QUOTES, 'UTF-8') ?>">SMTP</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'email_templates' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=email_templates', ENT_QUOTES, 'UTF-8') ?>">Modèles emails</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'accounting' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=accounting', ENT_QUOTES, 'UTF-8') ?>">Compte comptable</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'billing' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=billing', ENT_QUOTES, 'UTF-8') ?>">Facturation</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'users' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=users', ENT_QUOTES, 'UTF-8') ?>">Utilisateurs</a>
                    <a class="btn btn-secondary settings-tab <?= $currentTab === 'rbac' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=rbac', ENT_QUOTES, 'UTF-8') ?>">Rôles & permissions</a>
                </div>

                <?php if ($currentTab === 'general'): ?>
                    <div class="settings-panel">
                        <h3 style="margin:0 0 10px;">Paramètres généraux</h3>
                        <?php $smtp = is_array($smtpSettings ?? null) ? $smtpSettings : []; ?>
                        <?php $logoPath = trim((string) ($smtp['company_logo_path'] ?? '')); ?>
                        <?php $companyRow = is_array($company ?? null) ? $company : []; ?>
                        <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/smtp/update', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:none;" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="settings_tab" value="general">
                            <div class="contact-form-grid">
                                <div class="field contact-field-full">
                                    <label class="label" for="company_name">Nom de l’entreprise</label>
                                    <input class="input" id="company_name" name="company_name" type="text" maxlength="255" required value="<?= htmlspecialchars((string) ($companyRow['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <p class="muted" style="margin:4px 0 0;">Ce nom apparaît dans la plateforme (liste des sociétés) et dans l’application.</p>
                                </div>
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
                                    <label class="label" for="work_hours_per_day">Heures de travail par jour</label>
                                    <?php $whCompany = is_numeric(($companyRow['workHoursPerDay'] ?? null)) ? (float) $companyRow['workHoursPerDay'] : 8.0; ?>
                                    <input class="input" id="work_hours_per_day" name="work_hours_per_day" type="number" step="0.25" min="0.25" max="24" value="<?= htmlspecialchars((string) $whCompany, ENT_QUOTES, 'UTF-8') ?>">
                                    <p class="muted" style="margin:4px 0 0;">Utilisé pour convertir jours ↔ heures sur les temps passés chantier (défaut 8 h).</p>
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
                                <div class="field contact-field-full">
                                    <label class="label">Paiement en ligne (Stripe)</label>
                                    <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                                        <input
                                            type="checkbox"
                                            name="stripe_online_payment_enabled"
                                            value="1"
                                            <?= (string) ($smtp['stripe_online_payment_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                                        >
                                        Activer le paiement par carte sur la page publique de facture (<code>/invoice/pay</code>)
                                    </label>
                                    <p class="muted" style="margin:6px 0 0;">La clé secrète reste stockée dans les paramètres de l’entreprise (fichier sécurisé côté serveur), pas dans le code.</p>
                                </div>
                                <div class="field contact-field-full">
                                    <label class="label" for="stripe_secret_key">Clé secrète Stripe</label>
                                    <input
                                        class="input"
                                        id="stripe_secret_key"
                                        name="stripe_secret_key"
                                        type="password"
                                        autocomplete="off"
                                        placeholder="<?= trim((string) ($smtp['stripe_secret_key'] ?? '')) !== '' ? 'Laisser vide pour conserver la clé enregistrée' : 'sk_live_… ou sk_test_…' ?>"
                                        value=""
                                    >
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
                    <?php
                        $smtp = is_array($smtpSettings ?? null) ? $smtpSettings : [];
                        $emailSub = isset($emailSubTab) && in_array($emailSubTab, ['quote', 'invoice', 'invoice_paid'], true) ? $emailSubTab : 'quote';
                        $quoteVarChips = [
                            ['{{company_name}}', 'Entreprise'],
                            ['{{client_name}}', 'Client'],
                            ['{{quote_number}}', 'N° devis'],
                            ['{{quote_title}}', 'Titre devis'],
                            ['{{quote_total_ht}}', 'Total HT'],
                            ['{{quote_link}}', 'Lien en ligne'],
                        ];
                        $invoiceVarChips = [
                            ['{{company_name}}', 'Entreprise'],
                            ['{{client_name}}', 'Client'],
                            ['{{invoice_number}}', 'N° facture'],
                            ['{{invoice_title}}', 'Titre facture'],
                            ['{{project_name}}', 'Chantier'],
                            ['{{amount_total_ttc}}', 'Montant TTC'],
                            ['{{due_date}}', 'Échéance'],
                            ['{{invoice_link}}', 'Lien facture en ligne'],
                        ];
                        $invoicePaidVarChips = [
                            ['{{company_name}}', 'Entreprise'],
                            ['{{legal_name}}', 'Raison sociale Pilora'],
                            ['{{client_name}}', 'Client'],
                            ['{{invoice_number}}', 'N° facture'],
                            ['{{invoice_title}}', 'Titre facture'],
                            ['{{project_name}}', 'Chantier'],
                            ['{{amount_total_ttc}}', 'Montant TTC facture'],
                            ['{{amount_paid}}', 'Montant payé'],
                            ['{{remaining}}', 'Reste à payer'],
                            ['{{payment_date}}', 'Date du paiement'],
                            ['{{invoice_link}}', 'Lien facture en ligne'],
                        ];
                    ?>
                    <div class="settings-panel">
                        <h3 style="margin:0 0 12px;">Modèles d’emails</h3>
                        <p class="muted" style="margin:0 0 14px;">Cliquez sur une variable pour l’insérer à la position du curseur dans l’objet ou le corps (placez le curseur avant de cliquer).</p>

                        <div class="email-template-subtabs" role="tablist" aria-label="Type de modèle">
                            <a class="btn btn-secondary email-template-subtab <?= $emailSub === 'quote' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $emailSub === 'quote' ? 'true' : 'false' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=email_templates&email_sub=quote', ENT_QUOTES, 'UTF-8') ?>">Devis</a>
                            <a class="btn btn-secondary email-template-subtab <?= $emailSub === 'invoice' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $emailSub === 'invoice' ? 'true' : 'false' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=email_templates&email_sub=invoice', ENT_QUOTES, 'UTF-8') ?>">Facture</a>
                            <a class="btn btn-secondary email-template-subtab <?= $emailSub === 'invoice_paid' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $emailSub === 'invoice_paid' ? 'true' : 'false' ?>" href="<?= htmlspecialchars($basePath . '/settings?tab=email_templates&email_sub=invoice_paid', ENT_QUOTES, 'UTF-8') ?>">Réception paiement</a>
                        </div>

                        <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/smtp/update', ENT_QUOTES, 'UTF-8') ?>" class="form email-templates-form" style="max-width:none;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="settings_tab" value="email_templates">
                            <input type="hidden" name="email_sub" value="<?= htmlspecialchars($emailSub, ENT_QUOTES, 'UTF-8') ?>">
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

                            <div class="email-template-panel" id="email-panel-quote" role="tabpanel" <?= $emailSub !== 'quote' ? 'hidden' : '' ?>>
                                <h4 class="email-template-panel__title">Envoi de devis</h4>
                                <div class="email-var-toolbar" role="group" aria-label="Variables devis">
                                    <span class="email-var-toolbar__label">Variables</span>
                                    <?php foreach ($quoteVarChips as $chip): ?>
                                        <button type="button" class="email-var-chip js-email-var" data-token="<?= htmlspecialchars($chip[0], ENT_QUOTES, 'UTF-8') ?>" data-scope="quote" title="<?= htmlspecialchars($chip[0], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($chip[1], ENT_QUOTES, 'UTF-8') ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="contact-form-grid">
                                    <div class="field contact-field-full">
                                        <label class="label" for="quote_email_subject">Objet</label>
                                        <input class="input js-email-target" id="quote_email_subject" name="quote_email_subject" type="text" value="<?= htmlspecialchars((string) ($smtp['quote_email_subject'] ?? 'Votre devis {{quote_number}}'), ENT_QUOTES, 'UTF-8') ?>" data-scope="quote">
                                    </div>
                                    <div class="field contact-field-full">
                                        <label class="label" for="quote_email_body">Corps du message</label>
                                        <textarea class="input js-email-target" id="quote_email_body" name="quote_email_body" style="min-height:200px; resize:vertical;" data-scope="quote"><?= htmlspecialchars((string) ($smtp['quote_email_body'] ?? "Bonjour,\n\nVeuillez trouver votre devis en pièce jointe (PDF).\nVous pouvez aussi le consulter en ligne : {{quote_link}}\n\nCordialement,\n{{company_name}}"), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="email-template-panel" id="email-panel-invoice" role="tabpanel" <?= $emailSub !== 'invoice' ? 'hidden' : '' ?>>
                                <h4 class="email-template-panel__title">Envoi de facture</h4>
                                <div class="email-var-toolbar" role="group" aria-label="Variables facture">
                                    <span class="email-var-toolbar__label">Variables</span>
                                    <?php foreach ($invoiceVarChips as $chip): ?>
                                        <button type="button" class="email-var-chip js-email-var" data-token="<?= htmlspecialchars($chip[0], ENT_QUOTES, 'UTF-8') ?>" data-scope="invoice" title="<?= htmlspecialchars($chip[0], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($chip[1], ENT_QUOTES, 'UTF-8') ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="contact-form-grid">
                                    <div class="field contact-field-full">
                                        <label class="label" for="invoice_email_subject">Objet</label>
                                        <input class="input js-email-target" id="invoice_email_subject" name="invoice_email_subject" type="text" value="<?= htmlspecialchars((string) ($smtp['invoice_email_subject'] ?? 'Votre facture {{invoice_number}}'), ENT_QUOTES, 'UTF-8') ?>" data-scope="invoice">
                                    </div>
                                    <div class="field contact-field-full">
                                        <label class="label" for="invoice_email_body">Corps du message</label>
                                        <textarea class="input js-email-target" id="invoice_email_body" name="invoice_email_body" style="min-height:200px; resize:vertical;" data-scope="invoice"><?= htmlspecialchars((string) ($smtp['invoice_email_body'] ?? "Bonjour,\n\nVeuillez trouver votre facture en pièce jointe (PDF).\n\nConsultez ou payez en ligne : {{invoice_link}}\n\nCordialement,\n{{company_name}}"), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="email-template-panel" id="email-panel-invoice-paid" role="tabpanel" <?= $emailSub !== 'invoice_paid' ? 'hidden' : '' ?>>
                                <h4 class="email-template-panel__title">Accusé de réception de paiement (facture soldée)</h4>
                                <p class="muted" style="margin:0 0 10px;font-size:13px;">Envoyé au client lorsque la facture passe au statut « Payée » (carte, virement saisi manuellement, etc.). Laisser vide pour désactiver l’envoi automatique.</p>
                                <div class="email-var-toolbar" role="group" aria-label="Variables paiement">
                                    <span class="email-var-toolbar__label">Variables</span>
                                    <?php foreach ($invoicePaidVarChips as $chip): ?>
                                        <button type="button" class="email-var-chip js-email-var" data-token="<?= htmlspecialchars($chip[0], ENT_QUOTES, 'UTF-8') ?>" data-scope="invoice_paid" title="<?= htmlspecialchars($chip[0], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($chip[1], ENT_QUOTES, 'UTF-8') ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="contact-form-grid">
                                    <div class="field contact-field-full">
                                        <label class="label" for="invoice_paid_email_subject">Objet</label>
                                        <input class="input js-email-target" id="invoice_paid_email_subject" name="invoice_paid_email_subject" type="text" value="<?= htmlspecialchars((string) ($smtp['invoice_paid_email_subject'] ?? 'Réception de votre paiement — {{invoice_number}}'), ENT_QUOTES, 'UTF-8') ?>" data-scope="invoice_paid">
                                    </div>
                                    <div class="field contact-field-full">
                                        <label class="label" for="invoice_paid_email_body">Corps du message</label>
                                        <textarea class="input js-email-target" id="invoice_paid_email_body" name="invoice_paid_email_body" style="min-height:200px; resize:vertical;" data-scope="invoice_paid"><?= htmlspecialchars((string) ($smtp['invoice_paid_email_body'] ?? "Bonjour {{client_name}},\n\nNous accusons réception de votre paiement pour la facture {{invoice_number}} ({{amount_paid}}).\n\nMerci pour votre confiance.\n\nCordialement,\n{{company_name}}"), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="inline-actions" style="margin-top:16px;">
                                <button class="btn btn-primary" type="submit">Enregistrer les modèles</button>
                            </div>
                        </form>
                    </div>
                    <script>
                    (function () {
                        function insertAtCaret(el, text) {
                            if (!el || text === '') return;
                            var start = el.selectionStart != null ? el.selectionStart : 0;
                            var end = el.selectionEnd != null ? el.selectionEnd : 0;
                            var val = el.value;
                            el.value = val.slice(0, start) + text + val.slice(end);
                            var pos = start + text.length;
                            if (el.setSelectionRange) el.setSelectionRange(pos, pos);
                            el.focus();
                        }
                        document.querySelectorAll('.email-templates-form .js-email-var').forEach(function (btn) {
                            btn.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                var token = btn.getAttribute('data-token') || '';
                                var scope = btn.getAttribute('data-scope') || 'quote';
                                var active = document.activeElement;
                                var ids = scope === 'invoice'
                                    ? ['invoice_email_subject', 'invoice_email_body']
                                    : (scope === 'invoice_paid'
                                        ? ['invoice_paid_email_subject', 'invoice_paid_email_body']
                                        : ['quote_email_subject', 'quote_email_body']);
                                var defaultBodyId = scope === 'invoice' ? 'invoice_email_body' : (scope === 'invoice_paid' ? 'invoice_paid_email_body' : 'quote_email_body');
                                var el = active && active.id && ids.indexOf(active.id) !== -1 ? active : document.getElementById(defaultBodyId);
                                if (el) insertAtCaret(el, token);
                            });
                        });
                    })();
                    </script>
                <?php elseif ($currentTab === 'accounting'): ?>
                    <?php $smtpAcc = is_array($smtpSettings ?? null) ? $smtpSettings : []; ?>
                    <?php
                        $vatPairsUi = \Modules\Settings\Services\AccountingSettingsService::parseVatRateAccounts($smtpAcc['vat_rate_accounts'] ?? '[]');
                        if ($vatPairsUi === []) {
                            $vatPairsUi = [['rate' => '', 'account' => '']];
                        }
                    ?>
                    <div class="settings-panel">
                        <h3 style="margin:0 0 10px;">Compte comptable</h3>
                        <p class="muted" style="margin:0 0 14px;">Comptes de TVA collectée par taux (export CSV), compte tiers client et compte de ventes par défaut.</p>
                        <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/smtp/update', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:none;" id="form-accounting-settings">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="settings_tab" value="accounting">
                            <div class="field contact-field-full">
                                <label class="label">Comptes TVA par taux</label>
                                <div style="overflow-x:auto;">
                                    <table class="table" id="vat-accounts-table" style="min-width:420px;">
                                        <thead>
                                            <tr>
                                                <th style="width:140px;">Taux TVA (%)</th>
                                                <th>Numéro de compte</th>
                                                <th style="width:48px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="vat-accounts-body">
                                            <?php foreach ($vatPairsUi as $idx => $vp): ?>
                                                <tr class="vat-account-row">
                                                    <td>
                                                        <input class="input" name="vat_account_rate[]" type="number" step="0.01" min="0" max="100" placeholder="20" value="<?= isset($vp['rate']) ? htmlspecialchars((string) $vp['rate'], ENT_QUOTES, 'UTF-8') : '' ?>">
                                                    </td>
                                                    <td>
                                                        <input class="input" name="vat_account_number[]" type="text" maxlength="32" placeholder="44571000" value="<?= htmlspecialchars((string) ($vp['account'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    </td>
                                                    <td style="text-align:center;">
                                                        <button type="button" class="btn btn-danger btn-icon js-vat-row-remove" aria-label="Supprimer la ligne" title="Supprimer">×</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-secondary" id="vat-accounts-add-row" style="margin-top:8px;">Ajouter un taux</button>
                            </div>
                            <div class="field">
                                <label class="label" for="acc_default_client_account">Compte client (défaut)</label>
                                <input class="input" id="acc_default_client_account" name="default_client_account" type="text" maxlength="32" placeholder="41100000" value="<?= htmlspecialchars(trim((string) ($smtpAcc['default_client_account'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field">
                                <label class="label" for="acc_default_revenue_account">Compte ventes (si ligne sans compte)</label>
                                <input class="input" id="acc_default_revenue_account" name="default_revenue_account" type="text" maxlength="32" placeholder="70600000" value="<?= htmlspecialchars(trim((string) ($smtpAcc['default_revenue_account'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <button class="btn btn-primary" type="submit">Enregistrer</button>
                        </form>
                        <template id="tpl-vat-account-row">
                            <tr class="vat-account-row">
                                <td><input class="input" name="vat_account_rate[]" type="number" step="0.01" min="0" max="100" placeholder="20"></td>
                                <td><input class="input" name="vat_account_number[]" type="text" maxlength="32" placeholder="44571000" value=""></td>
                                <td style="text-align:center;"><button type="button" class="btn btn-danger btn-icon js-vat-row-remove" aria-label="Supprimer" title="Supprimer">×</button></td>
                            </tr>
                        </template>
                        <script>
                            (function () {
                                var body = document.getElementById('vat-accounts-body');
                                var tpl = document.getElementById('tpl-vat-account-row');
                                var addBtn = document.getElementById('vat-accounts-add-row');
                                if (!body || !tpl || !addBtn) return;
                                function bindRemove(row) {
                                    row.querySelectorAll('.js-vat-row-remove').forEach(function (b) {
                                        b.addEventListener('click', function () {
                                            if (body.querySelectorAll('.vat-account-row').length <= 1) return;
                                            row.remove();
                                        });
                                    });
                                }
                                body.querySelectorAll('.vat-account-row').forEach(bindRemove);
                                addBtn.addEventListener('click', function () {
                                    var node = tpl.content.cloneNode(true);
                                    var row = node.querySelector('tr');
                                    if (row) { body.appendChild(row); bindRemove(row); }
                                });
                            })();
                        </script>
                    </div>
                <?php elseif ($currentTab === 'billing'): ?>
                    <?php
                        $company = is_array($company ?? null) ? $company : [];
                        $packs = is_array($packs ?? null) ? $packs : [];
                        $paidPacks = [];
                        foreach ($packs as $p) {
                            if ((float) ($p['price'] ?? 0) > 0) {
                                $paidPacks[] = $p;
                            }
                        }
                        $currentPlan = (string) ($company['billingPlan'] ?? '');
                        $currentCycle = (string) ($company['billingCycle'] ?? '');
                        $currentRenew = (string) ($company['subscriptionRenewsAt'] ?? '');
                    ?>
                    <div class="settings-panel">
                        <h3 style="margin:0 0 10px;">Abonnement</h3>
                        <p class="muted" style="margin:0 0 12px;">
                            Pack actuel: <strong><?= htmlspecialchars($currentPlan !== '' ? $currentPlan : 'Aucun', ENT_QUOTES, 'UTF-8') ?></strong>
                            — Cycle: <strong><?= htmlspecialchars($currentCycle !== '' ? ($currentCycle === 'annual' ? 'Annuel' : 'Mensuel') : '—', ENT_QUOTES, 'UTF-8') ?></strong>
                            — Renouvellement: <strong><?= htmlspecialchars($currentRenew !== '' ? $currentRenew : '—', ENT_QUOTES, 'UTF-8') ?></strong>
                        </p>

                        <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/billing/subscribe', ENT_QUOTES, 'UTF-8') ?>" class="form-stack">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <label class="label">Choisir un pack payant</label>
                            <select class="input js-billing-pack" name="pack_id" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($paidPacks as $p): ?>
                                    <option value="<?= (int) ($p['id'] ?? 0) ?>" data-price="<?= htmlspecialchars((string) (float) ($p['price'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        — <?= htmlspecialchars(number_format((float) ($p['price'] ?? 0), 2, ',', ' ') . ' €', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label class="label">Cycle</label>
                            <select class="input js-billing-cycle" name="billing_cycle">
                                <option value="monthly">Mensuel</option>
                                <option value="annual">Annuel</option>
                            </select>
                            <p class="muted js-billing-cost" style="margin:4px 0 0;">Sélectionnez un pack pour afficher le coût.</p>

                            <button class="btn btn-primary" type="submit">Activer l'abonnement</button>
                        </form>
                        <script>
                        (function () {
                            var panel = document.currentScript && document.currentScript.previousElementSibling;
                            if (!panel || !panel.matches('form')) return;
                            var packSelect = panel.querySelector('.js-billing-pack');
                            var cycleSelect = panel.querySelector('.js-billing-cycle');
                            var costEl = panel.querySelector('.js-billing-cost');
                            if (!packSelect || !cycleSelect || !costEl) return;

                            function formatEuro(value) {
                                return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + ' €';
                            }

                            function refreshCost() {
                                var opt = packSelect.options[packSelect.selectedIndex];
                                var name = opt ? (opt.getAttribute('data-name') || '') : '';
                                var monthlyPrice = opt ? parseFloat(opt.getAttribute('data-price') || '0') : 0;
                                if (!name || !(monthlyPrice > 0)) {
                                    costEl.textContent = 'Sélectionnez un pack pour afficher le coût.';
                                    return;
                                }
                                var cycle = cycleSelect.value || 'monthly';
                                if (cycle === 'annual') {
                                    var annual = monthlyPrice * 12;
                                    costEl.textContent = 'Coût ' + name + ' : ' + formatEuro(annual) + ' / an (' + formatEuro(monthlyPrice) + ' / mois).';
                                } else {
                                    costEl.textContent = 'Coût ' + name + ' : ' + formatEuro(monthlyPrice) + ' / mois.';
                                }
                            }

                            packSelect.addEventListener('change', refreshCost);
                            cycleSelect.addEventListener('change', refreshCost);
                            refreshCost();
                        })();
                        </script>
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
                                        <th>Coût horaire (€)</th>
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
                                            <td>
                                                <form method="POST" action="<?= htmlspecialchars($basePath . '/settings/users/cout-horaire', ENT_QUOTES, 'UTF-8') ?>" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int) ($user['id'] ?? 0) ?>">
                                                    <?php $ch = $user['coutHoraire'] ?? null; ?>
                                                    <input class="input" name="cout_horaire" type="text" inputmode="decimal" placeholder="ex. 35" value="<?= is_numeric($ch) ? htmlspecialchars(number_format((float) $ch, 2, ',', ''), ENT_QUOTES, 'UTF-8') : '' ?>" style="width:88px;">
                                                    <button class="btn btn-secondary" type="submit" style="padding:6px 10px;">OK</button>
                                                </form>
                                            </td>
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

