/**
 * ZXing QR Code Scanner Module
 * Handles QR code scanning for entrance reservations
 * with security validation and error handling
 */

document.addEventListener('DOMContentLoaded', () => {
    const codeReader = new ZXing.BrowserQRCodeReader();
    const preview = document.getElementById('preview');
    const scanAgainButton = document.getElementById('scanAgain');
    const scanFeedback = document.getElementById('scan-feedback');
    const searchInput = document.getElementById('search-input');
    let stream = null;

    /**
     * Display feedback message to the user
     */
    function showScanFeedback(message, type = 'warning') {
        if (!scanFeedback) {
            return;
        }

        scanFeedback.className = `alert alert-${type}`;
        scanFeedback.textContent = message;
        scanFeedback.classList.remove('d-none');
    }

    /**
     * Clear feedback message
     */
    function clearScanFeedback() {
        if (!scanFeedback) {
            return;
        }

        scanFeedback.textContent = '';
        scanFeedback.className = 'alert alert-warning d-none';
    }

    /**
     * Stop video stream
     */
    function stopScan() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        codeReader.reset();
    }

    /**
     * Validate and build safe entrance URL from scanned QR text
     * Accepts either:
     * - Pure token: 64 hex characters (direct token)
     * - Full URL: /entrance?token=... on same origin
     */
    function buildSafeEntranceUrl(scannedText) {
        const text = (scannedText || '').trim();
        if (!text) {
            return null;
        }

        // Allow pure token (hexadecimal, 64 chars)
        if (/^[a-f0-9]{64}$/i.test(text)) {
            return '/entrance?token=' + encodeURIComponent(text);
        }

        try {
            const url = new URL(text, window.location.origin);

            // QR must redirect only to /entrance on same domain
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

    /**
     * Start QR code scanning from video device
     */
    async function startScan() {
        try {
            clearScanFeedback();

            // Stop existing stream if present
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }

            // Request rear camera access
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            preview.srcObject = stream;
            preview.style.display = 'block';

            // Start QR code detection
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

                        showScanFeedback('QR code invalide ou hors domaine. Veuillez scanner un QR de réservation généré par l\'application.');
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

    /**
     * Disable autofocus on search input to prioritize camera
     */
    if (searchInput) {
        searchInput.removeAttribute('autofocus');
        searchInput.blur();
    }

    // Start scanning on page load
    startScan();

    // Handle scan restart button
    if (scanAgainButton) {
        scanAgainButton.addEventListener('click', startScan);
    }
});

