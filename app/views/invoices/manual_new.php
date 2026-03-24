<?php
declare(strict_types=1);

$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
$project = is_array($project ?? null) ? $project : [];
$projectId = (int) ($projectId ?? 0);
$due = is_string($defaultDueYmd ?? null) ? $defaultDueYmd : '';
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Nouvelle facture manuelle</h2>
            <p class="muted">Affaire : <?= htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="card-body">
            <?php if (is_string($flashError ?? null) && trim((string) $flashError) !== ''): ?>
                <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars(rawurldecode((string) $flashError), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="POST" action="<?= htmlspecialchars($basePath . '/invoices/create-manual', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:520px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="project_id" value="<?= $projectId ?>">
                <label class="label" for="title">Titre</label>
                <input class="input" id="title" name="title" type="text" required placeholder="Ex : Avoir n°…, Facture complémentaire…">
                <label class="label" for="due_date">Date d’échéance</label>
                <input class="input" id="due_date" name="due_date" type="date" required value="<?= htmlspecialchars($due, ENT_QUOTES, 'UTF-8') ?>">
                <label class="label" for="notes">Notes internes</label>
                <textarea class="input" id="notes" name="notes" style="min-height:72px;" placeholder="Optionnel"></textarea>
                <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:14px;">
                    <button class="btn btn-primary" type="submit">Créer et saisir les lignes</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . $projectId, ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>
