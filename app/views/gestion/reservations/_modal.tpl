<div class="modal fade"
     id="reservationDetailModal"
     tabindex="-1"
     aria-labelledby="reservationDetailModalLabel"
     aria-hidden="true"
     data-is-readonly="{{ $isReadOnly ? 'true' : 'false' }}"
     data-can-update="{{ str_contains($userPermissions, 'U') ? 'true' : 'false' }}">
    >
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reservationDetailModalLabel">Détails de la réservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <form id="reservationDetailForm">
                        <div id="contact-fields-container">
                            <input type="hidden" id="modal_reservation_id" name="reservation_id" value="">
                            <input type="hidden" id="modal_reservation_token" name="reservation_token" value="">
                            <h4>Réservant</h4>
                            <div class="row mb-2">
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="input-group">
                                        <span class="input-group-text">Nom</span>
                                        <input type="text"
                                               id="modal_contact_name"
                                               class="form-control editable-contact"
                                               data-field="name"
                                               value="" {{ $isReadOnly ? 'disabled' : '' }}
                                               aria-label="Nom">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="input-group">
                                        <span class="input-group-text">Prénom</span>
                                        <input type="text"
                                               id="modal_contact_firstname"
                                               class="form-control editable-contact"
                                               data-field="firstname"
                                               value="" {{ $isReadOnly ? 'disabled' : '' }}
                                               aria-label="Prénom">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="input-group">
                                        <span class="input-group-text">Email</span>
                                        <input type="email"
                                               id="modal_contact_email"
                                               class="form-control editable-contact"
                                               data-field="email"
                                               value="{{ isset($reservation) && method_exists($reservation,'getEmail') ? $reservation->getEmail() : '' }}"
                                               aria-label="Email">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="input-group">
                                        <span class="input-group-text">Téléphone</span>
                                        <input type="tel"
                                               id="modal_contact_phone"
                                               class="form-control editable-contact"
                                               data-field="phone"
                                               value="" {{ $isReadOnly ? 'disabled' : '' }}
                                               aria-label="Téléphone"
                                               placeholder="Facultatif">
                                        <span class="input-group-text feedback-span"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <!-- section pour le paiement -->
                        <div id="modal-payment-section">
                            <h4>Paiements</h4>
                            à faire: afficher la liste des différentes informations de paiement (dont bouton refresh et remboursement)
                            <div id="modal-payment-list" class="d-flex justify-content-around text-center p-2 bg-light rounded">
                                <div>
                                    <strong>Coût total</strong><br>
                                    <span id="modal-total-cost">0,00</span> €
                                </div>
                                <div>
                                    <strong>Montant payé</strong><br>
                                    <span id="modal-amount-paid" class="text-success">0,00</span> €
                                </div>
                                <div>
                                    <strong>Reste à payer</strong><br>
                                    <span id="modal-amount-due" class="text-danger">0,00</span> €
                                </div>
                                <div>
                                    <strong>Dons</strong><br>
                                    <span id="modal-don-amount" class="text-info">0,00</span> €
                                </div>
                                <div id="div-modal-mark-as-paid" class="d-none">
                                    <strong>Marquer comme payé</strong><br>
                                    <span id="modal-mark-as-paid" style="cursor: pointer;" title="Marquer la réservation comme entièrement payée"><i class="bi bi-check fs-4"></i></span>
                                </div>
                            </div>
                            <div class="text-end mt-1">
                                <a href="#" id="toggle-payment-details" class="small" style="display: none;">Voir le détail des paiements</a>
                            </div>
                            <div id="modal-payment-details-container" class="mt-2" style="display: none;">
                                <!-- Les détails des paiements seront injectés ici par JavaScript -->
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Section pour les participants -->
                        <div id="modal-participants-section">
                            <h4>Participants</h4>
                            <div id="participants-container" class="list-group text-start">
                                <!-- Le contenu sera injecté ici par JavaScript -->
                            </div>
                        </div>

                        <!-- Section pour les compléments -->
                        <div id="modal-complements-section" style="display: none;">
                            <h4>Compléments</h4>
                            <div id="complements-container" class="list-group text-start">
                                <!-- Le contenu sera injecté ici par JavaScript -->
                            </div>
                        </div>

                    </form>
                    {% if str_contains($userPermissions, 'D') %}
                    <br>
                    <div id="token-container">
                        <div>
                            Réinit token : <span id="modal-reset-token" class="fw-bold"></span> <i class="bi bi-arrow-clockwise" title="Réinit token"></i>
                        </div>
                        <div class="ms-md-auto w-100 w-md-auto">
                            <div class="input-group">
                                <span class="input-group-text">Token expire à</span>
                                <input type="datetime-local"
                                       id="modal-modification-token-expire-at"
                                       class="form-control"
                                       data-field="tokenExpireAt"
                                       value="" {{ $isReadOnly ? 'disabled' : '' }}
                                       aria-label="Token expire à">
                                <span class="input-group-text feedback-span"></span>
                            </div>
                        </div>
                    </div>
                    {% endif %}
                </div>
            </div>

            <div class="modal-footer">
                <div class="row g-2 w-100 align-items-md-center">
                    <div class="col-12 col-md-auto">
                        <div class="d-grid gap-2 d-sm-flex">
                            {% if str_contains($userPermissions, 'D') %}
                            <button type="button" id="modal-reservation-delete-btn" class="btn btn-danger">
                                <i class="bi bi-trash"></i>&nbsp;Supprimer la réservation
                            </button>
                            {% endif %}
                            {% if str_contains($userPermissions, 'U') %}
                            <button type="button" id="modal-reservation-cancel-btn" class="btn btn-warning" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i>&nbsp;Annuler la réservation
                            </button>
                            {% endif %}
                        </div>
                    </div>

                    <div class="col-12 col-md-auto ms-md-auto">
                        <div class="d-grid gap-2 d-sm-flex justify-content-md-end">
                            {% if str_contains($userPermissions, 'U') %}
                            <button type="submit" class="btn btn-info" id="modal-save-and-toggle-checked-btn">
                                <i class="bi bi-check"></i>&nbsp;Enregistrer et marquer comme vérifié
                            </button>
                            {% endif %}
                            <button type="button" id="modal-close-btn" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
