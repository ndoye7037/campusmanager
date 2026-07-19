<?php

namespace App\Entity;

use App\Repository\CoursRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoursRepository::class)]
class Cours
{
    public const STATUTS_AUTORISES = ['disponible', 'complet', 'annule'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nomEnseignant = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'disponible';

    #[ORM\ManyToOne(inversedBy: 'cours')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $proprietaire = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getNomEnseignant(): ?string
    {
        return $this->nomEnseignant;
    }

    public function setNomEnseignant(?string $nomEnseignant): static
    {
        $this->nomEnseignant = $nomEnseignant;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (in_array($statut, self::STATUTS_AUTORISES, true)) {
            $this->statut = $statut;
        }
        return $this;
    }

    public function getProprietaire(): ?Utilisateur
    {
        return $this->proprietaire;
    }

    public function setProprietaire(Utilisateur $proprietaire): static
    {
        $this->proprietaire = $proprietaire;
        return $this;
    }

    public function appartientA(Utilisateur $utilisateur): bool
    {
        return $this->proprietaire?->getId() === $utilisateur->getId();
    }

    public function versTableau(): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'description' => $this->description,
            'nomEnseignant' => $this->nomEnseignant,
            'statut' => $this->statut,
            'proprietaireId' => $this->proprietaire?->getId(),
        ];
    }
}