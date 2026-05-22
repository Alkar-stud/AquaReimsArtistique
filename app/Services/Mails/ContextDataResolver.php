<?php

namespace app\Services\Mails;

use app\Models\Reservation\Reservation;
use app\Models\User\User;
use app\Services\Reservation\PaymentStatusCalculator;
use app\Services\Reservation\ReservationSummaryBuilder;
use app\Utils\BuildLink;
use app\Utils\QRCode;
use app\Utils\StringHelper;

/**
 * Clés disponibles utilisées dans les templates
 *
 * {username}
 * {link}
 * {email_club}
 * {app_name}
 * {timeout_token_new_account}
 * {name}
 * {IDreservation}
 * {EventName}
 * {DateEvent}
 * {Piscine}
 * {ReservationNameFirstname}
 * {ReservationNbTotalPlace}
 * {AffichRecapDetailPlaces}
 * {AffichRecapDetailPlacesText}
 * {TotalAPayer}
 * {ReservationMontantTotal}
 * {TotalAPayerText}
 * {SIGNATURE}
 * {DoorsOpen}
 * {email_gala}
 * {qrcodeEntranceInMail}
 * {UrlModifData}
 *
 */
class ContextDataResolver
{
    private ReservationSummaryBuilder $reservationSummaryBuilder;
    private PaymentStatusCalculator $paymentStatusCalculator;

    public function __construct(
        ReservationSummaryBuilder $reservationSummaryBuilder,
        PaymentStatusCalculator $paymentStatusCalculator,
    )
    {
        $this->reservationSummaryBuilder = $reservationSummaryBuilder;
        $this->paymentStatusCalculator = $paymentStatusCalculator;
    }

    public function resolve(array $contextData, array $keys = [], bool $withAttachment = false): array
    {
        $resolved = [];


        // Gestion des objets
        if (isset($contextData['user']) && $contextData['user'] instanceof User) {
            $user = $contextData['user'];
            $resolved['username'] = $user->getUsername();
            $resolved['email'] = $user->getEmail();
            $resolved['displayName'] = $user->getDisplayName();
            $resolved['timeout_token_new_account'] = $user->getPasswordResetExpiresAt();
        }

        if (isset($contextData['reservation']) && $contextData['reservation'] instanceof Reservation) {
            $reservation = $contextData['reservation'];
            $buildLink = new BuildLink();

            $recap = $this->reservationSummaryBuilder->buildFullRecap($reservation);
            $payment = $this->paymentStatusCalculator->calculate($reservation);

            $resolved['EventName'] = $reservation->getEventObject()->getName();
            $resolved['DateEvent'] = $reservation->getEventSessionObject()->getEventStartAt()->format('d/m/Y \à H\hi');
            $resolved['DoorsOpen'] = $reservation->getEventSessionObject()->getOpeningDoorsAt()->format('d/m/Y \à \p\a\r\t\i\r \d\e H\hi');
            $resolved['Piscine'] = $reservation->getEventObject()->getPiscine()->getLabel() . ' (' . $reservation->getEventObject()->getPiscine()->getAddress() . ' )';

            $resolved['IDreservation'] = StringHelper::generateReservationNumber($reservation->getId());
            $resolved['name'] = $reservation->getFirstName();
            $resolved['ReservationNameFirstname'] = $reservation->getName() . ' ' . $reservation->getFirstName();
            $resolved['ReservationNbTotalPlace'] = count($reservation->getDetails());
            $resolved['AffichRecapDetailPlaces'] = $recap['html'];
            $resolved['AffichRecapDetailPlacesText'] = $recap['text'];
            $resolved['TotalAPayer'] = $payment['labelHtml'];
            $resolved['TotalAPayerText'] = $payment['label'];
            $resolved['ReservationMontantTotal'] = number_format($payment['amount'] / 100, 2, ',', ' ') . ' €';
            $resolved['UrlModifData'] = $buildLink->buildResetLink('/modifData', $reservation->getToken());

            // Génération du QR code pour modification (fichier temporaire pour PDF)
            if (in_array('qrcodeEntranceInMail', $keys) || $withAttachment) {
                $qrCodeUrlEntrance = $buildLink->buildResetLink('/entrance', $reservation->getToken());
                // Génération du QR code d'entrée : on retourne un chemin de fichier (pour l'inline)
                $qrcodeEntrancePath = QRCode::generate($qrCodeUrlEntrance, 250, 10);
                //Génération pour le QRCode dans le mail
                $qrcodeEntranceInMail = '<img src="cid:qrcode_entrance" alt="QR Code d\'entrée" style="max-width: 250px; height: auto; display: block; margin: 20px auto;" />Montrez ce QR code pour faciliter votre entrée';

                $resolved['qrcodeEntrance'] = $qrcodeEntrancePath;
                $resolved['qrcodeEntranceInMail'] = $qrcodeEntranceInMail;

                if ($withAttachment) {
                    $resolved['attachments'] = [
                        [
//                            'path' => $contextData['pdfPath'],
//                            'filename' => $contextData['pdfName'],
                            'cid' => 'qrcode_entrance'
                        ]
                    ];
                }
            }
        }

        // Gestion des clés simples
        if (in_array('SIGNATURE', $keys)) {
            $resolved['SIGNATURE'] = SIGNATURE;
        }
        if (isset($keys['email_club'])) {
            $resolved['email_club'] = EMAIL_CLUB;
        }
        if (isset($keys['email_gala'])) {
            $resolved['email_gala'] = EMAIL_GALA;
        }
        if (isset($keys['app_name'])) {
            $resolved['app_name'] = $_ENV['APP_NAME'];
        }
        if (isset($keys['link'])) {
            $resolved['link'] = $contextData['link'];
        }
        if (isset($keys['username'])) {
            $resolved['username'] = $contextData['username'];
        }

        return $resolved;
    }
}