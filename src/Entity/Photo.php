<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\PhotoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['photo:read']],
    denormalizationContext: ['groups' => ['photo:write']],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
        new Mutation(name: 'create'),
        new Mutation(name: 'update'),
        new DeleteMutation(name: 'delete'),
    ]
)]
#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['photo:read', 'element:read', 'piece:read', 'edl:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['photo:read', 'photo:write'])]
    private ?Element $element = null;

    #[ORM\Column(length: 255)]
    #[Groups(['photo:read', 'photo:write', 'element:read', 'piece:read', 'edl:read'])]
    #[Assert\NotBlank(message: 'Le chemin de la photo est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'Le chemin ne peut pas dépasser {{ limit }} caractères')]
    private ?string $chemin = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['photo:read', 'photo:write', 'element:read', 'piece:read', 'edl:read'])]
    #[Assert\Length(max: 255, maxMessage: 'La légende ne peut pas dépasser {{ limit }} caractères')]
    private ?string $legende = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['photo:read', 'photo:write'])]
    #[Assert\Range(min: -90, max: 90, notInRangeMessage: 'La latitude doit être comprise entre {{ min }} et {{ max }}')]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['photo:read', 'photo:write'])]
    #[Assert\Range(min: -180, max: 180, notInRangeMessage: 'La longitude doit être comprise entre {{ min }} et {{ max }}')]
    private ?float $longitude = null;

    #[ORM\Column]
    #[Groups(['photo:read', 'photo:write', 'element:read', 'piece:read', 'edl:read'])]
    #[Assert\NotNull(message: 'L\'ordre est obligatoire')]
    #[Assert\PositiveOrZero(message: 'L\'ordre doit être positif ou nul')]
    private ?int $ordre = null;

    #[ORM\Column]
    #[Groups(['photo:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getElement(): ?Element
    {
        return $this->element;
    }

    public function setElement(?Element $element): static
    {
        $this->element = $element;

        return $this;
    }

    public function getChemin(): ?string
    {
        return $this->chemin;
    }

    public function setChemin(string $chemin): static
    {
        $this->chemin = $chemin;

        return $this;
    }

    public function getLegende(): ?string
    {
        return $this->legende;
    }

    public function setLegende(?string $legende): static
    {
        $this->legende = $legende;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
