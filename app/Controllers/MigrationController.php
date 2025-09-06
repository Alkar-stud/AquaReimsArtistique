<?php

namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\User\UserRepository;
use app\Services\MailService;
use app\Traits\HasPdoConnection;
use DateTime;
use Exception;
use PDO;
use PDOException;
use Random\RandomException;

class MigrationController
{
    use HasPdoConnection;

    private string $migrationPath = __DIR__ . '/../../database/migrations/';

    public function __construct()
    {
        $this->initPdo();

    }

    private function getAppliedMigrations(): array
    {
        $this->pdo->query("CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(255) PRIMARY KEY, executed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $stmt = $this->pdo->query("SELECT name FROM migrations");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(): false|array
    {
        return array_filter(scandir($this->migrationPath), function($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'sql';
        });
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    #[Route('/install', name: 'app_install')]
    public function installOrUpdate(): void
    {
        // Appliquer les migrations si besoin
        $applied = $this->getAppliedMigrations();
        $files = $this->getMigrationFiles();
        $toApply = array_diff($files, $applied);

        $userMigrationApplied = false;
        $appliedNow = [];

        if (!empty($toApply)) {
            foreach ($toApply as $file) {
                try {
                    $sql = file_get_contents($this->migrationPath . $file);
                    $this->pdo->exec($sql);
                    $stmt = $this->pdo->prepare("INSERT INTO migrations (name) VALUES (:name)");
                    $stmt->execute(['name' => $file]);
                    echo "Migration appliquée : $file<br>";
                    $appliedNow[] = $file;

                    if (stripos($file, '_create_user') !== false) {
                        $userMigrationApplied = true;
                    }
                } catch (PDOException $e) {
                    echo "Erreur lors de la migration $file : " . $e->getMessage() . "<br>";
                }
            }
            // On stocke la liste pour affichage après l'email
            if ($userMigrationApplied) {
                $_SESSION['applied_migrations'] = $appliedNow;
            }
        }

        // Si la migration user a été appliquée ou déjà faite, demander l'email
        $needEmail = $userMigrationApplied || isset($_SESSION['applied_migrations']);

        if ($needEmail) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
                $email = trim($_POST['email']);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $userRepo = new UserRepository();

                    // Met à jour l'email (display_name vide ici)
                    $userRepo->updateData(1, '', $email);

                    // Génère un token de reset
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
                    $userRepo->savePasswordResetToken(1, $token, $expiresAt);

                    // Envoie le mail (inchangé)
                    try {
                        if (class_exists('\app\Services\MailService')) {
                            $mailService = new MailService();
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                            $resetLink = $protocol . $_SERVER['HTTP_HOST'] . '/reset-password?token=' . $token;
                            $mailService->sendPasswordResetEmail($email, 'Super Admin', $resetLink);
                        }
                    } catch (Exception $e) {
                        error_log('Erreur lors de l\'envoi de l\'email de réinitialisation : ' . $e->getMessage());
                    }

                    echo "Email du user id=1 mis à jour avec succès.<br>";
                    echo "Un email de réinitialisation de mot de passe a été envoyé à l'adresse fournie.<br>";

                    if (!empty($_SESSION['applied_migrations'])) {
                        echo "Fichiers de migration traités :<ul>";
                        foreach ($_SESSION['applied_migrations'] as $file) {
                            echo "<li>" . htmlspecialchars($file) . "</li>";
                        }
                        echo "</ul>";
                        echo '<a href="/">Retour à l\'accueil</a>';;
                        unset($_SESSION['applied_migrations']);
                    }
                    return;
                } else {
                    $error = "Adresse email invalide.";
                }
            }

            // Affiche le formulaire HTML
            ?>
            <form method="post">
                <label for="email">Nouvelle adresse email pour l'utilisateur super admin, un lien de réinitialisation y sera envoyé :</label>
                <input type="email" name="email" id="email" required>
                <button type="submit">Mettre à jour</button>
                <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
            </form>
            <?php
            return;
        }

        // Si aucune migration à faire et pas besoin d'email
        if (empty($toApply)) {
            throw new Exception('404');
        } else {
            echo '<a href="/">Retour à l\'accueil</a>';
        }
    }
}