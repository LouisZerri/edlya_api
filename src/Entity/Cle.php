<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\CleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['cle:read']],
    denormalizationContext: ['groups' => ['cle:write']],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
        new Mutation(name: 'create'),
        new Mutation(name: 'update'),
        new DeleteMutation(name: 'delete'),
    ]
)]
#[ORM\Entity(repositoryClass: CleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Cle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cle:read', 'edl:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cle:read', 'cle:write'])]
    private ?EtatDesLieux $etatDesLieux = null;

    #[ORM\Column(length: 50)]
    #[Groups(['cle:read', 'cle:write', 'edl:read'])]
    #[Assert\NotBlank(message: 'Le type de clé est obligatoire')]
    #[Assert\Choice(
        choices: ['porte_entree', 'parties_communes', 'boite_lettres', 'cave', 'garage', 'parking', 'local_velo', 'portail', 'interphone', 'badge', 'telecommande', 'vigik', 'digicode', 'autre'],
        message: 'Le type de clé n\'est pas valide'
    )]
    private ?string $type = null;

    #[ORM\Column]
    #[Groups(['cle:read', 'cle:write', 'edl:read'])]
    #[Assert\NotNull(message: 'Le nombre de clés est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le nombre de clés doit être positif ou nul')]
    private ?int $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['cle:read', 'cle:write', 'edl:read'])]
    private ?string $commentaire = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['cle:read', 'cle:write', 'edl:read'])]
    private ?string $photo = null;

    #[ORM\Column]
    #[Groups(['cle:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['cle:read'])]
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

    public function getNombre(): ?int
    {
        return $this->nombre;
    }

    public function setNombre(int $nombre): static
    {
        $this->nombre = $nombre;

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

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

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
