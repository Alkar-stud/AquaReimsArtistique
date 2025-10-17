<div class="container">
    <h2>Gestion des templates de mails</h2>
    <table class="table table-bordered align-middle">
        <thead>
        <tr>
            <th>Code</th>
            <th>Sujet</th>
            <th colspan="2">Actions</th>
        </tr>
        </thead>
        <tbody>
        <!-- Ligne d'ajout -->
        <tr>
            <form method="POST" action="/gestion/mails_templates/add">
                <td>
                    <input type="text" name="code" class="form-control" required maxlength="64" placeholder="Nouveau code">
                </td>
                <td>
                    <input type="text" name="subject" class="form-control" required maxlength="255" placeholder="Nouveau sujet">
                </td>
                <td colspan="2">
                    <button type="submit" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-plus-circle"></i>
                        <span class="d-none d-md-inline">Ajouter</span>
                    </button>
                </td>
            </form>
        </tr>
        <!-- Affichage des templates existants -->
        {% foreach $templates as $template %}
        <tr>
            <td>{{ htmlspecialchars($template->getCode()) }}</td>
            <td>{{ htmlspecialchars($template->getSubject()) }}</td>
            <td class="d-flex gap-2 align-items-stretch">
                <button type="button"
                        class="btn btn-secondary btn-sm flex-fill text-nowrap"
                        data-bs-toggle="modal"
                        data-bs-target="#editModal-{{ $template->getId() }}">
                    <i class="bi bi-pencil-square"></i>
                    <span class="d-none d-md-inline">Modifier</span>
                </button>

                <form method="POST" action="/gestion/mails_templates/delete" class="m-0 flex-fill">
                    <input type="hidden" name="id" value="{{ $template->getId() }}">
                    <button type="submit" class="btn btn-danger btn-sm w-100 text-nowrap">
                        <i class="bi bi-trash"></i>
                        <span class="d-none d-md-inline">Supprimer</span>
                    </button>
                </form>
            </td>
        </tr>

        <!-- Modale d'édition -->
        <div class="modal fade" id="editModal-{{ $template->getId() }}" tabindex="-1" aria-labelledby="editModalLabel-{{ $template->getId() }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form method="POST" action="/gestion/mails_templates/edit">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editModalLabel-{{ $template->getId() }}">
                                Modifier le template <span class="badge bg-secondary">{{ htmlspecialchars($template->getCode()) }}</span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" value="{{ $template->getId() }}">
                            <div class="mb-3">
                                <label class="form-label">Sujet</label>
                                <input type="text" name="subject" class="form-control" required maxlength="255" value="{{ htmlspecialchars($template->getSubject()) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Corps HTML</label>
                                <textarea name="body_html" id="body_html-{{ $template->getId() }}" class="form-control ckeditor" rows="8">{{! htmlspecialchars($template->getBodyHtml() ?? '') !}}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Corps texte brut</label>
                                <textarea name="body_text" class="form-control" rows="8" style="font-family:monospace;">{{! htmlspecialchars($template->getBodyText() ?? '') !}}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer flex-column flex-sm-row">
                            <button type="button" class="btn btn-warning w-100 w-sm-auto mb-2 mb-sm-0" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary w-100 w-sm-auto">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {% endforeach %}
        </tbody>
    </table>

</div>
