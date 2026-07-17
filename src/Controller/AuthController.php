<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/api/inscription', name: 'api_inscription', methods: ['POST'])]
    public function inscription(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($data['email'] ?? '');
        $utilisateur->setNomComplet($data['nomComplet'] ?? null);
        $utilisateur->setMotDePasse(md5($data['motDePasse'] ?? ''));
        $utilisateur->setRoles($data['roles'] ?? ['ROLE_USER']);

        $em->persist($utilisateur);
        $em->flush();

        return $this->json(['message' => 'Utilisateur cree', 'id' => $utilisateur->getId()]);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, UtilisateurRepository $utilisateurRepository, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = $data['email'] ?? '';
        $motDePasse = $data['motDePasse'] ?? '';

        $utilisateur = $utilisateurRepository->findOneBy(['email' => $email]);

        if (!$utilisateur) {
            return $this->json(['error' => 'Aucun compte trouve pour cet email'], 401);
        }

        if (md5($motDePasse) !== $utilisateur->getPassword()) {
            return $this->json(['error' => 'Mot de passe incorrect'], 401);
        }

        $token = $jwtManager->create($utilisateur);

        return $this->json(['token' => $token, 'roles' => $utilisateur->getRoles()]);
    }
}