# Aqua Reims Artistique — Variables des templates e‑mail

## Variables disponibles

- `name` \: prénom du réservant.
- `token` \: jeton de réservation.
- `qrcodeModif` \: chemin du PNG du QR code de modification.
- `qrcodeEntrance` \: chemin du PNG du QR code d’entrée.
- `qrcodeEntranceInMail` \: balise HTML \`<img>\` pour intégrer le QR code d’entrée inline \(`cid:qrcode_entrance`\).
- `IDreservation` \: numéro lisible de réservation \.
- `EventName` \: nom de l’évènement.
- `DateEvent` \: date/heure de début \(`format('d/m/Y à Hhi')`\).
- `DoorsOpen` \: heure d’ouverture des portes \(`format('d/m/Y à partir de Hhi')`\).
- `Piscine` \: libellé et adresse de la piscine.
- `ReservationNameFirstname` \: nom + prénom du réservant.
- `ReservationEmail` \: e‑mail du réservant.
- `ReservationPhone` \: téléphone \(`'-'` si vide\).
- `ReservationNbTotalPlace` \: nombre total de places \(`count($reservation->getDetails())`\).
- `AffichRecapDetailPlaces` \: récapitulatif HTML des places.
- `AffichRecapDetailPlacesText` \: récapitulatif texte des places.
- `UrlModifData` \: lien pour modifier les données \(`$buildLink->buildResetLink('/modifData', $reservation->getToken())`\).
- `TotalAPayer` \: libellé HTML du total à payer.
- `TotalAPayerText` \: libellé texte du total à payer.
- `ReservationMontantTotal` \: montant total formaté \(`xx,yy €`\).
- `SIGNATURE` \: signature du club.
- `email_club` \: e‑mail du club \(`EMAIL_CLUB` si défini\).
- `username` \: nom d’utilisateur \(\*pour mails de compte\).
- `link` \: lien de réinitialisation \(\*pour mails de compte\).

## Exemple d’injection des variables

```php
<?php
// PHP
$data = [
    'name' => $reservation->getFirstName(),
    'token' => $reservation->getToken(),
    'qrcodeModif' => $qrcodeModifPath,
    'qrcodeEntrance' => $qrcodeEntrancePath,
    'ReservationNameFirstname' => $reservation->getName() . ' ' . $reservation->getFirstName(),
    'ReservationEmail' => $reservation->getEmail(),
    'ReservationPhone' => !empty($reservation->getPhone()) ? $reservation->getPhone() : '-',
    'ReservationNbTotalPlace' => count($reservation->getDetails()),
    'AffichRecapDetailPlaces' => $recap['html'],
    'AffichRecapDetailPlacesText' => $recap['text'],
    'UrlModifData' => $buildLink->buildResetLink('/modifData', $reservation->getToken()),
    'TotalAPayer' => $payment['labelHtml'],
    'TotalAPayerText' => $payment['label'],
    'ReservationMontantTotal' => number_format($payment['amount'] / 100, 2, ',', ' ') . ' €',
    // Variables spécifiques aux mails de compte
    'username' => $username ?? null,
    'link' => $resetLink ?? null,
];
