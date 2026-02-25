<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\PieceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['piece:read']],
    denormalizationContext: ['groups' => ['piece:write']],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
        new Mutation(name: 'create'),
        new Mutation(name: 'update'),
        new DeleteMutation(name: 'delete'),
    ]
)]
#[ORM\Entity(repositoryClass: PieceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Piece
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['piece:read', 'edl:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pieces')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['piece:read', 'piece:write'])]
    private ?EtatDesLieux $etatDesLieux = null;

    #[ORM\Column(length: 255)]
    #[Groups(['piece:read', 'piece:write', 'edl:read'])]
    #[Assert\NotBlank(message: 'Le nom de la pièce est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    private ?string $nom = null;

    #[ORM\Column]
    #[Groups(['piece:read', 'piece:write', 'edl:read'])]
    #[Assert\NotNull(message: 'L\'ordre est obligatoire')]
    #[Assert\PositiveOrZero(message: 'L\'ordre doit être positif ou nul')]
    private ?int $ordre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['piece:read', 'piece:write', 'edl:read'])]
    private ?string $observations = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['piece:read', 'piece:write', 'edl:read'])]
    private ?array $photos = null;

    #[ORM\Column]
    #[Groups(['piece:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['piece:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Element>
     */
    #[ORM\OneToMany(targetEntity: Element::class, mappedBy: 'piece', orphanRemoval: true)]
    #[Groups(['piece:read', 'edl:read'])]
    private Collection $elements;

    public function __construct()
    {
        $this->elements = new ArrayCollection();
    }

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;

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

    /**
     * @return Collection<int, Element>
     */
    public function getElements(): Collection
    {
        return $this->elements;
    }

    public function addElement(Element $element): static
    {
        if (!$this->elements->contains($element)) {
            $this->elements->add($element);
            $element->setPiece($this);
        }

        return $this;
    }

    public function removeElement(Element $element): static
    {
        if ($this->elements->removeElement($element)) {
            // set the owning side to null (unless already changed)
            if ($element->getPiece() === $this) {
                $element->setPiece(null);
            }
        }

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
