<?php
declare(strict_types=1);
$basePath = isset($basePath) && is_string($basePath) ? $basePath : '';
?>
<section class="page">
    <div class="card">
        <div class="card-header card-header-with-back">
            <a
                class="link-back"
                href="<?= htmlspecialchars($basePath . '/hr', ENT_QUOTES, 'UTF-8') ?>"
                aria-label="Retour RH"
                title="Retour RH"
            >
                <svg class="link-back__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </a>
            <div class="card-header-with-back__main">
                <h2>Nouvelle demande</h2>
                <p class="muted">Saisissez votre demande de congés ou d'absence.</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom:16px;"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($basePath . '/hr/leave/create', ENT_QUOTES, 'UTF-8') ?>" class="form" style="max-width:720px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <label class="label" for="type">Type</label>
                <select class="input" id="type" name="type">
                    <option value="conges">Congés</option>
                    <option value="absence">Absence</option>
                </select>

                <label class="label" for="start_date">Date de début</label>
                <input class="input" id="start_date" name="start_date" type="date" required>

                <label class="label" for="end_date">Date de fin</label>
                <input class="input" id="end_date" name="end_date" type="date" required>

                <label class="label" for="reason">Motif</label>
                <input class="input" id="reason" name="reason" type="text" placeholder="Optionnel">

                <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:10px;">
                    <button class="btn btn-primary" type="submit">Envoyer la demande</button>
                    <a class="btn btn-secondary" href="<?= htmlspecialchars($basePath . '/hr', ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</section>
