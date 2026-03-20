<?php
declare(strict_types=1);
// Variables: $permissionDenied, $csrfToken, $projectId, $users, $assignedUserIds
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Affecter une équipe</h2>
            <p class="muted">Chantier ID : #<?= (int) ($projectId ?? 0) ?> (squelette fonctionnel).</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php
                    $basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
                    $assignedSet = [];
                    foreach (($assignedUserIds ?? []) as $uid) {
                        $assignedSet[(int) $uid] = true;
                    }
                ?>

                <form method="POST" action="<?= htmlspecialchars($basePath . '/projects/assign/save', ENT_QUOTES, 'UTF-8') ?>" class="form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="project_id" value="<?= (int) ($projectId ?? 0) ?>">

                    <div class="checkbox-list">
                        <?php foreach (($users ?? []) as $u): ?>
                            <?php $uid = (int) ($u['id'] ?? 0); ?>
                            <label class="checkbox-item">
                                <input
                                    type="checkbox"
                                    name="user_ids[]"
                                    value="<?= $uid ?>"
                                    <?= isset($assignedSet[$uid]) ? 'checked' : '' ?>
                                >
                                <span>
                                    <?= htmlspecialchars((string) ($u['fullName'] ?? $u['email'] ?? 'Utilisateur'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <button class="btn btn-primary" type="submit">Enregistrer l’équipe</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/projects/show?projectId=' . (int) ($projectId ?? 0), ENT_QUOTES, 'UTF-8') ?>">Retour</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

