<?php

namespace app\Core;

class MenuBuilder
{
    /**
     * Construit la structure de données du menu principal.
     *
     * @param string $uri L'URI de la requête actuelle.
     * @param array|null $userSession La session utilisateur.
     * @return array La structure du menu.
     */
    public function build(string $uri, ?array $userSession): array
    {
        $menuItems = [];
        $isGestionPage = str_starts_with($uri, '/gestion');
        $isEntrancePage = str_starts_with($uri, '/entrance');
        $userRoleLevel = $userSession['role']['level'] ?? 99; // 99 = invité/non connecté

        if ($isGestionPage) {
            // Accueil gestion épinglé en mobile (affiché hors collapse)
            $menuItems[] = $this->createItem('Accueil gestion', '/gestion', $uri === '/gestion', true);

            $menuItems[] = $this->createItem('Réservations', '/gestion/reservations', str_starts_with($uri, '/gestion/reservations'));
            $menuItems[] = $this->createItem('Évènements', '/gestion/events', $uri === '/gestion/events');
            $menuItems[] = $this->createItem('Page d\'accueil', '/gestion/accueil', $uri === '/gestion/accueil');
            $menuItems[] = $this->createItem('Tarifs', '/gestion/tarifs', $uri === '/gestion/tarifs');
            $menuItems[] = $this->createItem('Nageuses', '/gestion/swimmers-groups', str_starts_with($uri, '/gestion/swimmers'));
            $menuItems[] = $this->createItem('Piscines', '/gestion/piscines', $uri === '/gestion/piscines');
            $menuItems[] = $this->createItem('Mails', '/gestion/mails_templates', $uri === '/gestion/mails_templates');

            if ($userRoleLevel <= 1) {
                $menuItems[] = $this->createItem('Utilisateurs', '/gestion/users', $uri === '/gestion/users');
                $menuItems[] = $this->createDropdown('Configuration', [
                    $this->createItem('Configs', '/gestion/configs', $uri === '/gestion/configs'),
                    $this->createItem('Pages/menu (à venir)', '/gestion/pages', $uri === '/gestion/pages'),
                    $this->createItem('Messages d\'erreur (à venir)', '/gestion/erreurs', $uri === '/gestion/erreurs'),
                ], str_starts_with($uri, '/gestion/configs'));
                $menuItems[] = $this->createItem('Logs', '/gestion/logs', $uri === '/gestion/logs');
            }
        }
        elseif($isEntrancePage) {
            $menuItems[] = $this->createItem('Gestion des entrées', '/entrance/search', str_starts_with($uri, '/entrance'), true);
            if ($userRoleLevel <= 2) {
                $menuItems[] = $this->createItem('Gestion', '/gestion', $uri === '/gestion');
            }
        } else {
            // Menu du site public
            $menuItems[] = $this->createItem('Réservations', '/reservation', $uri === '/reservation', true);
            if ($userRoleLevel <= 5) {
                $menuItems[] = $this->createItem('Gestion des entrées', '/entrance/search', str_starts_with($uri, '/entrance'));
            }
            if ($userRoleLevel <= 2) {
                $menuItems[] = $this->createItem('Gestion', '/gestion', $uri === '/gestion');
            }
        }

        // Liens communs
        if (isset($userSession['id'])) {
            if (!$isGestionPage) {
                $menuItems[] = $this->createItem('Mon compte', '/account', $uri === '/account');
            }
            $menuItems[] = $this->createItem('Déconnexion', '/logout', false);
        } else {
            $menuItems[] = $this->createItem('Connexion', '/login', $uri === '/login');
        }

        return $menuItems;
    }

    /**
     * Crée un item de menu simple.
     *
     * @param string $label
     * @param string $url
     * @param bool $isActive
     * @param bool $pinnedOnMobile Si true, l'item sera affiché hors "collapse" en mobile.
     * @return array
     */
    private function createItem(string $label, string $url, bool $isActive, bool $pinnedOnMobile = false): array
    {
        return [
            'type' => 'link',
            'label' => $label,
            'url' => $url,
            'isActive' => $isActive,
            'children' => [],
            'pinned_on_mobile' => $pinnedOnMobile,
        ];
    }

    /**
     * Crée un item de menu déroulant (dropdown).
     */
    private function createDropdown(string $label, array $children, bool $isActive): array
    {
        return [
            'type' => 'dropdown',
            'label' => $label,
            'url' => '#',
            'isActive' => $isActive,
            'children' => $children,
            'pinned_on_mobile' => false,
        ];
    }
}
