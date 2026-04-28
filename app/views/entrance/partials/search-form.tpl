<form method="GET" action="/entrance/search" class="mb-4">
    <div class="input-group">
        <input type="text"
               name="q"
               id="search-input"
               class="form-control"
               placeholder="Nom ou numéro de réservation..."
               value="{{ $searchQuery }}"
               autofocus>
        <button class="btn btn-primary" type="submit">
            <i class="fas fa-search"></i> Rechercher
        </button>
    </div>
</form>