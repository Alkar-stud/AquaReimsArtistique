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
- Exemple : `0 3 1 * * /usr/bin/php /var/www/project/bin/anonymize >> /var/log/project/storage/log/anonymize.log 2>&1`

## Envoi des emails récapitulatifs

Ce projet permet l'envoi automatique d'emails récapitulatifs aux participants dont la période d'inscription est terminée.

### Conditions d'envoi

Un email récapitulatif final est envoyé automatiquement aux réservations qui remplissent **toutes** les conditions suivantes :

- ✅ **Réservation active** : `is_canceled = 0` (non annulée)
- ✅ **Session à venir** : La date de début de la session (`event_start_at`) est dans le futur
- ✅ **Inscriptions closes** : La date de clôture des inscriptions (`close_registration_at`) est dépassée
- ✅ **Email non déjà envoyé** : Le template `final_summary` n'a pas encore été envoyé pour cette réservation

*Note : La requête vérifie la date de clôture la plus récente de l'événement via la table `event_inscription_date`.*

### Configuration

L'envoi peut se faire de deux manières :

#### 1. Via ligne de commande (recommandé pour CRON)

```bash
# Avec limite par défaut (100 emails)
php bin/send-recap-email

# Avec limite personnalisée
php bin/send-recap-email 50
```

**Paramètres :**
- `limit` (optionnel) : nombre maximum d'emails à envoyer par exécution (défaut: 100)

**Exemple de configuration CRON :**
```bash
# Toutes les 10 minutes entre minuit et 5h du matin, envoyer 200 emails maximum
*/10 0-4 * * * cd /var/www/project && /usr/bin/php bin/send-recap-email 200 >> /var/log/project/storage/log/recap-email.log 2>&1
```

**Avantages :**
- ✅ Pas de token de sécurité à gérer
- ✅ Accès direct au serveur
- ✅ Logs facilités

#### 2. Via route HTTP (avec token de sécurité)

**URL :** `GET /reservations/send-final-recap?token=...&limit=100`

**Paramètres :**
- `token` (obligatoire) : token de sécurité défini dans la constante `CRON_TOKEN` (table `config`)
- `limit` (optionnel) : nombre maximum d'emails à envoyer (défaut: 100)

**Exemple :**
```bash
curl "https://votre-domaine.com/reservations/send-final-recap?token=VOTRE_TOKEN&limit=50"
```

**Configuration CRON avec wget :**
```bash
# Toutes les 10 minutes entre minuit et 5h du matin
*/10 0-4 * * * wget -q -O - "https://votre-domaine.com/reservations/send-final-recap?token=VOTRE_TOKEN&limit=200" >> /var/log/project/storage/log/recap-email.log 2>&1
```

**Avantages :**
- ✅ Pas besoin d'accès SSH au serveur
- ✅ Peut être déclenché depuis un service externe (webhook, monitoring)

### Sécurité

- **CLI** : Aucun token requis (nécessite un accès serveur)
- **HTTP** : Token obligatoire pour éviter les déclenchements non autorisés

### Contenu de l'email

L'email récapitulatif final inclut :
- 📄 **PDF récapitulatif** en pièce jointe
- 🔲 **QR Code d'entrée** intégré dans le corps du mail (image inline)
- 📝 Informations complètes de la réservation

### Retour de la commande

La commande affiche :
- Le nombre d'emails envoyés avec succès
- Le nombre d'erreurs rencontrées
- Code de sortie : `0` en cas de succès, `1` en cas d'erreur

**Exemple de sortie :**
```
Début de l'envoi des emails récapitulatifs...
Limite d'envoi : 100
Nombre d'emails envoyés : 42
Nombre d'erreurs : 0
Processus terminé avec succès.
```

### Limitation et performance

- ⏱️ **Délai entre envois** : 1 seconde (respect des limites SMTP)
- 📊 **Traitement par lot** : Utilisation de la limite pour éviter la surcharge
- 🔄 **Exécution répétable** : Seules les réservations non traitées sont concernées

