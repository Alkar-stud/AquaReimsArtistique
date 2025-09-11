let selected = [];

const participantsCount = document.querySelectorAll('.chosen-seat').length;
let participantSeats = window.participantSeatsInit || Array(participantsCount).fill(null);

function toggleSeat(btn) {
    const seat = parseInt(btn.dataset.seat, 10);
    const idx = participantSeats.findIndex(s => s === seat);

    // On nettoie l'alerte précédente à chaque nouvelle action
    document.getElementById('etape5Alert').innerHTML = '';

    if (idx !== -1) {
        //Ajout d'un spinner dans la colonne de la ligne correspondante du tableau participantsTable chosen-seat- $i
        showSpinnerForParticipant(idx);
        // Désélection : suppression côté serveur
        fetch('/reservation/etape5RemoveSeat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                seat_id: seat,
                csrf_token: window.csrf_token
            })
        })
            .then(async r => {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Réponse non JSON du serveur :', text);
                    showError('Erreur de communication avec le serveur');
                    throw e;
                }
            })
            .then(data => {
                if (data && data.success) {
                    if (data.reload) {
                        //Avant le reload, stocker la zone ouverte
                        const openedZone = document.querySelector('.zone-detail[style*="display: block"]');
                        if (openedZone) {
                            const zoneId = openedZone.id.replace('zone-detail-', '');
                            localStorage.setItem('openedZone', zoneId);
                        }

                        return;
                    }
                    if (data.csrf_token) {
                        window.csrf_token = data.csrf_token;
                    }
                    participantSeats[idx] = null;
                    btn.closest('td').classList.remove('tdplaceTempSession');
                    refreshDisplayDetails();
                    updateParticipantsTable();
                    updateSubmitBtn();
                } else {
                    if (data.reason && data.seat_id) updateSeatStatus(data.seat_id, data.reason);
                    showError(data.error);
                    // En cas d'erreur, on rafraîchit la table pour enlever le spinner
                    updateParticipantsTable();
                }
            });
    } else {
        const firstFree = participantSeats.indexOf(null);
        if (firstFree !== -1) {
            //Ajout d'un spinner dans la colonne de la ligne correspondante du tableau participantsTable chosen-seat- $i
            showSpinnerForParticipant(firstFree);
            // Sélection : ajout côté serveur
            fetch('/reservation/etape5AddSeat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    seat_id: seat,
                    index: firstFree,
                    csrf_token: window.csrf_token
                })
            })
                .then(response => {
                    // Récupérer d'abord le texte brut
                    return response.text().then(text => {
                        // Ensuite essayer de parser en JSON si possible
                        try {
                            return JSON.parse(text);
                        } catch (error) {
                            console.log(text);
                            console.error("Erreur de parsing JSON:", error);
                            console.log("Contenu non parsable:", text);
                            throw new Error("Réponse non valide du serveur");
                        }
                    });
                })
                .then(data => {
                    if (data && data.success) {
                        if (data.csrf_token) {
                            window.csrf_token = data.csrf_token;
                        }
                        participantSeats[firstFree] = seat;
                        btn.closest('td').classList.add('tdplaceTempSession');
                        refreshDisplayDetails();
                        updateParticipantsTable();
                        updateSubmitBtn();
                    } else {
                        // Si l'ajout échoue, on met à jour la case et on affiche l'erreur
                        if (data.reason && data.seat_id) updateSeatStatus(data.seat_id, data.reason);
                        showError(data.error || 'Une erreur est survenue.');
                        // On rafraîchit la table pour enlever le spinner de la ligne participant
                        updateParticipantsTable();
                    }
                });
        }
    }
}

function updateParticipantsTable() {
    document.querySelectorAll('.chosen-seat').forEach((td, i) => {
        const seatId = participantSeats[i];
        if (seatId) {
            td.textContent = window.seatNames && window.seatNames[seatId] ? window.seatNames[seatId] : seatId;
        } else {
            td.textContent = 'Non choisie';
        }
    });
    document.querySelectorAll('.seat.btn').forEach(btn => {
        const seat = parseInt(btn.dataset.seat, 10);
        if (participantSeats.includes(seat)) {
            btn.closest('td').classList.add('selected');
        } else {
            btn.closest('td').classList.remove('selected');
        }
    });
}

