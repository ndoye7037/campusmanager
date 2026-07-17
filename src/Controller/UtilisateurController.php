<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UtilisateurController extends AbstractController
{
    // READ (liste) - devrait etre reserve a ROLE_ADMIN, aucun controle ici (VULN-01)
    #[Route('/api/utilisateurs', name: 'api_utilisateurs_liste', methods: ['GET'])]
    public function liste(UtilisateurRepository $repo): JsonResponse
    {
        $utilisateurs = $repo->findAll();

        return $this->json(array_map(fn($u) => [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'nomComplet' => $u->getNomComplet(),
            'roles' => $u->getRoles(),
        ], $utilisateurs));
    }

    // READ (un utilisateur)
    #[Route('/api/utilisateurs/{id}', name: 'api_utilisateurs_get', methods: ['GET'])]
    public function get(int $id, UtilisateurRepository $repo): JsonResponse
    {
        $utilisateur = $repo->find($id);
        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        return $this->json([
            'id' => $utilisateur->getId(),
            'email' => $utilisateur->getEmail(),
            'nomComplet' => $utilisateur->getNomComplet(),
            'roles' => $utilisateur->getRoles(),
        ]);
    }

    // CREATE - un admin cree directement un compte (etudiant ou admin)
    #[Route('/api/utilisateurs', name: 'api_utilisateurs_creer', methods: ['POST'])]
    public function creer(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($data['email'] ?? '');
        $utilisateur->setNomComplet($data['nomComplet'] ?? null);
        $utilisateur->setMotDePasse(md5($data['motDePasse'] ?? ''));
        // VULN-05 : mass assignment - le champ roles envoye par le client est applique tel quel
        $utilisateur->setRoles($data['roles'] ?? ['ROLE_USER']);

        $em->persist($utilisateur);
        $em->flush();

        return $this->json([
            'id' => $utilisateur->getId(),
            'email' => $utilisateur->getEmail(),
            'nomComplet' => $utilisateur->getNomComplet(),
            'roles' => $utilisateur->getRoles(),
        ], 201);
    }

    // UPDATE
    #[Route('/api/utilisateurs/{id}', name: 'api_utilisateurs_modifier', methods: ['PATCH'])]
    public function modifier(int $id, Request $request, UtilisateurRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $utilisateur = $repo->find($id);
        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['nomComplet'])) $utilisateur->setNomComplet($data['nomComplet']);
        if (isset($data['email'])) $utilisateur->setEmail($data['email']);
        // VULN-05 : aucune whitelist, le champ "roles" est applique tel quel
        if (isset($data['roles'])) $utilisateur->setRoles($data['roles']);

        $em->flush();

        return $this->json([
            'id' => $utilisateur->getId(),
            'email' => $utilisateur->getEmail(),
            'nomComplet' => $utilisateur->getNomComplet(),
            'roles' => $utilisateur->getRoles(),
        ]);
    }

    // DELETE
    #[Route('/api/utilisateurs/{id}', name: 'api_utilisateurs_supprimer', methods: ['DELETE'])]
    public function supprimer(int $id, UtilisateurRepository $repo, EntityManagerInterface $em): Response
    {
        $utilisateur = $repo->find($id);
        if (!$utilisateur) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $em->remove($utilisateur);
        $em->flush();

        return new Response(null, 204);
    }
}