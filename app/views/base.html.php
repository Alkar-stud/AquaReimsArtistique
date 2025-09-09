<!DOCTYPE html>
<html lang="<?= str_replace('_', '-', $_ENV['APP_NAME'] ?? 'fr'); ?>">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="Description" content="Site de réservation pour les galas du Aqua Reims Artistique" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <META http-equiv="content-type" content="no-cache">
    <META http-equiv="refresh" content="no-cache">
    <meta charset="UTF-8">
    <link rel="icon" href="/assets/images/cropped-logo-AquaReimsArtistique-300-32x32.png" type="image/x-icon" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/generic.css">
    <?php if (str_starts_with($_SERVER['REQUEST_URI'], '/gestion')): ?><link rel="stylesheet" href="/assets/css/admin.css">
    <?php endif; ?>
    <?php if (str_starts_with($_SERVER['REQUEST_URI'], '/gestion/mail_templates') || str_starts_with($_SERVER['REQUEST_URI'], '/gestion/accueil')): ?>
        <link rel="stylesheet" href="/assets/css/ckeditor.css">
        <link rel="stylesheet" href="/assets/ckeditor5/ckeditor5.css">
    <?php endif; ?>

    <script type="text/javascript" src="/assets/js/scripts.js" charset="UTF8"></script>

    <title>
        <?= htmlspecialchars(($_ENV['APP_NAME'] ?? 'Titre') . ' - ' . ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </title>

</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/header.html.php'; ?>

    <main id="main-page" class="p-3 flex-grow-1">
        <?= $content ?? '' ?>
    </main>

    <?php include __DIR__ . '/footer.html.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>

    <?php if (str_starts_with($_SERVER['REQUEST_URI'], '/gestion/mail_templates') || str_starts_with($_SERVER['REQUEST_URI'], '/gestion/accueil')): ?>
    <script type="importmap">
        {
            "imports": {
                "ckeditor5": "/assets/ckeditor5/ckeditor5.js",
                "ckeditor5/": "/assets/ckeditor5/"
            }
        }
    </script>
    <script type="module" src="/assets/js/ckeditor.js"></script>
    <?php endif; ?>
<?php
if ($_ENV['APP_DEBUG'] === "true") {
?>

<!-- Outil de débogage pour afficher les dimensions de la fenêtre -->
<div id="screen-dimensions-display" style="position: fixed; bottom: 10px; right: 10px; background-color: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 5px; font-family: monospace; z-index: 9999; font-size: 14px;"></div>

	<script>
		// Attend que le DOM soit entièrement chargé avant d'exécuter le script
		document.addEventListener('DOMContentLoaded', function() {
			// Sélectionne l'élément où afficher les dimensions
			const dimensionsDisplay = document.getElementById('screen-dimensions-display');

			// Fonction pour récupérer et afficher les dimensions
			function updateDimensions() {
				const width = window.innerWidth;
				const height = window.innerHeight;
				// Met à jour le texte dans notre élément
				dimensionsDisplay.textContent = `Viewport: ${width}px x ${height}px`;
			}

			// Affiche les dimensions une première fois au chargement
			updateDimensions();

			// Ajoute un écouteur d'événement pour mettre à jour les dimensions à chaque redimensionnement
			window.addEventListener('resize', updateDimensions);
		});
	</script>
	<?php
    echo '<pre>$_SESSION : ';
    print_r($_SESSION);
    echo '</pre>';
}
?>
</body>
</html>

<?php



