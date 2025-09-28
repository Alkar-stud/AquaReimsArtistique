<?php

namespace app\Services\DataValidation;

use app\Models\Event\Event;
use app\Models\Event\EventInscriptionDate;
use app\Models\Event\EventSession;
use app\Repository\Tarif\TarifRepository;
use DateTime;
use Throwable;

class EventDataValidationService
{
    private ?Event $validatedEvent = null;
    private array $validatedTarifs = [];
    private array $validatedSessions = [];
    private array $validatedInscriptionDates = [];
    private TarifRepository $tarifRepository;

    public function __construct()
    {
        $this->tarifRepository = new TarifRepository();
    }

    /**
     * Valide et nettoie les données POST pour la création d'un événement.
     * @param array $postData Les données de $_POST.
     * @return string|null Un message d'erreur si la validation échoue, sinon null.
     */
    public function checkData(array $postData): ?string
    {
        try {
            // --- Validation de l'événement principal ---
            $name = htmlspecialchars(mb_convert_case(trim($postData['name'] ?? ''), MB_CASE_TITLE, "UTF-8"), ENT_QUOTES, 'UTF-8');
            if (empty($name)) {
                return "Le libellé de l'événement est obligatoire.";
            }

            $placeId = filter_var($postData['place'] ?? null, FILTER_VALIDATE_INT);
            if ($placeId === false || $placeId <= 0) {
                return "Le lieu de l'événement est obligatoire.";
            }

            $limitation = filter_var($postData['limitation_per_swimmer'] ?? null, FILTER_VALIDATE_INT);
            $limitationPerSwimmer = ($limitation === false || $limitation <= 0) ? null : $limitation;

            $this->validatedEvent = (new Event())
                ->setName($name)
                ->setPlace($placeId)
                ->setLimitationPerSwimmer($limitationPerSwimmer);

            // --- Validation des tarifs ---
            $tarifIds = $postData['tarifs'] ?? [];
            if (!is_array($tarifIds) || empty($tarifIds)) {
                return "Au moins un tarif doit être sélectionné.";
            }
            $this->validatedTarifs = array_map('intval', $tarifIds);

            if (!$this->tarifRepository->hasSeatedTarif($this->validatedTarifs)) {
                return "La sélection doit inclure au moins un tarif 'avec places'.";
            }

            // --- Validation des séances ---
            $sessionsData = $postData['sessions'] ?? [];
            if (!is_array($sessionsData) || empty($sessionsData)) {
                return "Au moins une séance est obligatoire.";
            }

            foreach ($sessionsData as $index => $sessionData) {
                $sessionName = htmlspecialchars(trim($sessionData['session_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                if (empty($sessionName)) {
                    return "Le libellé est obligatoire pour toutes les séances.";
                }

                $startAt = new DateTime($sessionData['event_start_at'] ?? 'invalid');
                $openingAt = new DateTime($sessionData['opening_doors_at'] ?? 'invalid');

                if ($openingAt > $startAt) {
                    return "La date d'ouverture des portes ne peut pas être après le début de la séance pour la séance '$sessionName'.";
                }

                $session = (new EventSession())
                    ->setSessionName($sessionName)
                    ->setEventStartAt($startAt->format('Y-m-d H:i:s'))
                    ->setOpeningDoorsAt($openingAt->format('Y-m-d H:i:s'));

                $this->validatedSessions[] = $session;
            }

            // --- Validation des périodes d'inscription ---
            $inscriptionsData = $postData['inscription_dates'] ?? [];
            if (!is_array($inscriptionsData) || empty($inscriptionsData)) {
                return "Au moins une période d'inscription est obligatoire.";
            }

            foreach ($inscriptionsData as $index => $inscriptionData) {
                $name = htmlspecialchars(trim($inscriptionData['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                if (empty($name)) {
                    return "Le libellé est obligatoire pour toutes les périodes d'inscription.";
                }

                $startRegAt = new DateTime($inscriptionData['start_registration_at'] ?? 'invalid');
                $closeRegAt = new DateTime($inscriptionData['close_registration_at'] ?? 'invalid');
                $accessCode = htmlspecialchars(trim($inscriptionData['access_code'] ?? ''), ENT_QUOTES, 'UTF-8');

                if ($closeRegAt < $startRegAt) {
                    return "La date de clôture des inscriptions ne peut pas être avant la date d'ouverture pour la période '$name'.";
                }

                $inscription = (new EventInscriptionDate())
                    ->setName($name)
                    ->setStartRegistrationAt($startRegAt->format('Y-m-d H:i:s'))
                    ->setCloseRegistrationAt($closeRegAt->format('Y-m-d H:i:s'))
                    ->setAccessCode($accessCode);

                $this->validatedInscriptionDates[] = $inscription;
            }

        } catch (Throwable $e) {
            // Gère les dates invalides qui lèvent une exception dans le constructeur de DateTime
            return "Un format de date est invalide. Veuillez vérifier toutes les dates saisies.";
        }

        return null; // Pas d'erreur
    }

    public function getValidatedEvent(): ?Event { return $this->validatedEvent; }
    public function getValidatedTarifs(): array { return $this->validatedTarifs; }
    public function getValidatedSessions(): array { return $this->validatedSessions; }
    public function getValidatedInscriptionDates(): array { return $this->validatedInscriptionDates; }
}