<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\ElementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['element:read']],
    denormalizationContext: ['groups' => ['element:write']],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
        new Mutation(name: 'create'),
        new Mutation(name: 'update'),
        new DeleteMutation(name: 'delete'),
    ]
)]
#[ORM\Entity(repositoryClass: ElementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Element
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['element:read', 'piece:read', 'edl:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'elements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['element:read', 'element:write'])]
    private ?Piece $piece = null;

    #[ORM\Column(length: 50)]
    #[Groups(['element:read', 'element:write', 'piece:read', 'edl:read'])]
    #[Assert\NotBlank(message: 'Le type d\'élément est obligatoire')]
    #[Assert\Choice(
        choices: ['sol', 'mur', 'plafond', 'menuiserie', 'electricite', 'plomberie', 'chauffage', 'equipement', 'mobilier', 'electromenager', 'autre'],
        message: 'Le type d\'élément n\'est pas valide'
    )]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Groups(['element:read', 'element:write', 'piece:read', 'edl:read'])]
    #[Assert\NotBlank(message: 'Le nom de l\'élément est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    private ?string $nom = null;

    #[ORM\Column(length: 20)]
    #[Groups(['element:read', 'element:write', 'piece:read', 'edl:read'])]
    #[Assert\NotBlank(message: 'L\'état de l\'élément est obligatoire')]
    #[Assert\Choice(
        choices: ['neuf', 'tres_bon', 'bon', 'usage', 'mauvais', 'hors_service'],
        message: 'L\'état doit être "neuf", "tres_bon", "bon", "usage", "mauvais" ou "hors_service"'
    )]
    private ?string $etat = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['element:read', 'element:write', 'piece:read', 'edl:read'])]
    private ?string $observations = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['element:read', 'element:write', 'piece:read', 'edl:read'])]
    private ?array $degradations = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['element:read', 'element:write', 'piece:read', 'edl:read'])]
    private ?int $ordre = null;

    #[ORM\Column]
    #[Groups(['element:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['element:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Photo>
     */
    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'element', orphanRemoval: true)]
    #[Groups(['element:read', 'piece:read', 'edl:read'])]
    private Collection $photos;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPiece(): ?Piece
    {
        return $this->piece;
    }

    public function setPiece(?Piece $piece): static
    {
        $this->piece = $piece;

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;

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

    public function getDegradations(): ?array
    {
        return $this->degradations;
    }

    public function setDegradations(?array $degradations): static
    {
        $this->degradations = $degradations;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
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
     * @return Collection<int, Photo>
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(Photo $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setElement($this);
        }

        return $this;
    }

    public function removePhoto(Photo $photo): static
    {
        if ($this->photos->removeElement($photo)) {
            // set the owning side to null (unless already changed)
            if ($photo->getElement() === $this) {
                $photo->setElement(null);
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
