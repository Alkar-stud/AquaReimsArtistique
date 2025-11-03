<?php

namespace app\Utils;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;
use Exception;

class QRCode
{
    /**
     * Génère un QR code et retourne le chemin du fichier image temporaire
     *
     * @param string $data Données à encoder (URL, texte, etc.)
     * @param int $size Taille du QR code en pixels
     * @param int $margin Marge autour du QR code
     * @return string|null Chemin du fichier image généré, null en cas d'erreur
     */
    public static function generate(
        string $data,
        int $size = 300,
        int $margin = 10
    ): ?string {
        try {
            $qrCode = new EndroidQrCode($data, errorCorrectionLevel: ErrorCorrectionLevel::High );

            $writer = new PngWriter();
            $result = $writer->write($qrCode, null, null, [
                'size' => $size,
                'margin' => $margin
            ]);

            $tempFile = sys_get_temp_dir() . '/qrcode_' . uniqid() . '.png';
            $result->saveToFile($tempFile);

            return $tempFile;
        } catch (Exception $e) {
            error_log("Erreur génération QR code: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Génère un QR code et retourne directement les données binaires de l'image
     *
     * @param string $data Données à encoder
     * @param int $size Taille du QR code
     * @param int $margin Marge autour du QR code
     * @return string|null Données binaires PNG, null en cas d'erreur
     */
    public static function generateBinary(
        string $data,
        int $size = 300,
        int $margin = 10
    ): ?string {
        try {
            $qrCode = new EndroidQrCode($data, errorCorrectionLevel: ErrorCorrectionLevel::High );

            $writer = new PngWriter();
            $result = $writer->write($qrCode, null, null, [
                'size' => $size,
                'margin' => $margin
            ]);

            return $result->getString();
        } catch (Exception $e) {
            error_log("Erreur génération QR code binaire: " . $e->getMessage());
            return null;
        }
    }
}
