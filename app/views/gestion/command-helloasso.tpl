
<div class="container py-4">

    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">

            <!-- En-tête -->
            <div class="mb-4">
                <h1 class="h2 mb-2">
                    Vérification d'une commande HelloAsso
                </h1>
                <p class="text-muted mb-0">
                    Saisissez le numéro de commande pour retrouver les
                    informations associées.
                </p>
            </div>


            <!-- Formulaire de recherche -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <form method="get" action="/gestion/command-helloasso">

                        <div class="mb-3">
                            <label for="orderId" class="form-label fw-semibold">
                                Numéro de commande HelloAsso
                            </label>

                            <input
                                    type="text"
                                    class="form-control form-control-lg"
                                    id="orderId"
                                    name="orderId"
                                    value="{{ $orderId ?? '' }}"
                                    inputmode="numeric"
                                    pattern="[0-9]+"
                                    placeholder="Ex. 190741272"
                                    required
                                    autofocus
                            >

                            <div class="form-text">
                                Entrez uniquement le numéro de la commande.
                            </div>
                        </div>

                        <div class="d-grid d-sm-flex justify-content-sm-end">
                            <button type="submit" class="btn btn-primary btn-lg px-4">
                                Vérifier la commande
                            </button>
                        </div>

                    </form>

                </div>
            </div>


            <!-- Erreur -->
            {% if !empty($error) %}

            <div
                    class="alert alert-danger d-flex align-items-start shadow-sm mb-4"
                    role="alert"
            >
                <div class="me-3 fs-4">
                    ⚠
                </div>

                <div>
                    <div class="fw-semibold mb-1">
                        Impossible de récupérer la commande
                    </div>

                    <div>
                        {{ $error }}
                    </div>
                </div>
            </div>

            {% endif %}


            <!-- Résultat -->
            {% if !empty($orderData) %}

            <div class="mb-4">

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h2 class="h4 mb-1">
                            Commande #{{ $orderData['orderId'] }}
                        </h2>

                        <p class="text-muted mb-0">
                            Informations récupérées depuis HelloAsso
                        </p>
                    </div>

                    <span class="badge text-bg-success fs-6">
                        Commande trouvée
                    </span>
                </div>

            </div>



            <!-- Informations du payeur -->
            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h3 class="h5 mb-0">
                        Informations du payeur
                    </h3>
                </div>

                <div class="card-body px-4 pb-4">

                    <div class="row g-3">

                        <div class="col-12 col-md-4">
                            <div class="text-muted small mb-1">
                                Prénom
                            </div>

                            <div class="fw-semibold">
                                {{ $orderData['payer']['firstName'] ?: '—' }}
                            </div>
                        </div>


                        <div class="col-12 col-md-4">
                            <div class="text-muted small mb-1">
                                Nom
                            </div>

                            <div class="fw-semibold">
                                {{ $orderData['payer']['lastName'] ?: '—' }}
                            </div>
                        </div>


                        <div class="col-12 col-md-4">
                            <div class="text-muted small mb-1">
                                Adresse e-mail
                            </div>

                            {% if !empty($orderData['payer']['email']) %}

                            <a
                                    href="mailto:{{ $orderData['payer']['email'] }}"
                                    class="text-decoration-none"
                            >
                                {{ $orderData['payer']['email'] }}
                            </a>

                            {% else %}

                            <span>—</span>

                            {% endif %}
                        </div>

                    </div>

                </div>

            </div>



            <!-- Articles -->
            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h3 class="h5 mb-0">
                        Articles de la commande
                    </h3>
                </div>

                <div class="card-body px-4 pb-4">

                    {% if !empty($orderData['items']) %}

                    <div class="row g-3">

                        {% foreach $orderData['items'] as $item %}

                        <div class="col-12">

                            <div class="border rounded-3 p-3">

                                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">

                                    <div>
                                        <h4 class="h6 mb-1">
                                            {{ $item['name'] ?: 'Article' }}
                                        </h4>

                                        {% if !empty($item['id']) %}

                                        <div class="text-muted small">
                                            Article #{{ $item['id'] }}
                                        </div>

                                        {% endif %}
                                    </div>


                                </div>


                                <!-- Champs personnalisés -->
                                {% if !empty($item['customFields']) %}

                                <div class="row g-3">

                                    {% if !empty($item['customFields']['printedFirstName']) %}

                                    <div class="col-12 col-md-6">

                                        <div class="bg-light rounded-3 p-3 h-100">

                                            <div class="text-muted small mb-1">
                                                Prénom imprimé sur le tee-shirt
                                            </div>

                                            <div class="fs-5 fw-semibold">
                                                {{ $item['customFields']['printedFirstName'] }}
                                            </div>

                                        </div>

                                    </div>

                                    {% endif %}


                                    {% if !empty($item['customFields']['tshirtSize']) %}

                                    <div class="col-12 col-md-6">

                                        <div class="bg-light rounded-3 p-3 h-100">

                                            <div class="text-muted small mb-1">
                                                Taille du T-shirt enfant
                                            </div>

                                            <div class="fs-5 fw-semibold">
                                                {{ $item['customFields']['tshirtSize'] }}
                                            </div>

                                        </div>

                                    </div>

                                    {% endif %}

                                </div>

                                {% else %}

                                <div class="text-muted small">
                                    Aucun renseignement complémentaire pour cet article.
                                </div>

                                {% endif %}

                            </div>

                        </div>

                        {% endforeach %}

                    </div>

                    {% else %}

                    <div class="text-muted">
                        Aucun article trouvé dans cette commande.
                    </div>

                    {% endif %}

                </div>

            </div>


            <div class="text-center pb-3">

                <a
                        href="/gestion/command-helloasso"
                        class="btn btn-outline-secondary"
                >
                    Vérifier une autre commande
                </a>

            </div>

            {% endif %}

        </div>
    </div>

</div>
