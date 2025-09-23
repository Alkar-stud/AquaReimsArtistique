<?php
declare(strict_types=1);

namespace app\Controllers\Install;

use app\Attributes\Route;
use app\Repository\User\UserRepository;
use app\Services\Mails\MailPrepareService;
use app\Traits\HasPdoConnection;
use app\Utils\BuildLink;
use DateTime;
use Exception;
use PDO;
use PDOException;
use Random\RandomException;

class MigrationController
{
    use HasPdoConnection;
    private BuildLink $buildLink;

    private const string MIGRATIONS_TABLE = 'migrations';
    private string $migrationPath = __DIR__ . '/../../../database/migrations/';

    public function __construct()
    {
        $this->initPdo();
        $this->buildLink = new BuildLink();
    }

    #[Route('/install', name: 'app_install')]
    public function installOrUpdate(): void
    {
        $this->ensureSessionStarted();

        $applied = $this->getAppliedMigrations();
        $files = $this->getMigrationFiles();

        if ($files === []) {
            http_response_code(404);
            echo "Aucun fichier de migration .sql trouvé dans " . htmlspecialchars($this->migrationPath) . "<br>";
            echo '<a href="/">Retour à l\'accueil</a>';
            return;
        }

        $toApply = array_values(array_diff($files, $applied));
        sort($toApply);

        $userMigrationApplied = false;

        if (!empty($toApply)) {
            ['appliedNow' => $appliedNow, 'userMigrationApplied' => $userMigrationApplied] = $this->applyMigrations($toApply);
            foreach ($appliedNow as $file) {
                echo "Migration appliquée : " . htmlspecialchars($file) . "<br>";
            }
            if ($userMigrationApplied) {
                $_SESSION['applied_migrations'] = $appliedNow;
            }
        }

        // Doit-on afficher le formulaire email admin ?
        $needEmail = $userMigrationApplied
            || isset($_SESSION['applied_migrations'])
            || $this->shouldPromptForAdminEmail();

        if ($needEmail) {
            $error = '';

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['email'])) {
                $email = trim((string)($_POST['email']));
                $error = $this->processAdminEmail($email);
                if ($error === '') {
                    echo "Email du user id=1 mis à jour avec succès.<br>";
                    echo "Un email de réinitialisation de mot de passe a été envoyé à l'adresse fournie.<br>";

                    if (!empty($_SESSION['applied_migrations'])) {
                        echo "Fichiers de migration traités :<ul>";
                        foreach ($_SESSION['applied_migrations'] as $file) {
                            echo "<li>" . htmlspecialchars((string)$file) . "</li>";
                        }
                        echo "</ul>";
                        unset($_SESSION['applied_migrations']);
                    }
                    echo '<a href="/">Retour à l\'accueil</a>';
                    return;
                }
            }

            $this->renderAdminEmailForm($error);
            return;
        }

        if (empty($toApply)) {
            $this->redirect('/404');
            return;
        }

        echo '<a href="/">Retour à l\'accueil</a>';
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function getAppliedMigrations(): array
    {
        $this->pdo->query(
            "CREATE TABLE IF NOT EXISTS " . self::MIGRATIONS_TABLE .
            " (name VARCHAR(255) PRIMARY KEY, executed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP)"
        );
        $stmt = $this->pdo->query("SELECT name FROM " . self::MIGRATIONS_TABLE);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationPath) || !is_readable($this->migrationPath)) {
            return [];
        }
        $files = scandir($this->migrationPath);
        if (!is_array($files)) {
            return [];
        }
        $sqlFiles = array_values(array_filter($files, static function ($file) {
            return is_string($file) && pathinfo($file, PATHINFO_EXTENSION) === 'sql';
        }));
        sort($sqlFiles);
        return $sqlFiles;
    }

    /**
     * @param string[] $files
     * @return array{appliedNow:string[], userMigrationApplied:bool}
     */
    private function applyMigrations(array $files): array
    {
        $appliedNow = [];
        $userMigrationApplied = false;

        foreach ($files as $file) {
            try {
                $sqlPath = $this->migrationPath . $file;
                $sql = file_get_contents($sqlPath);
                if ($sql === false) {
                    echo "Erreur lors de la lecture du fichier " . htmlspecialchars($file) . "<br>";
                    continue;
                }

                $this->pdo->beginTransaction();
                $this->pdo->exec($sql);
                $stmt = $this->pdo->prepare("INSERT INTO " . self::MIGRATIONS_TABLE . " (name) VALUES (:name)");
                $stmt->execute(['name' => $file]);
                $this->pdo->commit();

                $appliedNow[] = $file;

                // Heuristique large : toute migration mentionnant "user" déclenche la suite d'initialisation
                if (stripos($file, 'user') !== false) {
                    $userMigrationApplied = true;
                }
            } catch (PDOException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                echo "Erreur lors de la migration " . htmlspecialchars($file) . " : " . htmlspecialchars($e->getMessage()) . "<br>";
            }
        }

        // Si la table/user existe maintenant, on force l'étape email
        $userMigrationApplied = $userMigrationApplied || $this->doesAdminUserExist();

        return ['appliedNow' => $appliedNow, 'userMigrationApplied' => $userMigrationApplied];
    }

    private function shouldPromptForAdminEmail(): bool
    {
        try {
            $stmt = $this->pdo->query("SELECT email FROM user WHERE id = 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            $email = trim((string)($row['email'] ?? ''));
            return $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL);
        } catch (PDOException) {
            return false; // table user absente
        }
    }

    private function doesAdminUserExist(): bool
    {
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM user WHERE id = 1");
            return (bool)$stmt->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    private function processAdminEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse email invalide.';
        }

        $userRepo = new UserRepository();
        $user = $userRepo->findById(1);
        $displayName = $user?->getDisplayName() ?? 'Super Admin';

        if (!$userRepo->updateData(1, $displayName, $email)) {
            return 'Impossible de mettre à jour l’email.';
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (RandomException) {
            return 'Impossible de générer un token de réinitialisation.';
        }

        $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
        if (!$userRepo->savePasswordResetToken(1, $token, $expiresAt)) {
            return 'Impossible d’enregistrer le token de réinitialisation.';
        }

        try {
            $resetLink = $this->buildLink->buildResetLink('/reset-password', $token);
            (new MailPrepareService())->sendPasswordResetEmail($email, $displayName, $resetLink);
        } catch (Exception $e) {
            error_log('Erreur lors de l\'envoi de l\'email de réinitialisation : ' . $e->getMessage());
        }

        return '';
    }

    private function renderAdminEmailForm(string $error = ''): void
    {
        ?>
        <form method="post">
            <label for="email">Nouvelle adresse email pour l'utilisateur super admin, un lien de réinitialisation y sera envoyé :</label>
            <input type="email" name="email" id="email" required>
            <button type="submit">Mettre à jour</button>
            <?php if ($error !== ''): ?>
                <p style='color:red;'><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
        </form>
        <?php
    }

}
