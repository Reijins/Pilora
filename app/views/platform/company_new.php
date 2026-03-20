<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Nouvelle société</h2>
            <p class="muted"><a href="<?= htmlspecialchars($basePath . '/platform/companies', ENT_QUOTES, 'UTF-8') ?>">← Retour à la liste</a></p>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($basePath . '/platform/companies/create', ENT_QUOTES, 'UTF-8') ?>" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <label class="label">Nom <span class="muted">*</span></label>
                <input class="input" type="text" name="name" required maxlength="255">

                <label class="label">Email facturation</label>
                <input class="input" type="email" name="billing_email" maxlength="255">

                <label class="label">Statut</label>
                <select class="input" name="status">
                    <option value="active">active</option>
                    <option value="suspended">suspended</option>
                    <option value="disabled">disabled</option>
                </select>

                <div style="margin-top:16px;">
                    <button class="btn btn-primary" type="submit">Créer</button>
                </div>
            </form>
        </div>
    </div>
</section>
