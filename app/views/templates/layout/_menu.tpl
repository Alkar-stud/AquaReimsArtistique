<!-- Menu responsive -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-start">
            <a class="navbar-brand nav-link" href="/">Accueil</a>
            <a class="nav-link d-lg-none" href="/reservation">Réservations</a>
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                {% if !$is_gestion_page %}
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/reservation' ? 'active-link' : '' }}" href="/reservation">Réservations</a>
                </li>
                {% endif %}
                {% if isset($_SESSION['user']['role']) and $_SESSION['user']['role']['level'] <= 2 and !$is_gestion_page %}
                <li class="nav-item">
                    <a class="nav-link" href="/gestion">Gestion</a>
                </li>
                {% endif %}
                {% if isset($_SESSION['user']['role']) and $_SESSION['user']['role']['level'] <= 2 and $is_gestion_page %}
                <li class="nav-item">
                    <a class="navbar-brand nav-link" href="/gestion">Gestion</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/accueil' ? 'active-link' : '' }}" href="/gestion/accueil">Page d'accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/reservations' ? 'active-link' : '' }}" href="/gestion/reservations">Réservations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/piscines' ? 'active-link' : '' }}" href="/gestion/piscines">Piscines</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/tarifs' ? 'active-link' : '' }}" href="/gestion/tarifs">Tarifs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/events' ? 'active-link' : '' }}" href="/gestion/events">Évènements</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/groupes-nageuses' ? 'active-link' : '' }}" href="/gestion/groupes-nageuses">Nageuses</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/mail_templates' ? 'active-link' : '' }}" href="/gestion/mail_templates">Mails</a>
                </li>
                {% endif %}
                {% if isset($_SESSION['user']['role']) and $_SESSION['user']['role']['level'] <= 1 and $is_gestion_page %}
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/users' ? 'active-link' : '' }}" href="/gestion/users">Utilisateurs</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="configDropdown" role="button" aria-expanded="false">
                        Configuration
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="configDropdown">
                        <li><a class="dropdown-item {{ $uri == '/gestion/configuration/configs' ? 'active-link' : '' }}" href="/gestion/configuration/configs">Configs</a></li>
                        <li><a class="dropdown-item {{ $uri == '/gestion/configuration/pages' ? 'active-link' : '' }}" href="/gestion/configuration/pages">Pages (à venir)</a></li>
                        <li><a class="dropdown-item {{ $uri == '/gestion/configuration/erreurs' ? 'active-link' : '' }}" href="/gestion/configuration/erreurs">Messages d'erreur (à venir)</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/gestion/logs' ? 'active-link' : '' }}" href="/gestion/logs">Logs</a>
                </li>
                {% endif %}
                {% if isset($_SESSION['user']) and !$is_gestion_page %}
                <li class="nav-item">
                    <a class="nav-link {{ $uri == '/account' ? 'active-link' : '' }}" href="/account">Mon compte</a>
                </li>
                {% endif %}
                <li class="nav-item">
                    {% if isset($_SESSION['user']) %}
                    <a class="nav-link" href="/logout">Déconnexion</a>
                    {% else %}
                    <a class="nav-link" href="/login">Connexion</a>
                    {% endif %}
                </li>
            </ul>
        </div>
    </div>
</nav>
