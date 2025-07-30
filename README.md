# Aqua Reims Artistique

## Présentation

Billetterie du club sportif Aqua Reims Artistique, pour la vente des places des galas du club

## Installation

1. Cloner le dépôt
2. Exécuter `composer install`
3. Exécuter `composer dump-autoload`
4. Vérifier que PHP version 8.2 minimum soit installé, un serveur web type Apache et votre serveur MySQL lancés.
5. Créer un fichier .env sur le modèle du .env.example et le renseigner avec vos informations, nom de l’application, paramètres BDD et Mailer.
6. Aller sur l’url de l’app et `/install`. Les tables s’installent dans la base de données.
7. Saisir son email pour recevoir un lien de réinitialisation de mot de passe pour le compte SuperAdmin qui pourra créer les autres comptes utilisateurs.