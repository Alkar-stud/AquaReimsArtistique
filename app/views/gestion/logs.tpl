<div class="container-fluid">
    <h2 class="mb-4">Visualiseur de Logs</h2>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/gestion/logs" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="filter-level" class="form-label">Niveau</label>
                    <select id="filter-level" name="level" class="form-select">
                        <option value="">Tous</option>
                        {% foreach $logLevels as $level %}
                        <option value="{{ $level }}" {{ ($filters['level'] ?? '') == $level ? 'selected' : '' }}>{{ $level }}</option>
                        {% endforeach %}
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter-channel" class="form-label">Channel</label>
                    <select id="filter-channel" name="channel" class="form-select">
                        <option value="">Tous</option>
                        {% foreach $logChannels as $channel %}
                        <option value="{{ $channel }}" {{ ($filters['channel'] ?? '') == $channel ? 'selected' : '' }}>{{ $channel }}</option>
                        {% endforeach %}
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter-ip" class="form-label">Adresse IP</label>
                    <input type="text" id="filter-ip" name="ip" class="form-control" value="{{ $filters['ip'] ?? '' }}" placeholder="127.0.0.1">
                </div>
                <div class="col-md-2">
                    <label for="filter-user" class="form-label">Utilisateur ID</label>
                    <input type="text" id="filter-user" name="user" class="form-control" value="{{ $filters['user'] ?? '' }}" placeholder="123 ou anonymous">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover table-striped">
            <thead class="table-light">
            <tr>
                <th style="width: 160px;">Date</th>
                <th style="width: 100px;">Niveau</th>
                <th style="width: 120px;">Channel</th>
                <th>Message</th>
                <th>URL</th>
                <th>Requête</th>
                <th style="width: 130px;">IP</th>
                <th style="width: 120px;">Utilisateur</th>
            </tr>
            </thead>
            <tbody>
            {% if $logs %}
            {% foreach $logs_loop as $loop %}
            {% php %}
            $log = $loop['item'];
            $level_class = match (strtoupper($log['level'] ?? '')) {
            'CRITICAL', 'ALERT', 'EMERGENCY' => 'table-danger',
            'ERROR' => 'table-warning',
            'WARNING' => 'table-info',
            'NOTICE' => 'table-primary',
            default => ''
            };
            $badge_class = $level_class ? str_replace('table-', '', $level_class) : 'secondary';
            $ctx_json = '';
            if (!empty($log['context'])) {
            $ctx_json = json_encode($log['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            {% endphp %}
            <tr class="{{ $level_class ?? '' }}">
                <td>{{ $log['ts']|date('Y-m-d H:i:s') }}</td>
                <td>
                    <span class="badge bg-{{ $badge_class }}">{{ $log['level'] ?? 'N/A' }}</span>
                </td>
                <td>{{ $log['channel'] ?? 'N/A' }}</td>
                <td>
                    <small>{{ $log['message'] ?? '' }}</small>
                    {% if !empty($log['context']) %}
                    <a class="ms-2" data-bs-toggle="collapse" href="#context-{{ $loop['index'] }}" role="button" aria-expanded="false" aria-controls="context-{{ $loop['index'] }}">
                        <i class="bi bi-plus-circle"></i>
                    </a>
                    <div class="collapse" id="context-{{ $loop['index'] }}">
                        <pre class="bg-light p-2 rounded small" style="white-space: pre-wrap; word-break: break-all;">{{ $ctx_json }}</pre>
                    </div>
                    {% endif %}
                </td>
                <td>{{ $log['uri'] ?? '-' }}</td>
                <td>{{ $log['context']['query'] ?? '-' }}</td>
                <td>{{ $log['ip'] ?? 'N/A' }}</td>
                <td>
                    {% if !empty($log['user_id']) %}
                    {% if !empty($log['username']) %}
                    {{ $log['username'] }} ({{ $log['user_id'] }})
                    {% else %}
                    {{ $log['user_id'] }}
                    {% endif %}
                    {% else %}
                    <span class="text-muted">N/A</span>
                    {% endif %}
                </td>
            </tr>
            {% endforeach %}
            {% else %}
            <tr>
                <td colspan="6" class="text-center p-4">Aucun log trouvé pour les filtres sélectionnés.</td>
            </tr>
            {% endif %}
            </tbody>
        </table>
    </div>

    {% if $pagination['totalPages'] > 1 %}
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item {{ $pagination['currentPage'] == 1 ? 'disabled' : '' }}">
                <a class="page-link" href="?page={{ $pagination['currentPage'] - 1 }}&{{ http_build_query($filters) }}">Précédent</a>
            </li>
            {% for $i in 1..$pagination['totalPages'] %}
            {% if $i == $pagination['currentPage'] %}
            <li class="page-item active" aria-current="page"><span class="page-link">{{ $i }}</span></li>
            {% elseif $i == 1 or $i == $pagination['totalPages'] or ($i >= $pagination['currentPage'] - 2 and $i <= $pagination['currentPage'] + 2) %}
            <li class="page-item"><a class="page-link" href="?page={{ $i }}&{{ http_build_query($filters) }}">{{ $i }}</a></li>
            {% elseif $i == $pagination['currentPage'] - 3 or $i == $pagination['currentPage'] + 3 %}
            <li class="page-item disabled"><span class="page-link">...</span></li>
            {% endif %}
            {% endfor %}
            <li class="page-item {{ $pagination['currentPage'] == $pagination['totalPages'] ? 'disabled' : '' }}">
                <a class="page-link" href="?page={{ $pagination['currentPage'] + 1 }}&{{ http_build_query($filters) }}">Suivant</a>
            </li>
        </ul>
    </nav>
    {% endif %}

    <p class="text-center text-muted">Page {{ $pagination['currentPage'] }} sur {{ $pagination['totalPages'] }}. Total de {{ $pagination['totalResults'] }} logs.</p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const setIcon = (btn, open) => {
            const i = btn.querySelector('i.bi');
            if (!i) return;
            i.classList.toggle('bi-plus-circle', !open);
            i.classList.toggle('bi-dash-circle', open);
        };

        // Couvre les boutons Bootstrap Collapse et, à défaut, .js-toggle + data-target
        const toggles = document.querySelectorAll('[data-bs-toggle="collapse"], .js-toggle');
        toggles.forEach(btn => {
            const targetSel =
                btn.getAttribute('data-bs-target') ||
                btn.getAttribute('href') ||
                btn.getAttribute('data-target');
            const target = targetSel ? document.querySelector(targetSel) : null;

            if (target && typeof bootstrap !== 'undefined' && target.classList.contains('collapse')) {
                // Synchronise l’icône sur les événements Bootstrap
                target.addEventListener('shown.bs.collapse', () => setIcon(btn, true));
                target.addEventListener('hidden.bs.collapse', () => setIcon(btn, false));
                // État initial
                setIcon(btn, target.classList.contains('show'));
            } else if (target) {
                // Fallback sans Bootstrap: toggle display
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const open = !target.classList.contains('is-open');
                    target.classList.toggle('is-open', open);
                    target.style.display = open ? '' : 'none';
                    setIcon(btn, open);
                });
                // État initial
                const open = target.classList.contains('is-open') || target.style.display !== 'none';
                setIcon(btn, open);
            }
        });
    });
</script>
