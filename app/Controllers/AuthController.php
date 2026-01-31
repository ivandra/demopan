<?php

class AuthController extends Controller
{
    public function loginForm(): void
    {
        $this->view('auth/login');
    }

    public function login(): void
    {
        // MVP-заглушка
        $_SESSION['user_id'] = 1;
        $this->redirect('/');
    }
}
