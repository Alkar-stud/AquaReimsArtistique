<?php

namespace app\Services\Event;

/**
 * Catalogue centralisé des événements métiers connus par l'application.
 *
 * Chaque entrée décrit le comportement attendu : niveau par défaut, channel,
 * si l'évènement est auditable, s'il déclenche une notification, etc.
 */
final class EventCatalog
{
    /** @return EventDefinition[] */
    public static function all(): array
    {
        // Liste non exhaustive, mais structurée par domaines métier du projet.
        $map = [
            // ------------------------------------------------------------
            // Security / Auth / Users
            // ------------------------------------------------------------
            'security.login.failed' => new EventDefinition('security.login.failed', 'security', 'WARNING', true, false, 'Échec d authentification', false, 600),
            'security.login.success' => new EventDefinition('security.login.success', 'security', 'INFO', false, false, 'Authentification réussie', false, null),
            'security.bootstrap.super_admin_initialized' => new EventDefinition('security.bootstrap.super_admin_initialized', 'security', 'NOTICE', true, false, 'Compte super admin initialisé pendant l installation', true, null),

            'security.user.create.requested' => new EventDefinition('security.user.create.requested', 'security', 'INFO', true, false, 'Création d un compte demandée par un admin', true, null),
            'security.user.role_level.blocked' => new EventDefinition('security.user.role_level.blocked', 'security', 'WARNING', true, false, 'Création ou modification bloquée à cause du niveau de rôle cible', true, 600),
            'security.user.create.succeeded' => new EventDefinition('security.user.create.succeeded', 'security', 'INFO', true, false, 'Compte utilisateur créé avec succès', true, null),
            'security.user.create.privileged_succeeded' => new EventDefinition('security.user.create.privileged_succeeded', 'security', 'NOTICE', true, false, 'Compte créé avec un rôle privilégié', true, null),

            'security.user.update.requested' => new EventDefinition('security.user.update.requested', 'security', 'INFO', true, false, 'Mise à jour d un compte demandée', true, null),
            'security.user.update.succeeded' => new EventDefinition('security.user.update.succeeded', 'security', 'INFO', true, false, 'Compte utilisateur mis à jour', true, null),
            'security.user.delete.requested' => new EventDefinition('security.user.delete.requested', 'security', 'NOTICE', true, false, 'Suppression d un compte demandée', true, null),
            'security.user.delete.succeeded' => new EventDefinition('security.user.delete.succeeded', 'security', 'NOTICE', true, false, 'Compte utilisateur supprimé', true, null),

            'security.password_reset.requested' => new EventDefinition('security.password_reset.requested', 'security', 'INFO', true, false, 'Demande de réinitialisation de mot de passe', false, null),
            'security.password_reset.token_created' => new EventDefinition('security.password_reset.token_created', 'security', 'INFO', true, false, 'Token de réinitialisation généré', false, null),
            'security.password_reset.completed' => new EventDefinition('security.password_reset.completed', 'security', 'INFO', true, false, 'Réinitialisation de mot de passe terminée', true, null),
            'security.password_modified' => new EventDefinition('security.password_modified', 'security', 'INFO', true, false, 'Mot de passe modifié par l utilisateur', true, null),

            'security.csrf.invalid' => new EventDefinition('security.csrf.invalid', 'security', 'WARNING', true, false, 'Jeton CSRF invalide / expiré', false, 300),
            'security.unauthorized_access_attempt' => new EventDefinition('security.unauthorized_access_attempt', 'security', 'CRITICAL', true, true, 'Accès non autorisé détecté', true, 3600),

            // ------------------------------------------------------------
            // Mail
            // ------------------------------------------------------------
            'mail.user.new_account.requested' => new EventDefinition('mail.user.new_account.requested', 'mail', 'INFO', false, false, 'Demande d envoi du mail de création de compte', true, null),
            'mail.user.new_account.sent' => new EventDefinition('mail.user.new_account.sent', 'mail', 'INFO', false, false, 'Mail de création de compte envoyé', true, null),
            'mail.user.new_account.failed' => new EventDefinition('mail.user.new_account.failed', 'mail', 'ERROR', false, true, 'Échec d envoi du mail de création de compte', false, 1800),

            'mail.user.password_reset.requested' => new EventDefinition('mail.user.password_reset.requested', 'mail', 'INFO', false, false, 'Demande d envoi du mail de réinitialisation de mot de passe', true, null),
            'mail.user.password_reset.sent' => new EventDefinition('mail.user.password_reset.sent', 'mail', 'INFO', false, false, 'Mail de réinitialisation de mot de passe envoyé', true, null),
            'mail.user.password_reset.failed' => new EventDefinition('mail.user.password_reset.failed', 'mail', 'ERROR', false, true, 'Échec d envoi du mail de réinitialisation', false, 1800),

            'mail.user.password_modified.sent' => new EventDefinition('mail.user.password_modified.sent', 'mail', 'INFO', false, false, 'Mail de confirmation de changement de mot de passe envoyé', true, null),
            'mail.user.password_modified.failed' => new EventDefinition('mail.user.password_modified.failed', 'mail', 'ERROR', false, true, 'Échec d envoi du mail de confirmation de changement de mot de passe', false, 1800),

            'mail.reservation.confirmation.sent' => new EventDefinition('mail.reservation.confirmation.sent', 'mail', 'INFO', false, false, 'Mail de confirmation de réservation envoyé', true, null),
            'mail.reservation.confirmation.failed' => new EventDefinition('mail.reservation.confirmation.failed', 'mail', 'ERROR', false, true, 'Échec d envoi du mail de confirmation de réservation', false, 1800),
            'mail.reservation.final_summary.sent' => new EventDefinition('mail.reservation.final_summary.sent', 'mail', 'INFO', false, false, 'Mail récapitulatif final envoyé', true, null),
            'mail.reservation.final_summary.failed' => new EventDefinition('mail.reservation.final_summary.failed', 'mail', 'ERROR', false, true, 'Échec d envoi du mail récapitulatif final', false, 1800),
            'mail.reservation.cancel_confirmation.sent' => new EventDefinition('mail.reservation.cancel_confirmation.sent', 'mail', 'INFO', false, false, 'Mail de confirmation d annulation envoyé', true, null),
            'mail.reservation.cancel_confirmation.failed' => new EventDefinition('mail.reservation.cancel_confirmation.failed', 'mail', 'ERROR', false, true, 'Échec d envoi du mail de confirmation d annulation', false, 1800),

            'mail.template.missing' => new EventDefinition('mail.template.missing', 'mail', 'WARNING', false, false, 'Template mail introuvable', false, 3600),
            'mail.smtp.failed' => new EventDefinition('mail.smtp.failed', 'mail', 'ERROR', false, true, 'Échec d envoi SMTP', false, 1800),
            'mail.unexpected.send_attempt' => new EventDefinition('mail.unexpected.send_attempt', 'mail', 'CRITICAL', true, true, 'Tentative d envoi de mail hors workflow attendu', false, 3600),

            // ------------------------------------------------------------
            // Reservation
            // ------------------------------------------------------------
            'reservation.persist.started' => new EventDefinition('reservation.persist.started', 'reservation', 'INFO', true, false, 'Début de persistance d une réservation', true, null),
            'reservation.persist.completed' => new EventDefinition('reservation.persist.completed', 'reservation', 'INFO', true, false, 'Persistance de réservation terminée avec succès', true, null),
            'reservation.persist.failed' => new EventDefinition('reservation.persist.failed', 'reservation', 'ERROR', true, true, 'Échec de persistance d une réservation', true, 1800),

            'reservation.manual_payment.marked' => new EventDefinition('reservation.manual_payment.marked', 'reservation', 'NOTICE', true, false, 'Réservation marquée payée manuellement', true, null),
            'reservation.token.updated' => new EventDefinition('reservation.token.updated', 'reservation', 'INFO', true, false, 'Token de réservation modifié', true, null),
            'reservation.token.update.failed' => new EventDefinition('reservation.token.update.failed', 'reservation', 'ERROR', true, true, 'Échec de mise à jour du token de réservation', false, 1800),

            'reservation.cancel.requested' => new EventDefinition('reservation.cancel.requested', 'reservation', 'INFO', true, false, 'Demande d annulation de réservation', true, null),
            'reservation.cancel.completed' => new EventDefinition('reservation.cancel.completed', 'reservation', 'NOTICE', true, false, 'Réservation annulée / réactivée', true, null),
            'reservation.cancel.blocked_with_registrations' => new EventDefinition('reservation.cancel.blocked_with_registrations', 'reservation', 'WARNING', true, true, 'Annulation bloquée car des inscriptions existent déjà', true, 3600),

            'reservation.complement.added' => new EventDefinition('reservation.complement.added', 'reservation', 'INFO', false, false, 'Complément ajouté à une réservation', true, null),
            'reservation.complement.updated' => new EventDefinition('reservation.complement.updated', 'reservation', 'INFO', false, false, 'Complément mis à jour', true, null),
            'reservation.complement.deleted' => new EventDefinition('reservation.complement.deleted', 'reservation', 'INFO', false, false, 'Complément supprimé', true, null),
            'reservation.detail.updated' => new EventDefinition('reservation.detail.updated', 'reservation', 'INFO', false, false, 'Participant de réservation mis à jour', true, null),
            'reservation.contact.updated' => new EventDefinition('reservation.contact.updated', 'reservation', 'INFO', false, false, 'Coordonnées de réservation mises à jour', true, null),

            'reservation.email.confirmation.sent' => new EventDefinition('reservation.email.confirmation.sent', 'reservation', 'INFO', false, false, 'Mail de confirmation de réservation envoyé', true, null),
            'reservation.email.confirmation.failed' => new EventDefinition('reservation.email.confirmation.failed', 'reservation', 'ERROR', false, true, 'Échec d envoi du mail de confirmation de réservation', false, 1800),
            'reservation.email.final_summary.sent' => new EventDefinition('reservation.email.final_summary.sent', 'reservation', 'INFO', false, false, 'Mail récapitulatif final envoyé pour une réservation', true, null),
            'reservation.email.final_summary.failed' => new EventDefinition('reservation.email.final_summary.failed', 'reservation', 'ERROR', false, true, 'Échec d envoi du mail récapitulatif final', false, 1800),
            'reservation.email.cancel_confirmation.sent' => new EventDefinition('reservation.email.cancel_confirmation.sent', 'reservation', 'INFO', false, false, 'Mail de confirmation d annulation de réservation envoyé', true, null),
            'reservation.email.cancel_confirmation.failed' => new EventDefinition('reservation.email.cancel_confirmation.failed', 'reservation', 'ERROR', false, true, 'Échec d envoi du mail de confirmation d annulation de réservation', false, 1800),

            'reservation.temp.cleaned' => new EventDefinition('reservation.temp.cleaned', 'reservation', 'INFO', false, false, 'Nettoyage de session temporaire réservation', true, null),
            'reservation.payment.failed' => new EventDefinition('reservation.payment.failed', 'reservation', 'ERROR', true, true, 'Paiement réservation échoué', true, 1800),

            // ------------------------------------------------------------
            // Event / Manifestation (domain spécifique "Event" = manifestation)
            // ------------------------------------------------------------
            // Création / modification / suppression d'une manifestation (évènement public)
            'event.create.requested' => new EventDefinition('event.create.requested', 'event', 'INFO', true, false, 'Création d\'une manifestation demandée', true, null),
            'event.create.succeeded' => new EventDefinition('event.create.succeeded', 'event', 'INFO', true, false, 'Création de la manifestation effectuée', true, null),
            'event.create.failed' => new EventDefinition('event.create.failed', 'event', 'ERROR', true, true, 'Échec lors de la création d\'une manifestation', true, 1800),

            'event.update.requested' => new EventDefinition('event.update.requested', 'event', 'INFO', true, false, 'Demande de modification d\'une manifestation', true, null),
            'event.update.succeeded' => new EventDefinition('event.update.succeeded', 'event', 'INFO', true, false, 'Modification de la manifestation effectuée', true, null),
            'event.update.failed' => new EventDefinition('event.update.failed', 'event', 'ERROR', true, true, 'Échec lors de la modification d\'une manifestation', true, 1800),

            // Suppression — cas particulier : si des inscriptions existent, c'est plus critique
            'event.delete.requested' => new EventDefinition('event.delete.requested', 'event', 'NOTICE', true, false, 'Demande de suppression d\'une manifestation', true, null),
            'event.delete.succeeded' => new EventDefinition('event.delete.succeeded', 'event', 'NOTICE', true, false, 'Manifestation supprimée', true, null),
            'event.delete.blocked_with_registrations' => new EventDefinition('event.delete.blocked_with_registrations', 'event', 'WARNING', true, true, 'Suppression bloquée : des inscriptions existent', true, 3600),
            'event.delete.failed' => new EventDefinition('event.delete.failed', 'event', 'ERROR', true, true, 'Échec lors de la suppression d\'une manifestation', true, 1800),

            // Publication / visibilité
            'event.publish' => new EventDefinition('event.publish', 'event', 'NOTICE', true, false, 'Manifestation publiée (visible aux utilisateurs)', true, null),
            'event.unpublish' => new EventDefinition('event.unpublish', 'event', 'NOTICE', true, false, 'Manifestation dépubliée (retirée de la visibilité)', true, null),

            // Gestion des inscriptions / capacités
            'event.registration.opened' => new EventDefinition('event.registration.opened', 'event', 'INFO', true, false, 'Ouverture des inscriptions pour une manifestation', true, null),
            'event.registration.closed' => new EventDefinition('event.registration.closed', 'event', 'INFO', true, false, 'Fermeture des inscriptions pour une manifestation', true, null),
            'event.capacity.changed' => new EventDefinition('event.capacity.changed', 'event', 'NOTICE', true, false, 'Capacité (places) de la manifestation modifiée', true, null),
            'event.capacity.reduced' => new EventDefinition('event.capacity.reduced', 'event', 'WARNING', true, true, 'Capacité réduite : impact possible sur inscriptions existantes', true, 3600),
            'event.capacity.exceeded' => new EventDefinition('event.capacity.exceeded', 'event', 'CRITICAL', true, true, 'Capacité dépassée — incohérence détectée', true, 3600),

            // Import / synchronisation d'évènements
            'event.import.started' => new EventDefinition('event.import.started', 'event', 'INFO', true, false, 'Début d\'import de manifestations (batch)', false, null),
            'event.import.completed' => new EventDefinition('event.import.completed', 'event', 'INFO', true, false, 'Import de manifestations terminé', false, null),
            'event.import.failed' => new EventDefinition('event.import.failed', 'event', 'ERROR', true, true, 'Échec lors de l\'import des manifestations', false, 1800),

            // ------------------------------------------------------------
            // Backoffice / Content management
            // ------------------------------------------------------------
            'application.admin.event_presentation.created' => new EventDefinition('application.admin.event_presentation.created', 'application', 'INFO', true, false, 'Présentation de la page d accueil créée', true, null),
            'application.admin.event_presentation.updated' => new EventDefinition('application.admin.event_presentation.updated', 'application', 'INFO', true, false, 'Présentation de la page d accueil mise à jour', true, null),
            'application.admin.event_presentation.deleted' => new EventDefinition('application.admin.event_presentation.deleted', 'application', 'NOTICE', true, false, 'Présentation de la page d accueil supprimée', true, null),

            'application.admin.tariff.created' => new EventDefinition('application.admin.tariff.created', 'application', 'INFO', true, false, 'Tarif créé', true, null),
            'application.admin.tariff.updated' => new EventDefinition('application.admin.tariff.updated', 'application', 'INFO', true, false, 'Tarif mis à jour', true, null),
            'application.admin.tariff.deleted' => new EventDefinition('application.admin.tariff.deleted', 'application', 'NOTICE', true, false, 'Tarif supprimé', true, null),

            'application.admin.mail_template.created' => new EventDefinition('application.admin.mail_template.created', 'application', 'INFO', true, false, 'Template mail créé', true, null),
            'application.admin.mail_template.updated' => new EventDefinition('application.admin.mail_template.updated', 'application', 'INFO', true, false, 'Template mail mis à jour', true, null),
            'application.admin.mail_template.deleted' => new EventDefinition('application.admin.mail_template.deleted', 'application', 'NOTICE', true, false, 'Template mail supprimé', true, null),

            'application.admin.config.created' => new EventDefinition('application.admin.config.created', 'application', 'NOTICE', true, false, 'Configuration créée', true, null),
            'application.admin.config.updated' => new EventDefinition('application.admin.config.updated', 'application', 'NOTICE', true, false, 'Configuration mise à jour', true, null),
            'application.admin.config.deleted' => new EventDefinition('application.admin.config.deleted', 'application', 'WARNING', true, false, 'Configuration supprimée', true, null),

            'application.admin.swimmer.created' => new EventDefinition('application.admin.swimmer.created', 'application', 'INFO', true, false, 'Nageur créé', true, null),
            'application.admin.swimmer.updated' => new EventDefinition('application.admin.swimmer.updated', 'application', 'INFO', true, false, 'Nageur mis à jour', true, null),
            'application.admin.swimmer.deleted' => new EventDefinition('application.admin.swimmer.deleted', 'application', 'NOTICE', true, false, 'Nageur supprimé', true, null),

            // ------------------------------------------------------------
            // Database / Filesystem / Application errors
            // ------------------------------------------------------------
            'database.query.failed' => new EventDefinition('database.query.failed', 'database', 'ERROR', true, true, 'Requête SQL échouée', false, 1800),
            'database.query.slow' => new EventDefinition('database.query.slow', 'database', 'NOTICE', false, false, 'Requête SQL lente', false, null),

            'filesystem.delete.failed' => new EventDefinition('filesystem.delete.failed', 'filesystem', 'ERROR', false, false, 'Échec suppression fichier', false, 3600),
            'filesystem.upload.failed' => new EventDefinition('filesystem.upload.failed', 'filesystem', 'ERROR', false, false, 'Échec upload fichier', false, 3600),

            'application.template.render_failed' => new EventDefinition('application.template.render_failed', 'application', 'ERROR', true, false, 'Erreur rendu template', false, 1800),
            'application.uncaught_exception' => new EventDefinition('application.uncaught_exception', 'application', 'CRITICAL', true, true, 'Exception non gérée', true, 3600),

            // ------------------------------------------------------------
            // Access / URL
            // ------------------------------------------------------------
            'access.request' => new EventDefinition('access.request', 'access', 'INFO', false, false, 'Requête HTTP (access log)', false, null),
            'url.not_found' => new EventDefinition('url.not_found', 'url', 'NOTICE', false, false, 'Erreur 404', false, null),
        ];

        return $map;
    }

    public static function get(string $code): ?EventDefinition
    {
        $all = self::all();
        return $all[$code] ?? null;
    }
}
