document.getElementById('tarifsSansPlacesForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const tarifs = [];
    form.querySelectorAll('input[type="number"]').forEach(input => {
        const id = parseInt(input.id.replace('tarif_', ''));
        const qty = parseInt(input.value) || 0;
        if (qty > 0) {
            tarifs.push({ id, qty });
        }
    });
    fetch('/reservation/etape6', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            tarifs,
            csrf_token: window.csrf_token
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/reservation/confirmation';
            } else {
                document.getElementById('tarifsSansPlacesAlert').innerHTML =
                    `<div class="alert alert-danger">${data.error || 'Erreur'}</div>`;
            }
        });
});