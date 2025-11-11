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