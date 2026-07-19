<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UtilisateurController extends AbstractController
{
    #[Route('/api/utilisateurs', name: 'api_utilisateurs_liste', methods: ['GET'])]
    public function liste(UtilisateurRepository $repo): JsonResponse
    {
        $utilisateurs = $repo->findAll();

        return $this->json(array_map(fn(Utilisateur $u) => $this->versTableau($u), $utilisateurs));
    }

    #[Route('/api/utilisateurs/{id}', name: 'api_utilisateurs_get', methods: ['GET'])]
    public function get(int $id, UtilisateurRepository $repo): JsonResponse
    {
        $utilisateur = $repo->find($id);
        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        return $this->json($this->versTableau($utilisateur));
    }

    #[Route('/api/utilisateurs', name: 'api_utilisateurs_creer', methods: ['POST'])]
    public function creer(
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
        $utilisateur->setRoles($donnees['roles'] ?? ['ROLE_USER']);

        $em->persist($utilisateur);
        $em->flush();

        return $this->json($this->versTableau($utilisateur), 201);
    }

    #[Route('/api/utilisateurs/{id}', name: 'api_utilisateurs_modifier', methods: ['PATCH'])]
    public function modifier(int $id, Request $request, UtilisateurRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $utilisateur = $repo->find($id);
        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $donnees = json_decode($request->getContent(), true) ?? [];

        if (isset($donnees['nomComplet'])) {
            $utilisateur->setNomComplet($donnees['nomComplet']);
        }
        if (isset($donnees['roles'])) {
            $utilisateur->setRoles($donnees['roles']);
        }

        $em->flush();

        return $this->json($this->versTableau($utilisateur));
    }

    #[Route('/api/utilisateurs/{id}', name: 'api_utilisateurs_supprimer', methods: ['DELETE'])]
    public function supprimer(int $id, UtilisateurRepository $repo, EntityManagerInterface $em): Response
    {
        $utilisateur = $repo->find($id);
        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        if ($utilisateur->getId() === $this->getUser()->getId()) {
            return $this->json(['error' => 'Impossible de supprimer son propre compte.'], 400);
        }

        $em->remove($utilisateur);
        $em->flush();

        return new Response(null, 204);
    }

    private function versTableau(Utilisateur $utilisateur): array
    {
        return [
            'id' => $utilisateur->getId(),
            'email' => $utilisateur->getEmail(),
            'nomComplet' => $utilisateur->getNomComplet(),
            'roles' => $utilisateur->getRoles(),
        ];
    }
}