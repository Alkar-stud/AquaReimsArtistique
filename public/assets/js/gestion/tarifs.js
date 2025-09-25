document.addEventListener('DOMContentLoaded', function() {
    const tarifModal = new bootstrap.Modal(document.getElementById('modal-tarif'));
    const form = document.getElementById('modal-tarif-form');
    const title = document.getElementById('modal-tarif-title');
    const tarifIdInput = document.getElementById('modal-tarif-id');
    const deleteBtn = document.getElementById('modal-tarif-delete-btn');
    const deleteForm = document.getElementById('modal-tarif-delete-form');
    const deleteIdInput = document.getElementById('modal-tarif-delete-id');

    // On attache la fonction à l'objet window pour la rendre accessible depuis les onclick
    window.openTarifModal = function(mode, dataset = {}) {
        form.reset(); // Réinitialise le formulaire

        if (mode === 'add') {
            title.textContent = 'Ajouter un tarif';
            form.action = '/gestion/tarifs/add';
            tarifIdInput.value = '';
            document.getElementById('modal-tarif-is_active').checked = true; // Actif par défaut
            deleteBtn.style.display = 'none';
        } else if (mode === 'edit') {
            title.textContent = 'Modifier : ' + dataset.name;
            form.action = '/gestion/tarifs/update';

            // Remplissage des champs
            tarifIdInput.value = dataset.id;
            for (const key in dataset) {
                const input = form.elements[key];
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = (dataset[key] === '1');
                    } else {
                        input.value = dataset[key];
                    }
                }
            }

            // Gestion du bouton supprimer
            deleteBtn.style.display = 'block';
            deleteIdInput.value = dataset.id;
        }

        tarifModal.show();
    }

    // Lier le bouton supprimer de la modale au formulaire de suppression
    deleteBtn.addEventListener('click', function() {
        if (confirm('Supprimer ce tarif ?')) {
            deleteForm.submit();
        }
    });
});