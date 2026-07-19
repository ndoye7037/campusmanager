<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly RateLimiterFactory $connexionUtilisateurLimiter,
    ) {
    }

    #[Route('/api/inscription', name: 'api_inscription', methods: ['POST'])]
    public function inscription(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasheurMotDePasse,
    ): JsonResponse {
        $donnees = json_decode($request->getContent(), true) ?? [];

        $email = $donnees['email'] ?? '';
        $motDePasse = $donnees['motDePasse'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($motDePasse) < 8) {
            return $this->json(['error' => 'Email ou mot de passe invalide.'], 400);
        }

        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($email);
        $utilisateur->setNomComplet($donnees['nomComplet'] ?? null);
        $utilisateur->setMotDePasse($hasheurMotDePasse->hashPassword($utilisateur, $motDePasse));
        $utilisateur->setRoles(['ROLE_USER']);

        $em->persist($utilisateur);
        $em->flush();

        return $this->json(['message' => 'Compte cree avec succes.'], 201);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        UserPasswordHasherInterface $hasheurMotDePasse,
        JWTTokenManagerInterface $gestionnaireJwt,
    ): JsonResponse {
        $limiteur = $this->connexionUtilisateurLimiter->create($request->getClientIp());

        if (!$limiteur->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Trop de tentatives. Reessayez plus tard.'], 429);
        }

        $donnees = json_decode($request->getContent(), true) ?? [];
        $email = $donnees['email'] ?? '';
        $motDePasse = $donnees['motDePasse'] ?? '';

        $utilisateur = $utilisateurRepository->trouverParEmail($email);

        $identifiantsValides = $utilisateur !== null
            && $hasheurMotDePasse->isPasswordValid($utilisateur, $motDePasse);

        if (!$identifiantsValides) {
            return $this->json(['error' => 'Identifiants incorrects.'], 401);
        }

        $token = $gestionnaireJwt->create($utilisateur);

        return $this->json(['token' => $token, 'roles' => $utilisateur->getRoles()]);
    }
}