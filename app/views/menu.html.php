    <!-- Header avec logo centré -->
    <header>
        <div class="container text-center">
            <a href="/">
                <img src="/assets/images/logo-ARA.png" alt="Logo Aqua Reims Artistique" style="height: 80px;">
            </a>
        </div>
    </header>

    <!-- Menu responsive -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-start">
                <a class="navbar-brand nav-link" href="/">Accueil</a>
                <a class="nav-link d-lg-none" href="/reservation">Réservation</a>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="/reservation">Réservation</a>
                    </li>
                    <li class="nav-item">
                        <?php if (isset($_SESSION['user'])): ?>
                            <a class="nav-link" href="/logout">Déconnexion</a>
                        <?php else: ?>
                            <a class="nav-link" href="/login">Connexion</a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>