<div class="container-fluid">
    <h2 class="mb-4">Bienvenue dans la gestion de la billetterie des galas ARA</h2>

    <div class="row">
        <!-- ================================================================== -->
        <!-- ENCART STATISTIQUES RESERVATIONS -->
        <!-- ================================================================== -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-bar-chart-line-fill"></i> Réservations des prochains galas</h5>
                </div>
                <div class="card-body">
                    {% if !empty($reservationStats) %}
                    <ul class="list-group list-group-flush">
                        {% foreach $reservationStats as $stat %}
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <a href="/gestion/reservations?s={{ $stat['sessionId'] }}" class="text-decoration-none">{{ $stat['eventName'] }}</a>
                                <small class="d-block text-muted">{{ $stat['sessionName'] }} - {{ (new DateTime($stat['sessionDate']))->format('d/m/Y H:i') }}</small>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-primary rounded-pill me-1">{{ $stat['reservationCount'] }} R</span>
                                <span class="badge bg-secondary rounded-pill me-2">{{ $stat['nbSeatCount'] }} P</span>
                                <a href="/gestion/reservations?tab=extract&s={{ $stat['sessionId'] }}" class="text-dark" title="Exporter les réservations"><i class="bi bi-box-arrow-down"></i></a>
                            </div>
                        </li>
                        {% endforeach %}
                    </ul>
                    {% else %}
                    <div class="alert alert-secondary">Aucune réservation pour les sessions à venir.</div>
                    {% endif %}
                </div>
            </div>
        </div>

        <!-- ================================================================== -->
        <!-- ENCART PROCHAINS EVENEMENTS -->
        <!-- ================================================================== -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-calendar-event-fill"></i> Prochains Événements</h5>
                </div>
                <div class="card-body">
                    {% if !empty($upcomingEvents) %}
                    {% foreach $upcomingEvents as $event %}
                    <h6><a href="/gestion/events" }}" class="text-decoration-none">{{ $event['name'] }}</a></h6>
                    <ul class="list-unstyled ps-3">
                        {% foreach $event['sessions'] as $session %}
                        <li>
                            <strong>{{ $session['name'] }}</strong> le {{ (new DateTime($session['date']))->format('d/m/Y à H:i') }}
                        </li>
                        {% endforeach %}
                    </ul>
                    <p class="mb-1 mt-2"><small class="text-muted">Périodes d'inscription :</small></p>
                    <ul class="list-unstyled ps-3">
                        {% foreach $event['registrationPeriods'] as $period %}
                        <li>
                            <small>
                                <i class="bi bi-clock-history"></i> {{ $period['name'] }}: du {{ (new DateTime($period['start']))->format('d/m') }} au {{ (new DateTime($period['end']))->format('d/m/Y') }}
                            </small>
                        </li>
                        {% endforeach %}
                    </ul>
                    <hr>
                    {% endforeach %}
                    {% else %}
                    <div class="alert alert-secondary">Aucun événement programmé.</div>
                    {% endif %}
                </div>
            </div>
        </div>

        <!-- Pour ajouter un nouvel encart, il suffit d'ajouter un nouveau bloc <div class="col-12 col-lg-6 mb-4">...</div> ici -->

    </div>



</div>