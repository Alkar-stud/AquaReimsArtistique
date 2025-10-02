<?php
// php
namespace app\DTO;

use JsonSerializable;

class ReservationDetailItemDTO extends AbstractDTO implements JsonSerializable
{
    public function __construct(
        public int     $tarif_id,
        public ?string $tarif_access_code = null,
        public ?string $name = null,
        public ?string $firstname = null,
        public ?string $justificatif_name = null,
        public ?int    $place_number = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tarif_id: (int)($data['tarif_id'] ?? 0),
            tarif_access_code: self::nullIfEmpty($data['tarif_access_code'] ?? null),
            name: self::nullIfEmpty($data['name'] ?? null),
            firstname: self::nullIfEmpty($data['firstname'] ?? null),
            justificatif_name: self::nullIfEmpty($data['justificatif_name'] ?? null),
            // Préserve null si absent
            place_number: array_key_exists('place_number', $data) ? (int)$data['place_number'] : null,
        );
    }

    /**
     * Construit une liste d'items à partir du payload envoyé par le JS:
     * - tarifs: { <id>: <quantité> }
     * - special: { <id>: <code> }
     */
    public static function listFromPayload(array $payload): array
    {
        $tarifs = is_array($payload['tarifs'] ?? null) ? $payload['tarifs'] : [];
        $special = is_array($payload['special'] ?? null) ? $payload['special'] : [];

        $items = [];
        foreach ($tarifs as $id => $qty) {
            $idInt = (int)$id;
            $qtyInt = (int)$qty;
            if ($idInt <= 0 || $qtyInt <= 0) {
                continue;
            }

            // Récupère le code spécial si présent pour ce tarif
            $code = null;
            // Gestion des clés numériques/chaînes
            if (array_key_exists((string)$id, $special)) {
                $code = $special[(string)$id];
            } elseif (array_key_exists($idInt, $special)) {
                $code = $special[$idInt];
            }

            $items[] = new self(
                tarif_id: $idInt,
                tarif_access_code: self::nullIfEmpty($code ?? null),
                // Les champs suivants seront remplis aux étapes ultérieures
                name: null,
                firstname: null,
                justificatif_name: null,
                place_number: $qtyInt
            );
        }

        return $items;
    }
}
