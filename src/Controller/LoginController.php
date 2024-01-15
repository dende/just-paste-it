<?php

namespace App\Controller;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

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

    #[Route('/changepw', name: 'show_change_password', methods: ['GET'])]
    public function changepw(AuthenticationUtils $authenticationUtils, Request $request): ?Response
    {
        return $this->render("user/changepw.html.twig", [
        ]);
    }

    #[Route('/changepw', name: 'change_password', methods: ['POST'])]
    public function do_changepw(AuthenticationUtils $authenticationUtils, Request $request): ?Response
    {
        $old_password = $request->get("password");
        $new_password = $request->get("new_password");
        $new_password_repeat = $request->get("new_password_repeat");

        $user = $this->getUser();
        $retval = $this->userService->changePassword($user, $old_password, $new_password, $new_password_repeat);
        if($retval === true) {
            return $this->redirect("logout");

        }

        return new Response($retval);

    }

//    #[Route('/register', name: 'register')]
//    public function showRegistration(Request $request): Response
//    {
//        $error = null;
//        $lastUsername = null;
//        return $this->render('register/index.html.twig', [
//            'last_username' => $lastUsername,
//            'error'         => $error,
//        ]);
//    }
//
//    #[Route('/register', name: 'register')]
//    public function performRegistration(Request $request): Response
//    {
//        $error = null;
//        $lastUsername = null;
//        return $this->render('register/index.html.twig', [
//            'last_username' => $lastUsername,
//            'error'         => $error,
//        ]);
//    }

}


