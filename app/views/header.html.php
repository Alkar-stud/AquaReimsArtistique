<?php $uri = strtok($_SERVER['REQUEST_URI'], '?'); ?>
<!-- Header avec logo centré -->
    <header>
        <div class="container text-center">
            <a href="/">
                <img src="/assets/images/logo-ARA.png" alt="Logo Aqua Reims Artistique" style="height: 80px;">
            </a>
        </div>
    </header>

<?php include __DIR__ . '/_menu.html.php'; ?>