<?php if (isset($_SESSION['flash_message'])): ?>
    <?php $flash = $_SESSION['flash_message']; ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'danger'); ?>">
        <?= htmlspecialchars($flash['message'] ?? ''); ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<div class="container">
    <h2 class="mb-4">Accueil de l'administration</h2>

    <p>Affichera des liens vers les parties accessibles, éventuellement des messages pratiques</p>

    <!-- Statistiques des logs - Uniquement pour les niveaux 0 et 1 -->
    <?php if (isset($logsStats) && isset($_SESSION['user']['role']['level']) && $_SESSION['user']['role']['level'] <= 1): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Fichiers de logs</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logsStats['files'])): ?>
                            <p class="text-muted">Aucun fichier de log trouvé</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>Fichier</th>
                                        <th>Taille</th>
                                        <th>Lignes</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($logsStats['files'] as $file): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($file['name'] ?? 'N/A') ?></td>
                                            <td><?= $this->formatBytes($file['size'] ?? 0) ?></td>
                                            <td><?= number_format($file['lines'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2">
                                <strong>Total: <?= $this->formatBytes($logsStats['total_file_size'] ?? 0) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Base de données MongoDB</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logsStats['mongo'])): ?>
                            <p class="text-muted">Aucune collection trouvée</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>Collection</th>
                                        <th>Documents</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($logsStats['mongo'] as $collection): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($collection['name'] ?? 'N/A') ?></td>
                                            <td><?= number_format($collection['count'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2">
                                <strong>Total: <?= number_format($logsStats['total_mongo_docs'] ?? 0) ?> documents</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>