<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    #[Route('/', name: 'accueil', methods: ['GET'])]
    public function accueil(): Response
    {
        return $this->render('app/accueil.html.twig');
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function admin(): Response
    {
        return $this->render('app/admin.html.twig');
    }
}