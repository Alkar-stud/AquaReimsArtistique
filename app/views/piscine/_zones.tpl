<div class="container my-3" data-component="zones-list">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 m-0">Zones</h2>
    </div>

    <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-6">
        {% foreach $zones as $z %}
        <div class="col">
            {% if $z->isOpen() %}
            <div
                    class="card zone-card zone-open text-center h-100"
                    role="button"
                    tabindex="0"
                    data-zone-id="{{ $z->getId() }}"
                    data-piscine-id="{{ $z->getPiscineObject()->getId() }}"
            >
                <div class="card-body py-3">
                    <div class="fw-bold">{{ $z->getZoneName() }}</div>
                    <div class="small text-muted">
                        {{ $z->getNbSeatsVertically() }} rangs × {{ $z->getNbSeatsHorizontally() }} places
                    </div>
                </div>
            </div>
            {% else %}
            <div
                    class="card zone-card zone-closed text-center h-100"
                    aria-disabled="true"
                    data-zone-id="{{ $z->getId() }}"
            >
                <div class="card-body py-3">
                    <div class="fw-bold">{{ $z->getZoneName() }}</div>
                    <div class="small text-muted">
                        {{ $z->getNbSeatsVertically() }} rangs × {{ $z->getNbSeatsHorizontally() }} places
                    </div>
                    <div class="badge bg-secondary mt-2">Fermée</div>
                </div>
            </div>
            {% endif %}
        </div>
        {% endforeach %}
    </div>

    <div class="mt-3">
        <div class="d-flex flex-column flex-md-row text-center small fw-semibold">
            <div class="flex-fill py-2 px-2 bg-light border">
                Côté bassin nordique / extérieur (Zones A &amp; B)
            </div>
            <div class="flex-fill py-2 px-2 bg-light border border-start-0 border-end-0">
                Milieu bassin (Zones C &amp; D)
            </div>
            <div class="flex-fill py-2 px-2 bg-light border">
                Côté bassin aquagym (Zones E &amp; F)
            </div>
        </div>
    </div>
</div>