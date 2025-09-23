/*
 * Pour refermer le sous menu en mode mobile
 */
document.addEventListener('DOMContentLoaded', function () {
    const configDropdown = document.getElementById('configDropdown');
    const menu = configDropdown ? configDropdown.nextElementSibling : null;

    if (configDropdown && menu) {
        // gestion du clic (mobile et desktop)
        configDropdown.addEventListener('click', function (e) {
            e.preventDefault();
            if (menu.classList.contains('show')) {
                menu.classList.remove('show');
                menu.style.display = 'none';
                configDropdown.setAttribute('aria-expanded', 'false');
            } else {
                menu.classList.add('show');
                menu.style.display = 'block';
                configDropdown.setAttribute('aria-expanded', 'true');
            }
        });

        // gestion du hover (desktop uniquement)
        configDropdown.addEventListener('mouseenter', function () {
            if (window.innerWidth > 768) {
                menu.classList.add('show');
                menu.style.display = 'block';
                configDropdown.setAttribute('aria-expanded', 'true');
            }
        });
        configDropdown.addEventListener('mouseleave', function () {
            if (window.innerWidth > 768) {
                menu.classList.remove('show');
                menu.style.display = 'none';
                configDropdown.setAttribute('aria-expanded', 'false');
            }
        });
        menu.addEventListener('mouseenter', function () {
            if (window.innerWidth > 768) {
                menu.classList.add('show');
                menu.style.display = 'block';
                configDropdown.setAttribute('aria-expanded', 'true');
            }
        });
        menu.addEventListener('mouseleave', function () {
            if (window.innerWidth > 768) {
                menu.classList.remove('show');
                menu.style.display = 'none';
                configDropdown.setAttribute('aria-expanded', 'false');
            }
        });

        // Ferme le menu si on clique ailleurs
        document.addEventListener('click', function (e) {
            if (!configDropdown.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('show');
                menu.style.display = 'none';
                configDropdown.setAttribute('aria-expanded', 'false');
            }
        });

        // Initialement masqué
        menu.style.display = 'none';
    }
});
