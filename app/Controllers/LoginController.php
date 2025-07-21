<?php
namespace app\Controllers;

use app\BaseController;
use app\Attributes\Route;

#[Route('/login', name: 'app_login')]
class LoginController extends BaseController
{
    public function index()
    {
        $this->render('Login');
    }
}