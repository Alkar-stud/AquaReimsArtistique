import { apiPost } from '../components/apiClient.js';
import { buttonLoading } from '../components/utils.js';

document.addEventListener('DOMContentLoaded', () => {
    const executeButtons = document.querySelectorAll('.execute-command');
    const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
    const resultContent = document.getElementById('resultContent');

    executeButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const url = btn.dataset.url;
            const name = btn.dataset.name;

            if (!confirm(`Voulez-vous vraiment exécuter : ${name} ?`)) {
                return;
            }

            buttonLoading(btn, true);

            try {
                const data = await apiPost(url);

                if (data.success) {
                    let html = `<div class="alert alert-success">Commande exécutée avec succès</div>`;

                    if (data.data) {
                        html += '<ul class="list-group mt-3">';
                        for (const [key, value] of Object.entries(data.data)) {
                            html += `<li class="list-group-item"><strong>${key}:</strong> ${value}</li>`;
                        }
                        html += '</ul>';
                    }

                    resultContent.innerHTML = html;
                } else {
                    resultContent.innerHTML = `<div class="alert alert-danger">${data.error || 'Erreur inconnue'}</div>`;
                }
                resultModal.show();
            } catch (error) {
                resultContent.innerHTML = `<div class="alert alert-danger">${error.userMessage || error.message}</div>`;
                resultModal.show();
            } finally {
                buttonLoading(btn, false);
            }
        });
    });
});
