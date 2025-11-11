<!-- Menu responsive -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-start">
            <a class="navbar-brand nav-link{{ $uri == '/' ? ' active-link' : '' }}" href="/"
               {{ $uri == '/' ? 'aria-current="page"' : '' }}
            >Accueil</a>
            {% if str_starts_with($uri, '/entrance') %}
            <a class="nav-link d-lg-none{{ str_starts_with($uri, '/entrance/search') ? ' active-link' : '' }}"
               href="/entrance/search"
               {{ str_starts_with($uri, '/entrance/search') ? 'aria-current="page"' : '' }}>
                Rechercher
            </a>
            {% else %}
            <a class="nav-link d-lg-none{{ $uri == '/reservation' ? ' active-link' : '' }}"
               href="/reservation"
               {{ $uri == '/reservation' ? 'aria-current="page"' : '' }}>
                Réservations
            </a>
            {% endif %}
        </div>
        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
                aria-controls="navbarNav"
                aria-expanded="false"
                aria-label="Ouvrir le menu">
            <span class="navbar-toggler-icon" aria-hidden="true"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                {% if str_starts_with($uri, '/entrance') %}
                <li class="nav-item">
                    <a class="nav-link{{ str_starts_with($uri, '/entrance/search') ? ' active-link' : '' }}"
                       href="/entrance/search"
                       {{ str_starts_with($uri, '/entrance/search') ? 'aria-current="page"' : '' }}>
                        Rechercher
                    </a>
                </li>
                {% endif %}
                {% if !$is_gestion_page %}
                <li class="nav-item">
                    <a class="nav-link{{ $uri == '/reservation' ? ' active-link' : '' }}"
                       href="/reservation"
                       {{ $uri == '/reservation' ? 'aria-current="page"' : '' }}>
                        Réservations
                    </a>
                </li>
                {% endif %}
                {% if isset($_SESSION['user']['role']) and $_SESSION['user']['role']['level'] <= 2 and !$is_gestion_page %}
                <li class="nav-item">
                    <a class="nav-link{{ $uri == '/gestion' ? ' active-link' : '' }}"
                       href="/gestion"
                       {{ $uri == '/gestion' ? 'aria-current="page"' : '' }}>
                        Gestion
                    </a>
                </li>
                {% endif %}
                {% if isset($_SESSION['user']['role']) and $_SESSION['user']['role']['level'] <= 2 and $is_gestion_page %}
                <li class="nav-item">
                    <a class="navbar-brand nav-link{{ $uri == '/gestion' ? ' active-link' : '' }}"
                       href="/gestion"
                       {{ $uri == '/gestion' ? 'aria-current="page"' : '' }}>
                        Gestion
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link{{ str_starts_with($uri, '/gestion/reservations') ? ' active-link' : '' }}"
                       href="/gestion/reservations"
                       {{ str_starts_with($uri, '/gestion/reservations') ? 'aria-current="page"' : '' }}>
                        Réservations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/piscines' ? 'active-link' : '' }}"
                       href="/gestion/piscines"
                       {{ $uri == '/gestion/piscines' ? 'aria-current="page"' : '' }}>
                        Piscines
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ str_starts_with($uri, '/gestion/swimmers') ? 'active-link' : '' }}"
                       href="/gestion/swimmers-groups"
                       {{ str_starts_with($uri, '/gestion/swimmers') ? 'aria-current="page"' : '' }}>
                        Nageuses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/tarifs' ? 'active-link' : '' }}"
                       href="/gestion/tarifs"
                       {{ $uri == '/gestion/tarifs' ? 'aria-current="page"' : '' }}>
                        Tarifs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/events' ? 'active-link' : '' }}"
                       href="/gestion/events"
                       {{ $uri == '/gestion/events' ? 'aria-current="page"' : '' }}>
                        Évènements
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ str_starts_with($uri, '/gestion/accueil') ? 'active-link' : '' }}"
                       href="/gestion/accueil"
                       {{ str_starts_with($uri, '/gestion/accueil') ? 'aria-current="page"' : '' }}>
                        Page d'accueil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/mails_templates' ? 'active-link' : '' }}"
                       href="/gestion/mails_templates"
                       {{ $uri == '/gestion/mails_templates' ? 'aria-current="page"' : '' }}>
                        Mails
                    </a>
                </li>
                {% endif %}
                {% if isset($_SESSION['user']['role']) and $_SESSION['user']['role']['level'] <= 1 and $is_gestion_page %}
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/users' ? 'active-link' : '' }}" href="/gestion/users" {{ $uri == '/gestion/users' ? 'aria-current="page"' : '' }}>Utilisateurs</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ $uri == '/gestion/configs' ? 'active-link' : '' }}" href="#" id="configDropdown" role="button" aria-expanded="false">
                        Configuration
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="configDropdown">
                        <li><a class="dropdown-item {{ $uri == '/gestion/configs' ? 'active-link' : '' }}" href="/gestion/configs" {{ $uri == '/gestion/configs' ? 'aria-current="page"' : '' }}>Configs</a></li>
                        <li><a class="dropdown-item {{ $uri == '/gestion/pages' ? 'active-link' : '' }}" href="/gestion/pages" {{ $uri == '/gestion/pages' ? 'aria-current="page"' : '' }}>Pages (à venir)</a></li>
                        <li><a class="dropdown-item {{ $uri == '/gestion/erreurs' ? 'active-link' : '' }}" href="/gestion/erreurs" {{ $uri == '/gestion/erreurs' ? 'aria-current="page"' : '' }}>Messages d'erreur (à venir)</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/logs' ? 'active-link' : '' }}" href="/gestion/logs" {{ $uri == '/gestion/logs' ? 'aria-current="page"' : '' }}>Logs</a>
                </li>
                {% endif %}
                {% if isset($_SESSION['user']) and !$is_gestion_page %}
                <li class="nav-item">
                    <a class="nav-link{{ $uri == '/account' ? ' active-link' : '' }}"
                       href="/account"
                       {{ $uri == '/account' ? 'aria-current="page"' : '' }}>
                        Mon compte
                    </a>
                </li>
                {% endif %}
                <li class="nav-item">
                    {% if isset($_SESSION['user']) %}
                    <a class="nav-link" href="/logout">Déconnexion</a>
                    {% else %}
                    <a class="nav-link{{ $uri == '/login' ? ' active-link' : '' }}"
                       href="/login"
                       {{ $uri == '/login' ? 'aria-current="page"' : '' }}>
                        Connexion
                    </a>
                    {% endif %}
                </li>
            </ul>
        </div>
    </div>
</nav>
