{% php %}
$checkoutIntentId = $_GET['checkoutIntentId'] ?? null;
{% endphp %}

<div id="payment-check-container"
     class="container d-flex flex-column align-items-center justify-content-center"
     style="min-height: 60vh;"
     data-checkout-id="{{ $checkoutIntentId }}">

    <div class="spinner-border text-primary mb-4" style="width: 6rem; height: 6rem;" role="status" id="payment-check-spinner">
        <span class="visually-hidden">Vérification...</span>
    </div>
    <div class="message text-center fs-4 mb-2" id="payment-check-message">Vérification du paiement en cours...</div>
    <div class="text-danger" id="merci-error"></div>
    <div id="payment-check-success" class="alert alert-success mt-3" style="display: none;">
        Paiement confirmé ! Vous allez être redirigé vers la page de confirmation.
    </div>
</div>

<script type="module" src="/assets/js/reservations/paymentSuccess.js"></script>