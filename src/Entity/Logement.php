<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\LogementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['logement:read']],
    denormalizationContext: ['groups' => ['logement:write']],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
        new Mutation(name: 'create'),
        new Mutation(name: 'update'),
        new DeleteMutation(name: 'delete'),
    ]
)]
#[ORM\Entity(repositoryClass: LogementRepository::class)]
class Logement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['logement:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'logements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['logement:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Groups(['logement:read', 'logement:write', 'edl:read'])]
    #[Assert\NotBlank(message: 'Le nom du logement est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Groups(['logement:read', 'logement:write', 'edl:read'])]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères')]
    private ?string $adresse = null;

    #[ORM\Column(length: 255)]
    #[Groups(['logement:read', 'logement:write', 'edl:read'])]
    #[Assert\NotBlank(message: 'Le code postal est obligatoire')]
    #[Assert\Length(max: 10, maxMessage: 'Le code postal ne peut pas dépasser {{ limit }} caractères')]
    private ?string $codePostal = null;

    #[ORM\Column(length: 255)]
    #[Groups(['logement:read', 'logement:write', 'edl:read'])]
    #[Assert\NotBlank(message: 'La ville est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères')]
    private ?string $ville = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['logement:read', 'logement:write', 'edl:read'])]
    #[Assert\Length(max: 50, maxMessage: 'Le type ne peut pas dépasser {{ limit }} caractères')]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['logement:read', 'logement:write', 'edl:read'])]
    #[Assert\Positive(message: 'La surface doit être positive')]
    private ?float $surface = null;

    #[ORM\Column]
    #[Groups(['logement:read', 'logement:write', 'edl:read'])]
    #[Assert\NotNull(message: 'Le nombre de pièces est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le nombre de pièces doit être positif ou nul')]
    private ?int $nbPieces = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['logement:read', 'logement:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['logement:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['logement:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, EtatDesLieux>
     */
    #[ORM\OneToMany(targetEntity: EtatDesLieux::class, mappedBy: 'logement', orphanRemoval: true)]
    #[Groups(['logement:read'])]
    private Collection $etatDesLieux;

    public function __construct()
    {
        $this->etatDesLieux = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSurface(): ?float
    {
        return $this->surface;
    }

    public function setSurface(?float $surface): static
    {
        $this->surface = $surface;

        return $this;
    }

    public function getNbPieces(): ?int
    {
        return $this->nbPieces;
    }

    public function setNbPieces(int $nbPieces): static
    {
        $this->nbPieces = $nbPieces;

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
     * @return Collection<int, EtatDesLieux>
     */
    public function getEtatDesLieux(): Collection
    {
        return $this->etatDesLieux;
    }

    public function addEtatDesLieux(EtatDesLieux $etatDesLieux): static
    {
        if (!$this->etatDesLieux->contains($etatDesLieux)) {
            $this->etatDesLieux->add($etatDesLieux);
            $etatDesLieux->setLogement($this);
        }

        return $this;
    }

    public function removeEtatDesLieux(EtatDesLieux $etatDesLieux): static
    {
        if ($this->etatDesLieux->removeElement($etatDesLieux)) {
            // set the owning side to null (unless already changed)
            if ($etatDesLieux->getLogement() === $this) {
                $etatDesLieux->setLogement(null);
            }
        }

        return $this;
    }
}
