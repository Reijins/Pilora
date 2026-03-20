<?php
declare(strict_types=1);
// Variables: permissionDenied, canCreate, csrfToken, items, flashMessage, flashError
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Bibliothèque de prestations</h2>
            <p class="muted">Catalogue des prestations réutilisables.</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php if (!empty($flashError)): ?>
                    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-success" style="margin-bottom:12px; border-color: var(--success); background: rgba(22,163,74,.08); color: var(--success);">
                        <?= htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prix unitaire</th>
                            <th>Temps estimé (min)</th>
                            <th>Statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($it['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($it['unitPrice'] ?? '0'), ENT_QUOTES, 'UTF-8') ?> €</td>
                                    <td><?= htmlspecialchars((string) ($it['estimatedTimeMinutes'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge"><?= htmlspecialchars((string) ($it['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="muted">Aucune prestation.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($canCreate)): ?>
                    <div style="height:16px;"></div>
                    <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                    <details class="create-prestation-details">
                        <summary class="btn btn-primary">Créer une prestation</summary>

                        <div class="create-prestation-body">
                            <form method="POST" action="<?= htmlspecialchars($basePath . '/price-library/create', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                                <label class="label" for="name">Nom</label>
                                <input class="input" id="name" name="name" type="text" required>

                                <label class="label" for="description">Description</label>
                                <input class="input" id="description" name="description" type="text">

                                <label class="label" for="unit_label">Unité</label>
                                <input class="input" id="unit_label" name="unit_label" type="text" placeholder="Ex: m²">

                                <label class="label" for="unit_price">Prix unitaire (€)</label>
                                <input class="input" id="unit_price" name="unit_price" type="number" step="0.01" min="0" value="0" required>

                                <label class="label" for="estimated_time_minutes">Temps estimé (minutes)</label>
                                <input class="input" id="estimated_time_minutes" name="estimated_time_minutes" type="number" min="0">

                                <label class="label" for="status">Statut</label>
                                <select class="input" id="status" name="status">
                                    <option value="active">Actif</option>
                                    <option value="inactive">Inactif</option>
                                </select>

                                <button class="btn btn-primary" type="submit">Enregistrer</button>
                            </form>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

