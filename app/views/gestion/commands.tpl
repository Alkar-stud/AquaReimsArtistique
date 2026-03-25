<div class="container-fluid">
    <h2>Gestion des commandes</h2>
    <p class="text-muted">Liste des commandes administratives disponibles</p>

    <div class="row g-3">
        {% foreach $commands as $command %}
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 {{ $command['danger'] ? 'border-danger' : 'border-primary' }}">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi {{ $command['icon'] }}"></i>
                        {{ $command['name'] }}
                    </h5>
                    <p class="card-text">{{ $command['description'] }}</p>
                    {% if isset($command['info']) %}
                    <div class="alert alert-info small mb-0">
                        {{! $command['info'] !}}
                    </div>
                    {% endif %}
                    {% if isset($command['input']) %}
                    <div class="mb-3">
                        <label for="input-{{ $command['name'] | escape('html_attr') }}" class="form-label">
                            {% if $command['input']['type'] == 'number' %}
                            Nombre à traiter (max {{ $command['input']['max'] }})
                            {% else %}
                            Paramètre
                            {% endif %}
                        </label>
                        <input
                                type="{{ $command['input']['type'] }}"
                                class="form-control command-input"
                                id="input-{{ $command['name'] | escape('html_attr') }}"
                                name="input-{{ $command['name'] | escape('html_attr') }}"
                                {% if isset($command['input']['max']) %}max="{{ $command['input']['max'] }}"{% endif %}
                                {% if isset($command['input']['min']) %}min="{{ $command['input']['min'] }}"{% endif %}
                                {% if isset($command['input']['placeholder']) %}placeholder="{{ $command['input']['placeholder'] }}"{% endif %}
                                {% if isset($command['input']['value']) %}value="{{ $command['input']['value'] }}"{% endif %}
                        >
                    </div>
                    {% endif %}
                </div>
                <div class="card-footer">
                    <button class="btn {{ $command['danger'] ? 'btn-danger' : 'btn-primary' }} w-100 execute-command"
                    data-url="{{ $command['url'] }}"
                    data-name="{{ $command['name'] }}"
                    >
                    <i class="bi bi-play-circle"></i>&nbsp;Exécuter
                    </button>
                </div>
            </div>
        </div>
        {% endforeach %}
    </div>

    <!-- Modal de résultat -->
    <div class="modal fade" id="resultModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Résultat de l'exécution</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="resultContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="module" src="/assets/js/gestion/commands.js"></script>
