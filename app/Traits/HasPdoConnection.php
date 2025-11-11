<?php
namespace app\Traits;

use app\Core\Database;
use PDO;

trait HasPdoConnection
{
    protected PDO $pdo;

    protected function initPdo(): void
    {
        $this->pdo = Database::getInstance();
    }
}
