<!-- language: html -->
<div class="modal fade"
     id="reservation-incoming-modal"
     tabindex="-1"
     aria-labelledby="reservationIncomingModalLabel"
     aria-hidden="true"
     data-is-readonly="{{ $isReadOnly ? 'true' : 'false' }}"
     data-can-update="{{ str_contains($userPermissions, 'U') ? 'true' : 'false' }}">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reservationIncomingModalLabel">Détails de la réservation (à venir)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <div class="text-center">
                    <form id="reservationIncomingForm">
                        <div id="contact-fields-container">
                            <input type="hidden" id="incoming_reservation_id" name="reservation_id" value="">
                            <input type="hidden" id="incoming_is_locked" name="is_locked" value="">
                            <input type="hidden" id="incoming_reservation_token" name="reservation_token" value="">
                            <h4>Réservant</h4>

                            <!-- Champs attendus par reservationTemp.js -->
                            <div class="row mb-3 text-start">
                                <div class="col-12">
                                    <strong>Nom / Prénom :</strong>
                                    <div id="incoming-name"></div>
                                </div>
                            </div>
                            <div class="row mb-2 text-start">
                                <div class="col-md-6">
                                    <strong>Email :</strong>
                                    <div id="incoming-email"></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Téléphone :</strong>
                                    <div id="incoming-phone"></div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Montant total -->
                        <div id="incoming-amount-section" class="text-start">
                            <h4>Montant</h4>
                            <div id="incoming-amount" class="fw-bold"></div>
                        </div>

                        <hr class="my-4">

                        <!-- Participants -->
                        <div id="incoming-participants-section" class="text-start">
                            <h4>Participants</h4>
                            <div id="incoming-details"></div>
                        </div>

                        <!-- Compléments -->
                        <div id="incoming-complements-section" class="text-start" style="display: none;">
                            <h4>Compléments</h4>
                            <div id="incoming-complements"></div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal-footer">
                <div class="d-grid gap-2 d-sm-flex justify-content-md-end w-100">
                    <button type="button" id="incoming-close-btn" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>

        </div>
    </div>
</div>
