import {apiPost} from "./components/apiClient.js";

document.addEventListener('DOMContentLoaded', () => {

    const toggleComplementsBtn = document.querySelector('[data-action="toggle-complements"]');
    const reservationId = toggleComplementsBtn.dataset.reservationId;
    const participantItems = document.querySelectorAll('[data-action="toggle-participant"]');
    const everyonePresentBadge = document.getElementById('every-one-is-present');
    const checkReservationCheckbox = document.querySelector('[data-action="check-reservation"]');

    /*
     * Section pour vérifier la commande
     */
    if (checkReservationCheckbox) {
        checkReservationCheckbox.addEventListener('change', async () => {
            if (!checkReservationCheckbox.checked) return;

            // Demander confirmation
            if (!confirm('Êtes-vous sûr de vouloir marquer cette réservation comme vérifiée ?')) {
                checkReservationCheckbox.checked = false;
                return;
            }

                checkReservationCheckbox.disabled = true;

            try {
                const result = await apiPost('/entrance/update/' + reservationId, {
                    is_checked: true
                });

                if (result.success) {
                    // Recharger la page
                    window.location.reload();
                } else {
                    throw new Error(result.message || 'Une erreur est survenue.');
                }
            } catch (error) {
                alert(`Erreur : ${error.message}`);
                checkReservationCheckbox.checked = false;
                checkReservationCheckbox.disabled = false;
            }
        });
    }

    /*
     * Section pour valider la remise des compléments
     */
    if (toggleComplementsBtn) {
        toggleComplementsBtn.addEventListener('click', async () => {
            const isGiven = toggleComplementsBtn.dataset.complementGiven === 'true';
            // Désactiver le bouton et afficher un spinner
            const originalIcon = toggleComplementsBtn.innerHTML;
            toggleComplementsBtn.style.pointerEvents = 'none'; // Désactive les clics sur le span
            toggleComplementsBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const result = await apiPost('/entrance/update/' + reservationId, {
                    complement: !isGiven // Inverse l'état
                });
                if (result.success) {
                    // Mettre à jour l'état et le visuel
                    toggleComplementsBtn.dataset.complementGiven = (!isGiven).toString();
                    if (!isGiven) {
                        toggleComplementsBtn.classList.remove('btn-outline-primary');
                        toggleComplementsBtn.classList.add('btn-success');
                        toggleComplementsBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i>&nbsp;Remis';
                    } else {
                        toggleComplementsBtn.classList.remove('btn-success');
                        toggleComplementsBtn.classList.add('btn-outline-primary');
                        toggleComplementsBtn.innerHTML = '<i class="bi bi-circle"></i>&nbsp;À remettre';
                    }
                    // Réactiver le bouton après succès
                    toggleComplementsBtn.style.pointerEvents = 'auto';
                } else {
                    // Si l'API retourne une erreur contrôlée
                    throw new Error(result.message || 'Une erreur est survenue.');
                }
            } catch (error) {
                alert(`Erreur : ${error.message}`);
                // Restaurer l'icône et réactiver le bouton en cas d'erreur
                toggleComplementsBtn.style.pointerEvents = 'auto';
                toggleComplementsBtn.innerHTML = originalIcon;
            }
        });
    }

    /*
     * Section pour valider la présence des participants
     */
    participantItems.forEach(item => {
        item.addEventListener('click', async () => {
            const detailId = item.dataset.detailId;
            const isPresent = item.classList.contains('list-group-item-success');

            const originalContent = item.innerHTML;
            item.style.pointerEvents = 'none';

            try {
                const result = await apiPost('/entrance/update/' + reservationId, {
                    participant: detailId,
                    is_present: !isPresent
                });

                if (result.success) {
                    if (!isPresent) {
                        item.classList.add('list-group-item-success');
                        const icon = item.querySelector('.bi-circle');
                        if (icon) {
                            icon.className = 'bi bi-check-circle-fill text-success fs-4';
                        }
                        const timeDiv = document.createElement('div');
                        timeDiv.className = 'small text-muted mt-1';
                        timeDiv.textContent = `Entré à ${new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}`;
                        item.querySelector('.d-flex').parentElement.appendChild(timeDiv);
                    } else {
                        item.classList.remove('list-group-item-success');
                        const icon = item.querySelector('.bi-check-circle-fill');
                        if (icon) {
                            icon.className = 'bi bi-circle text-secondary fs-4';
                        }
                        const timeDiv = item.querySelector('.small.text-muted.mt-1');
                        if (timeDiv) timeDiv.remove();
                    }

                    // Mettre à jour le badge "Tous présents"
                    if (everyonePresentBadge) {
                        everyonePresentBadge.style.display = result.everyOneInReservation ? 'block' : 'none';
                    }

                    item.style.pointerEvents = 'auto';
                } else {
                    throw new Error(result.message || 'Une erreur est survenue.');
                }
            } catch (error) {
                alert(`Erreur : ${error.message}`);
                item.style.pointerEvents = 'auto';
            }
        });
    });

});