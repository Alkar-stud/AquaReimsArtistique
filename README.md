# Aqua Reims Artistique

## Présentation

Billetterie du club sportif Aqua Reims Artistique, pour la vente des places des galas du club

## Installation

### 1. Installation locale

- Installez PHP 8.2+, Composer, Apache, et MySQL.
- Clonez le dépôt.
- Copiez `.env.example` en `.env` et configurez-le.
- Exécutez `composer install` puis `composer dump-autoload`.
- Configurez le vhost Apache pour pointer sur `/public`.
- Lancez le serveur et accédez à `/install`.

### 2. Installation avec Docker (recommandé)

- Installez Docker et Docker Compose.
- Clonez le dépôt.
- Copiez `.env.example` en `.env.docker` et configurez-le.
- Lancez `docker-compose up --build`.
- Accédez à `https://localhost:8000/install`.


## Anonymisation RGPD

Ce projet implémente une anonymisation des données personnelles après une période de rétention configurable.

- Période de rétention: fournie à `AnonymizeDataService` au format `DateInterval` (ex: `P2Y`, `P6M`) définie dans le fichier environnement.
- Stratégies par champ:
    - `fixed:<valeur>` \=> remplace par une valeur constante.
    - `null` \=> vide le champ.
    - `concatIdEmail:<domaine>` (email uniquement) \=> `id@<domaine>`.

Tables et colonnes:
- `reservation`: champs anonymisés (`name`, `firstname`, `email`, `phone`), marqueur `anonymized_at`
- `reservation_detail`: champs anonymisés (`name`, `firstname`, `justificatif_name`), marqueur `anonymized_at`

Exécution:
- Via un script ou une tâche cron: `php bin/anonymize`
- Exemple : `0 3 1 * * /usr/bin/php /var/www/project/bin/anonymize.php >> /var/log/project/anonymize.log 2>&1`