function updateSubmitBtn() {
    document.getElementById('selectedSeats').value = participantSeats.filter(Boolean).join(',');
    const isDisabled = participantSeats.filter(Boolean).length !== nbPlacesAssises;
    document.getElementById('submitBtnTop').disabled = isDisabled;
    document.getElementById('submitBtnBottom').disabled = isDisabled;
}

/**
 * Met à jour l'apparence d'une place sur le plan après un échec de réservation.
 * @param {number} seatId L'ID de la place.
 * @param {string} reason La raison de l'échec ('closed', 'taken_definitively', 'taken_temporarily').
 */
function updateSeatStatus(seatId, reason) {
    const button = document.querySelector(`.seat.btn[data-seat="${seatId}"]`);
    if (!button) return;

    const td = button.closest('td');
    if (!td) return;

    // Supprime les anciennes classes de statut pour éviter les conflits
    td.classList.remove('tdplacePMR', 'tdplaceTempSession');

    let newClass = '';
    let newTitle = '';

    switch (reason) {
        case 'closed':
            newClass = 'tdplaceClosed';
            newTitle = 'Place fermée';
            break;
        case 'taken_definitively':
            newClass = 'tdplacePris';
            newTitle = 'Déjà réservée';
            break;
        case 'taken_temporarily':
            newClass = 'tdplaceTemp';
            newTitle = 'En cours de réservation';
            break;
    }

    if (newClass) td.classList.add(newClass);

    // Remplace le bouton par un span non cliquable
    const span = document.createElement('span');
    span.className = 'seat btn btn-secondary mb-1';
    span.style.opacity = '0.7';
    span.title = newTitle;
    span.textContent = button.textContent;
    button.replaceWith(span);
}

function showError(msg) {
    document.getElementById('etape5Alert').innerHTML =
        `<div class="alert alert-danger">${msg || 'Erreur'}</div>`;
}

document.addEventListener('DOMContentLoaded', function() {
    updateParticipantsTable();
    updateSubmitBtn();
});

document.getElementById('form_etape5').addEventListener('submit', function(e) {
    e.preventDefault();
    if (participantSeats.filter(Boolean).length !== nbPlacesAssises) return;
    // Le bouton submit est cloné dans le DOM, donc on écoute les deux formulaires
    // pour être sûr de capturer l'événement.
    submitForm();
});

document.getElementById('submitBtnTop').form.addEventListener('submit', function(e) {
    e.preventDefault();
    if (participantSeats.filter(Boolean).length !== nbPlacesAssises) return;
    submitForm();
});

document.getElementById('form_etape5_bottom').addEventListener('submit', function(e) {
    e.preventDefault();
    if (participantSeats.filter(Boolean).length !== nbPlacesAssises) return;
    submitForm();
});

function submitForm() {
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(btn => btn.disabled = true);

    const seatsToSubmit = participantSeats.filter(Boolean);

    if (seatsToSubmit.length !== nbPlacesAssises) {
        showError('Veuillez sélectionner toutes vos places avant de continuer.');
        submitButtons.forEach(btn => btn.disabled = false);
        return;
    }

    fetch('/reservation/etape5', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            seats: seatsToSubmit,
            csrf_token: window.csrf_token
        })
    })
        .then(async r => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Réponse non JSON du serveur :', text);
                showError('Erreur de communication avec le serveur');
                throw e;
            }
        })
        .then(data => {
            if (data.success) {
                window.location.href = '/reservation/etape6Display';
            } else {
                showError(data.error);
                submitButtons.forEach(btn => btn.disabled = false);
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            showError('Une erreur de communication est survenue.');
            submitButtons.forEach(btn => btn.disabled = false);
        });
}

// Affichage/masquage des zones
document.querySelectorAll('.zone-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        loadZonePlan(this.dataset.zone);
    });
});

document.getElementById('zone-plan-container').addEventListener('click', function(e) {
    const retourBtn = e.target.closest('.retour-zones');
    const navBtn = e.target.closest('.zone-nav-btn');

    if (retourBtn) {
        document.getElementById('zones-mini-plan').style.display = '';
        document.getElementById('zone-plan-container').style.display = 'none';
    } else if (navBtn) {
        loadZonePlan(navBtn.dataset.zone);
    }
});

