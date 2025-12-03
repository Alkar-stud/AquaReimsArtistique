<div id="reservation-incoming-modal" class="modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Réservation en cours</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <!-- Identité -->
                <div class="mb-3">
                    <div class="fw-bold small text-uppercase">Réservant</div>
                    <div id="incoming-name"></div>
                    <div id="incoming-email" class="text-muted small"></div>
                    <div id="incoming-phone" class="text-muted small"></div>
                </div>

                <!-- Détails (participants / compléments) -->
                <div class="mb-3">
                    <div class="fw-bold small text-uppercase">Contenu du panier</div>
                    <div id="incoming-details"></div>
                    <div id="incoming-complements"></div>
                </div>

                <!-- Montants -->
                <div class="mb-3">
                    <div class="fw-bold small text-uppercase">Montant</div>
                    <div id="incoming-amount" class="h6"></div>
                </div>

                <!-- Mails (inclut relance paiement) -->
                <hr class="my-4">
                <!-- section pour les mails -->
                <div id="modal-mail_sent-section">
                    <h4>Mails</h4>
                    {% if !empty($emailsTemplatesToSendManually) %}
                    <div id="modal-mail-manual-send" class="mt-3">
                        <div class="input-group">
                            <label class="input-group-text" for="modal-mail-template-selector">Modèle</label>
                            <select id="modal-mail-template-selector" class="form-select" {{ $isReadOnly ? 'disabled' : '' }}>
                                <option value="" selected disabled>— Choisir un modèle de mail à envoyer —</option>
                                {% foreach $emailsTemplatesToSendManually as $tpl %}
                                <option value="{{ $tpl->getCode() }}">{{ $tpl->getCode() }} — {{ $tpl->getSubject() }}</option>
                                {% endforeach %}
                            </select>
                            <button class="btn btn-primary" id="modal-send-mail-template-btn" {{ $isReadOnly ? 'disabled' : '' }}>
                                <i class="bi bi-envelope"></i>&nbsp;Envoyer
                            </button>
                        </div>
                        <div class="form-text" id="send-email-dialog">Le mail sera envoyé au réservant.</div>
                    </div>
                    {% else %}
                    <div class="alert alert-warning mt-3">Aucun modèle de mail manuel disponible.</div>
                    {% endif %}
                    <div id="modal-mail_sent-details-container" class="mt-2" style="display: none;">
                        <!-- Les détails des mails seront injectés ici par JavaScript -->
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
