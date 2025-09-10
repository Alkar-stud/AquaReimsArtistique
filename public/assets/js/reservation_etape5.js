let selected = [];

const participantsCount = document.querySelectorAll('.chosen-seat').length;
let participantSeats = window.participantSeatsInit || Array(participantsCount).fill(null);

function toggleSeat(btn) {
    const seat = parseInt(btn.dataset.seat, 10);
    const idx = participantSeats.findIndex(s => s === seat);

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
                } else if (data) {
                    showError(data.error);
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
                        if (data.csrf_token) {
                            window.csrf_token = data.csrf_token;
                        }
                        participantSeats[firstFree] = seat;
                        btn.closest('td').classList.add('tdplaceTempSession');
                        refreshDisplayDetails();
                        updateParticipantsTable();
                        updateSubmitBtn();
                    } else {
                        showError(data.error);
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
    document.getElementById('submitBtn').disabled = (participantSeats.filter(Boolean).length !== nbPlacesAssises);
    document.getElementById('submitBtnTop').disabled = (participantSeats.filter(Boolean).length !== nbPlacesAssises);
}

function showError(msg) {
    document.getElementById('etape5Alert').innerHTML =
        `<div class="alert alert-danger">${msg || 'Erreur'}</div>`;
}

document.addEventListener('DOMContentLoaded', function() {
    updateParticipantsTable();
    updateSubmitBtn();
    const openedZone = localStorage.getItem('openedZone');
    if (openedZone) {
        document.getElementById('zones-mini-plan').style.display = 'none';
        document.querySelectorAll('.zone-detail').forEach(z => z.style.display = 'none');
        const zoneDiv = document.getElementById('zone-detail-' + openedZone);
        if (zoneDiv) {
            zoneDiv.style.display = 'block';
        }
        localStorage.removeItem('openedZone');
    }
});

document.getElementById('form_etape5').addEventListener('submit', function(e) {
    e.preventDefault();
    if (participantSeats.filter(Boolean).length !== nbPlacesAssises) return;
    fetch('/reservation/etape5', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            seats: participantSeats,
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
            }
        });
});

// Affichage/masquage des zones
document.querySelectorAll('.zone-btn').forEach(btn => {
    btn.onclick = function() {
        document.getElementById('zones-mini-plan').style.display = 'none';
        document.querySelectorAll('.zone-detail').forEach(z => z.style.display = 'none');
        document.getElementById('zone-detail-' + btn.dataset.zone).style.display = 'block';
    };
});
document.querySelectorAll('.retour-zones').forEach(btn => {
    btn.onclick = function() {
        document.getElementById('zones-mini-plan').style.display = '';
        document.querySelectorAll('.zone-detail').forEach(z => z.style.display = 'none');
    };
});

function showSpinnerForParticipant(index) {
    const td = document.getElementById('chosen-seat-' + index);
    if (td) {
        td.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>';
    }
}