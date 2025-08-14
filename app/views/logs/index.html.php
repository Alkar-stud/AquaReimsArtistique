<div class="container-fluid">
    <h1>Visualisation des logs</h1>

    <!-- Filtres - Responsive -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2 col-6">
                    <label for="type" class="form-label">Type</label>
                    <select name="type" id="type" class="form-select">
                        <option value="all" <?= $filters['type'] === 'all' ? 'selected' : '' ?>>Tous</option>
                        <?php foreach ($logTypes as $logType): ?>
                            <option value="<?= $logType->value ?>" <?= $filters['type'] === $logType->value ? 'selected' : '' ?>>
                                <?= ucfirst($logType->value) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-6">
                    <label for="level" class="form-label">Niveau</label>
                    <select name="level" id="level" class="form-select">
                        <option value="all" <?= $filters['level'] === 'all' ? 'selected' : '' ?>>Tous</option>
                        <?php foreach ($availableLevels as $availableLevel): ?>
                            <option value="<?= $availableLevel ?>" <?= $filters['level'] === $availableLevel ? 'selected' : '' ?>>
                                <?= $availableLevel ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-6">
                    <label for="date_start" class="form-label">Début</label>
                    <input type="date" name="date_start" id="date_start" class="form-control"
                           value="<?= $filters['date_start'] ?>">
                </div>

                <div class="col-md-2 col-6">
                    <label for="time_start" class="form-label">Heure début</label>
                    <input type="time" name="time_start" id="time_start" class="form-control"
                           value="<?= $filters['time_start'] ?>">
                </div>

                <div class="col-md-2 col-6">
                    <label for="date_end" class="form-label">Fin</label>
                    <input type="date" name="date_end" id="date_end" class="form-control"
                           value="<?= $filters['date_end'] ?>">
                </div>

                <div class="col-md-2 col-6">
                    <label for="time_end" class="form-label">Heure fin</label>
                    <input type="time" name="time_end" id="time_end" class="form-control"
                           value="<?= $filters['time_end'] ?>">
                </div>

                <div class="col-md-2 col-6">
                    <label for="per_page" class="form-label">Par page</label>
                    <select name="per_page" id="per_page" class="form-select">
                        <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100</option>
                        <option value="200" <?= $perPage === 200 ? 'selected' : '' ?>>200</option>
                        <option value="500" <?= $perPage === 500 ? 'selected' : '' ?>>500</option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="exclude_logs_route" id="exclude_logs_route" value="1"
                            <?= $filters['exclude_logs_route'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="exclude_logs_route">
                            Exclure les logs de consultation des logs (/gestion/logs)
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary me-2">Filtrer</button>
                    <a href="/gestion/logs" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Informations -->
    <div class="alert alert-info">
        <strong><?= number_format($totalLogs) ?></strong> logs trouvés
        <span class="d-none d-md-inline">(page <?= $currentPage ?> sur <?= $totalPages ?>)</span>
        <div class="d-md-none small">Page <?= $currentPage ?>/<?= $totalPages ?></div>
    </div>

    <!-- Affichage mobile -->
    <div class="d-md-none mb-4">
        <?php if (empty($logs)): ?>
            <div class="card">
                <div class="card-body text-center text-muted">
                    Aucun log trouvé
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-<?= $this->getLogTypeBadgeColor($log['type']) ?>">
                                <?= ucfirst($log['type']) ?>
                            </span>
                            <span class="badge bg-<?= $this->getLogLevelBadgeColor($log['level'] ?? 'INFO') ?>">
                                    <?= $log['level'] ?? 'INFO' ?>
                            </span>
                        </div>

                        <div class="small text-muted mb-2">
                            <?= date('d/m/Y H:i:s', strtotime($log['timestamp'])) ?>
                            <span class="ms-2">IP: <?= htmlspecialchars($log['ip'] ?? 'unknown') ?></span>
                        </div>

                        <!-- Affichage de l'utilisateur si disponible -->
                        <?php if (!empty($log['username']) || !empty($log['user_id'])): ?>
                            <div class="small text-muted mb-2">
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars($log['username'] ?? 'ID:' . $log['user_id']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-2">
                            <?= htmlspecialchars($log['message']) ?>
                        </div>

                        <?php if (!empty($log['context'])): ?>
                            <button class="btn btn-sm btn-outline-info"
                                    onclick="showContext(<?= htmlspecialchars(json_encode($log)) ?>)">
                                Détails
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Affichage desktop -->
    <div class="d-none d-md-block">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                        <tr>
                            <th>Date/Heure</th>
                            <th>Type</th>
                            <th>Niveau</th>
                            <th>IP</th>
                            <th>Utilisateur</th>
                            <th>Message</th>
                            <th>-</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Aucun log trouvé</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-nowrap"><?= $log['timestamp'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $this->getLogTypeBadgeColor($log['type']) ?>">
                                            <?= ucfirst($log['type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $this->getLogLevelBadgeColor($log['level'] ?? 'INFO') ?>">
                                                <?= $log['level'] ?? 'INFO' ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <small><?= htmlspecialchars($log['ip'] ?? 'unknown') ?></small>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if (!empty($log['username']) || !empty($log['user_id'])): ?>
                                            <small>
                                                <?= htmlspecialchars($log['username'] ?? 'ID:' . $log['user_id']) ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">Anonyme</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($log['message']) ?></td>
                                    <td>
                                        <?php if (!empty($log['context'])): ?>
                                            <button class="btn btn-sm btn-outline-info"
                                                    onclick="showContext(<?= htmlspecialchars(json_encode($log)) ?>)">
                                                Détails
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination responsive -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Navigation des logs" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>">
                            <span class="d-none d-md-inline">Précédent</span>
                            <span class="d-md-none">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Desktop: affiche plusieurs pages -->
                <div class="d-none d-md-flex">
                    <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </div>

                <!-- Mobile: affiche seulement la page actuelle -->
                <div class="d-md-none">
                    <li class="page-item active">
                        <span class="page-link"><?= $currentPage ?>/<?= $totalPages ?></span>
                    </li>
                </div>

                <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>">
                            <span class="d-none d-md-inline">Suivant</span>
                            <span class="d-md-none">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Modal pour le contexte complet -->
<div class="modal fade" id="contextModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Données complètes du log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="contextContent" class="bg-dark text-light p-3 rounded" style="max-height: 600px; overflow-y: auto; font-size: 12px;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
    function showContext(logData) {
        document.getElementById('contextContent').textContent = JSON.stringify(logData, null, 2);
        new bootstrap.Modal(document.getElementById('contextModal')).show();
    }
</script>