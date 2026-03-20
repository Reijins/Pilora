<?php
declare(strict_types=1);
// Variables: $permissionDenied, $searchQuery, $clients
?>
<section class="page">
    <div class="card">
        <div class="card-header">
            <h2>Clients</h2>
            <p class="muted">Recherche rapide et accès aux fiches clients (squelette).</p>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionDenied)): ?>
                <div class="alert alert-danger">Accès refusé : permissions insuffisantes.</div>
            <?php else: ?>
                <?php $basePath = isset($basePath) && is_string($basePath) ? $basePath : ''; ?>
                <form method="GET" action="<?= htmlspecialchars($basePath . '/clients', ENT_QUOTES, 'UTF-8') ?>" class="search-bar search-bar-lg">
                    <input class="input" type="text" name="q" value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom, téléphone, email">
                    <button class="btn btn-primary" type="submit">Rechercher</button>
                </form>

                <div style="height:12px;"></div>

                <?php if (!empty($canCreateClient)): ?>
                    <div style="margin-bottom:12px;">
                        <a class="btn btn-primary" href="<?= htmlspecialchars($basePath . '/clients/new', ENT_QUOTES, 'UTF-8') ?>">Nouveau client</a>
                    </div>
                <?php endif; ?>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Téléphone</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($clients)): ?>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($client['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($client['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($client['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div style="display:flex; flex-wrap:wrap; gap:12px;">
                                                <a class="link-action" href="<?= htmlspecialchars($basePath . '/clients/show?clientId=' . (int) $client['id'], ENT_QUOTES, 'UTF-8') ?>">Ouvrir fiche</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="muted">Aucun client trouvé.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