function loadZonePlan(zoneId, initialScroll = 'start') {
    document.getElementById('zones-mini-plan').style.display = 'none';
    const planContainer = document.getElementById('zone-plan-container');
    planContainer.style.display = 'block';
    planContainer.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></div>';

    fetch(`/reservation/zone-plan/${zoneId}`)
        .then(response => response.ok ? response.text() : Promise.reject('Erreur lors du chargement de la zone.'))
        .then(html => {
            planContainer.innerHTML = html;
            updateParticipantsTable();
            setupSwipeNavigation(); // On active la navigation par défilement

            // Positionne le scroll initial de la nouvelle zone
            const scrollContainer = document.querySelector('.zone-plan.overflow-auto');
            if (scrollContainer) {
                if (initialScroll === 'end') {
                    // On scroll complètement à droite pour donner l'impression de continuité
                    scrollContainer.scrollLeft = scrollContainer.scrollWidth - scrollContainer.clientWidth;
                }
                // 'start' est la position par défaut (scrollLeft = 0), donc pas besoin de else.
            }
        })
        .catch(error => {
            planContainer.innerHTML = `<div class="alert alert-danger">${error.toString()}</div>`;
        });
}

/**
 * Crée une fonction "debounced" qui retarde l'invocation de `func`
 * jusqu'à ce que `wait` millisecondes se soient écoulées depuis la dernière fois
 * que la fonction debounced a été invoquée.
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function setupSwipeNavigation() {
    const scrollContainer = document.querySelector('.zone-plan.overflow-auto');
    if (!scrollContainer) return;

    // --- Gestion du swipe tactile ---
    let touchStartX = 0;
    scrollContainer.addEventListener('touchstart', (e) => {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });

    scrollContainer.addEventListener('touchend', (e) => {
        const touchEndX = e.changedTouches[0].clientX;
        const swipeDistance = touchStartX - touchEndX;
        const isAtEnd = scrollContainer.scrollLeft + scrollContainer.clientWidth >= scrollContainer.scrollWidth - 1;
        const isAtStart = scrollContainer.scrollLeft === 0;

        // Swipe vers la gauche (pour aller à la zone suivante)
        if (swipeDistance > 50 && isAtEnd) {
            const nextButton = document.querySelector('.zone-nav-btn[data-zone][title="Zone suivante"]');
            if (nextButton) {
                e.preventDefault();
                loadZonePlan(nextButton.dataset.zone, 'start');
            }
        }
        // Swipe vers la droite (pour aller à la zone précédente)
        else if (swipeDistance < -50 && isAtStart) {
            const prevButton = document.querySelector('.zone-nav-btn[data-zone][title="Zone précédente"]');
            if (prevButton) {
                e.preventDefault();
                loadZonePlan(prevButton.dataset.zone, 'end');
            }
        }
    });

    // --- Gestion de la molette de la souris ---
    scrollContainer.addEventListener('wheel', (e) => {
        // On ne gère que le défilement horizontal
        if (e.deltaX === 0) return;

        const isAtEnd = scrollContainer.scrollLeft + scrollContainer.clientWidth >= scrollContainer.scrollWidth -1;
        const isAtStart = scrollContainer.scrollLeft === 0;

        // Molette vers la droite en étant au bout
        if (e.deltaX > 0 && isAtEnd) {
            const nextButton = document.querySelector('.zone-nav-btn[data-zone][title="Zone suivante"]');
            if (nextButton) loadZonePlan(nextButton.dataset.zone, 'start');
        }
        // Molette vers la gauche en étant au début
        else if (e.deltaX < 0 && isAtStart) {
            const prevButton = document.querySelector('.zone-nav-btn[data-zone][title="Zone précédente"]');
            if (prevButton) loadZonePlan(prevButton.dataset.zone, 'end');
        }
    });
}


function showSpinnerForParticipant(index) {
    const td = document.getElementById('chosen-seat-' + index);
    if (td) {
        td.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>';
    }
}