<?php
// app/Enums/LogType.php
namespace app\Enums;

enum LogType: string
{
    case ACCESS = 'access';      // Connexions, déconnexions, tentatives
    case DATABASE = 'db';        // INSERT, UPDATE, DELETE
    case URL = 'url';           // URLs demandées
    case APPLICATION = 'application'; //erreurs retournées par l'application
    case URL_ERROR = 'url_error'; //URLs qui donnent 404
    case SQL_ERROR = 'sql_errors'; //erreurs SQL
    case SECURITY = 'security'; //erreurs de sécurité
}