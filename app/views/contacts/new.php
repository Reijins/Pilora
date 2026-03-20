<?php
declare(strict_types=1);
// Variables: $permissionDenied, $csrfToken, $clients, $selectedClientId, $flashMessage, $flashError
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Nouveau contact</h2>
            <p class="muted">Créer un contact lié a un client.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Acces refuse : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>

                <?php if (is_string($flashMessage) && trim($flashMessage) !== ''): ?>
                    <div class="alert alert-success" style="margin-bottom:12px;"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (is_string($flashError) && trim($flashError) !== ''): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($basePath . '/contacts/create', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:760px;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="return_to" value="contacts">

                    <label class="label" for="client_id">Client</label>
                    <select class="input" id="client_id" name="client_id" required>
                        <?php foreach (($clients ?? []) as $c): ?>
                            <option
                                value="<?= (int) ($c['id'] ?? 0) ?>"
                                <?= (int) ($c['id'] ?? 0) === (int) ($selectedClientId ?? 0) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="contact-form-grid">
                        <div class="field">
                            <label class="label" for="first_name">Prenom</label>
                            <input class="input" id="first_name" name="first_name" type="text">
                        </div>
                        <div class="field">
                            <label class="label" for="last_name">Nom</label>
                            <input class="input" id="last_name" name="last_name" type="text">
                        </div>
                        <div class="field">
                            <label class="label" for="function_label">Fonction</label>
                            <input class="input" id="function_label" name="function_label" type="text">
                        </div>
                        <div class="field">
                            <label class="label" for="email">Email</label>
                            <input class="input" id="email" name="email" type="email">
                        </div>
                        <div class="field contact-field-full">
                            <label class="label" for="phone">Telephone</label>
                            <input class="input" id="phone" name="phone" type="text">
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <button class="btn btn-primary" type="submit">Enregistrer le contact</button>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/clients', ENT_QUOTES, 'UTF-8') ?>">Retour</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

