<?php
// app/Enums/LogType.php
namespace app\Enums;

enum LogType: string
{
    case ACCESS = 'access';      // Connexions, déconnexions, tentatives
    case DATABASE = 'database';  // INSERT, UPDATE, DELETE
    case URL = 'url';           // URLs demandées
    case ROUTE = 'route';       // Routes appelées
    case APPLICATION = 'application'; //erreurs retournées par l'application
    case URL_ERROR = 'url_error'; //URLs qui donnent 404
}