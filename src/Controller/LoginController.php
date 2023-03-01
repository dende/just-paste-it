<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function index(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        return $this->render('login/index.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(AuthenticationUtils $authenticationUtils, Request $request): ?Response
    {
        return null;
    }

    #[Route('/register', name: 'register')]
    public function showRegistration(Request $request): Response
    {
        $error = null;
        $lastUsername = null;
        return $this->render('register/index.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/register', name: 'register')]
    public function performRegistration(Request $request): Response
    {
        $error = null;
        $lastUsername = null;
        return $this->render('register/index.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

}


