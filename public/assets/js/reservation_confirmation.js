document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector('button[type="submit"].btn-primary');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        // Récupère le token CSRF si besoin
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        fetch('/reservation/saveCart', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: window.csrf_token
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/reservation/payment';
                } else {
                    alert(data.error || 'Erreur lors de l\'enregistrement du panier.');
                }
            })
            .catch((err) => {
                console.error('Erreur réseau :', err);
                alert('Erreur réseau.');
            });
    });
});
