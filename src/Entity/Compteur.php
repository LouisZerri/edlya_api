<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\CompteurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['compteur:read']],
    denormalizationContext: ['groups' => ['compteur:write']],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
        new Mutation(name: 'create'),
        new Mutation(name: 'update'),
        new DeleteMutation(name: 'delete'),
    ]
)]
#[ORM\Entity(repositoryClass: CompteurRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Compteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['compteur:read', 'edl:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'compteurs')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['compteur:read', 'compteur:write'])]
    private ?EtatDesLieux $etatDesLieux = null;

    #[ORM\Column(length: 20)]
    #[Groups(['compteur:read', 'compteur:write', 'edl:read'])]
    #[Assert\NotBlank(message: 'Le type de compteur est obligatoire')]
    #[Assert\Choice(
        choices: ['electricite', 'eau_froide', 'eau_chaude', 'gaz'],
        message: 'Le type doit être "electricite", "eau_froide", "eau_chaude" ou "gaz"'
    )]
    private ?string $type = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['compteur:read', 'compteur:write', 'edl:read'])]
    #[Assert\Length(max: 50, maxMessage: 'Le numéro ne peut pas dépasser {{ limit }} caractères')]
    private ?string $numero = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['compteur:read', 'compteur:write', 'edl:read'])]
    #[Assert\Length(max: 50, maxMessage: 'L\'index ne peut pas dépasser {{ limit }} caractères')]
    private ?string $indexValue = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['compteur:read', 'compteur:write', 'edl:read'])]
    private ?string $commentaire = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['compteur:read', 'compteur:write', 'edl:read'])]
    private ?array $photos = null;

    #[ORM\Column]
    #[Groups(['compteur:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['compteur:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEtatDesLieux(): ?EtatDesLieux
    {
        return $this->etatDesLieux;
    }

    public function setEtatDesLieux(?EtatDesLieux $etatDesLieux): static
    {
        $this->etatDesLieux = $etatDesLieux;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getIndexValue(): ?string
    {
        return $this->indexValue;
    }

    public function setIndexValue(?string $indexValue): static
    {
        $this->indexValue = $indexValue;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getPhotos(): ?array
    {
        return $this->photos;
    }

    public function setPhotos(?array $photos): static
    {
        $this->photos = $photos;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
