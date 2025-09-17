function refreshDisplayDetails() {
    // Rafraîchit le contexte
    fetch('/reservation/display-details-fragment?ts=' + Date.now())
        .then(r => r.text())
        .then(html => {
            document.getElementById('display_details').innerHTML = html;
            if (window.restoreReservationDetailsState) window.restoreReservationDetailsState();
        });
}

document.addEventListener('click', function(e) {
    if (e.target && (e.target.id === 'toggleDetailsBtn' || e.target.id === 'toggleDetailsBtnBottom')) {
        const details = document.getElementById('reservationDetails');
        const btn = document.getElementById('toggleDetailsBtn');
        const btnBottom = document.getElementById('toggleDetailsBtnBottom');
        const isVisible = details.style.display === 'block';
        details.style.display = isVisible ? 'none' : 'block';
        if (btn) btn.textContent = isVisible ? 'Détail' : 'Masquer';
        if (btnBottom) btnBottom.textContent = isVisible ? 'Détail' : 'Masquer';
        // Sauvegarde l'état
        localStorage.setItem('reservationDetailsOpen', !isVisible);
    }
});

// Réapplique l'état après un rafraîchissement AJAX
function restoreReservationDetailsState() {
    const details = document.getElementById('reservationDetails');
    const btn = document.getElementById('toggleDetailsBtn');
    const btnBottom = document.getElementById('toggleDetailsBtnBottom');
    const open = localStorage.getItem('reservationDetailsOpen') === 'true';
    if (details) {
        details.style.display = open ? 'block' : 'none';
        if (btn) btn.textContent = open ? 'Masquer' : 'Détail';
        if (btnBottom) btnBottom.textContent = open ? 'Masquer' : 'Détail';
    }
}

function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
function validateTel(tel) {
    return /^0[1-9](\d{8})$/.test(tel.replace(/\s+/g, ''));
}

function showToast(message) {
    alert(message);
}