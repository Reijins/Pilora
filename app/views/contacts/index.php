<?php
declare(strict_types=1);
// Variables: $permissionDenied, $clientId, $contacts
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Contacts</h2>
            <p class="muted">Fiches contact liées au client (squelette).</p>
        </div>
        <div class="card-body">
            <div class="inline-actions">
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <a class="link-secondary" href="<?= htmlspecialchars($basePath . '/clients', ENT_QUOTES, 'UTF-8') ?>">Retour aux Clients</a>
                <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/contacts/new' . (!empty($clientId) ? ('?clientId=' . (int) $clientId) : ''), ENT_QUOTES, 'UTF-8') ?>">Nouveau contact</a>
                <?php if (!empty($clientId)): ?>
                    <span class="muted">Client ID : #<?= (int) $clientId ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger" style="margin-top:12px;">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <div style="height:12px;"></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Fonction</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($contacts)): ?>
                                <?php foreach ($contacts as $contact): ?>
                                    <?php
                                        $fullName = trim(((string)($contact['firstName'] ?? '')) . ' ' . ((string)($contact['lastName'] ?? '')));
                                        if ($fullName === '') { $fullName = '—'; }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($contact['functionLabel'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($contact['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($contact['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="muted">Aucun contact pour ce client.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

