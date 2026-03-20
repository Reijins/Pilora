<?php
declare(strict_types=1);
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Modifier le contact</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Acces refuse : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= htmlspecialchars($basePath . '/contacts/update', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:760px;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="contact_id" value="<?= (int) ($contact['id'] ?? 0) ?>">
                    <label class="label" for="client_id">Client</label>
                    <select class="input" id="client_id" name="client_id" required>
                        <?php foreach (($clients ?? []) as $c): ?>
                            <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= (int) ($c['id'] ?? 0) === (int) ($contact['clientId'] ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="contact-form-grid">
                        <div class="field">
                            <label class="label" for="first_name">Prenom</label>
                            <input class="input" id="first_name" name="first_name" type="text" value="<?= htmlspecialchars((string) ($contact['firstName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="field">
                            <label class="label" for="last_name">Nom</label>
                            <input class="input" id="last_name" name="last_name" type="text" value="<?= htmlspecialchars((string) ($contact['lastName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="field">
                            <label class="label" for="function_label">Fonction</label>
                            <input class="input" id="function_label" name="function_label" type="text" value="<?= htmlspecialchars((string) ($contact['functionLabel'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="field">
                            <label class="label" for="email">Email</label>
                            <input class="input" id="email" name="email" type="email" value="<?= htmlspecialchars((string) ($contact['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="field contact-field-full">
                            <label class="label" for="phone">Telephone</label>
                            <input class="input" id="phone" name="phone" type="text" value="<?= htmlspecialchars((string) ($contact['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <div class="inline-actions">
                        <button class="btn btn-primary" type="submit">Enregistrer</button>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients/show?clientId=' . (int) ($contact['clientId'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Retour</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

