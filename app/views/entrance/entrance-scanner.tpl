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
    let stream = null;

    // Fonction pour démarrer le scan
    async function startScan() {
        try {
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
                        // Rediriger vers le lien du QR code
                        window.location.href = result.text;
                    }
                    if (error) {
                        console.error("Erreur de détection :", error);
                    }
                }
            );
        } catch (error) {
            alert("Impossible d'accéder à la caméra : " + error.message);
        }
    }

    // Démarrer le scan au chargement de la page
    startScan();

    // Bouton pour relancer le scan
    scanAgainButton.addEventListener('click', startScan);
</script>