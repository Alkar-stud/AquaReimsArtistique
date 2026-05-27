<script src="https://unpkg.com/@zxing/library@latest"></script>

<style>
    #preview {
        width: 80%; /* Moins large que 100% */
        height: 80%;
        max-width: 400px; /* Limite la largeur maximale */
        max-height: 600px; /* Limite la hauteur maximale */
        margin: 20px auto;
        display: block;
        border: 2px solid #333;
        border-radius: 5px;
    }
</style>

{% include 'partials/search-form.tpl' %}

<div id="scan-feedback" class="alert alert-warning d-none" role="alert"></div>

<video id="preview" autoplay></video>

<script>

    //Pour désactiver l'autofocus du formulaire de recherche
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            // Supprime l'attribut autofocus
            searchInput.removeAttribute('autofocus');
            // Retire le focus si jamais il est mis par défaut
            searchInput.blur();
        }
    });

    const codeReader = new ZXing.BrowserQRCodeReader();
    const preview = document.getElementById('preview');
    const scanAgainButton = document.getElementById('scanAgain');
    const scanFeedback = document.getElementById('scan-feedback');
    let stream = null;

    function showScanFeedback(message, type = 'warning') {
        if (!scanFeedback) {
            return;
        }

        scanFeedback.className = `alert alert-${type}`;
        scanFeedback.textContent = message;
        scanFeedback.classList.remove('d-none');
    }

    function clearScanFeedback() {
        if (!scanFeedback) {
            return;
        }

        scanFeedback.textContent = '';
        scanFeedback.className = 'alert alert-warning d-none';
    }

    function stopScan() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        codeReader.reset();
    }

    function buildSafeEntranceUrl(scannedText) {
        const text = (scannedText || '').trim();
        if (!text) {
            return null;
        }

        // Autoriser un token brut hexadécimal (format actuel des tokens de réservation).
        if (/^[a-f0-9]{64}$/i.test(text)) {
            return '/entrance?token=' + encodeURIComponent(text);
        }

        try {
            const url = new URL(text, window.location.origin);

            // Un QR ne doit rediriger que vers l'application et uniquement sur /entrance.
            if (url.origin !== window.location.origin || url.pathname !== '/entrance') {
                return null;
            }

            const token = url.searchParams.get('token');
            if (!token) {
                return null;
            }

            return '/entrance?token=' + encodeURIComponent(token);
        } catch {
            return null;
        }
    }

    // Fonction pour démarrer le scan
    async function startScan() {
        try {
            clearScanFeedback();

            // Arrêter le flux existant si présent
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }

            // Demander l'accès à la caméra arrière
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            preview.srcObject = stream;
            preview.style.display = 'block';

            // Démarrer la détection des QR codes
            codeReader.decodeFromVideoDevice(
                null,
                preview,
                (result, error) => {
                    if (result) {
                        const safeUrl = buildSafeEntranceUrl(result.text);
                        if (safeUrl) {
                            stopScan();
                            window.location.href = safeUrl;
                            return;
                        }

                        showScanFeedback('QR code invalide ou hors domaine. Veuillez scanner un QR de réservation généré par l’application.');
                    }
                    if (error) {
                        console.error("Erreur de détection :", error);
                    }
                }
            );
        } catch (error) {
            showScanFeedback("Impossible d'accéder à la caméra : " + error.message, 'danger');
        }
    }

    // Démarrer le scan au chargement de la page
    startScan();

    // Bouton pour relancer le scan
    if (scanAgainButton) {
        scanAgainButton.addEventListener('click', startScan);
    }
</script>