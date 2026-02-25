<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\EtatDesLieuxRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['edl:read']],
    denormalizationContext: ['groups' => ['edl:write']],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
        new Mutation(name: 'create'),
        new Mutation(name: 'update'),
        new DeleteMutation(name: 'delete'),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'statut' => 'exact'])]
#[ORM\Entity(repositoryClass: EtatDesLieuxRepository::class)]
#[ORM\HasLifecycleCallbacks]
class EtatDesLieux
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['edl:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'etatDesLieux')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['edl:read', 'edl:write'])]
    private ?Logement $logement = null;

    #[ORM\ManyToOne(inversedBy: 'etatDesLieux')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['edl:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 10)]
    #[Groups(['edl:read', 'edl:write'])]
    #[Assert\NotBlank(message: 'Le type d\'état des lieux est obligatoire')]
    #[Assert\Choice(choices: ['entree', 'sortie'], message: 'Le type doit être "entree" ou "sortie"')]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['edl:read', 'edl:write'])]
    #[Assert\NotNull(message: 'La date de réalisation est obligatoire')]
    private ?\DateTime $dateRealisation = null;

    #[ORM\Column(length: 255)]
    #[Groups(['edl:read', 'edl:write'])]
    #[Assert\NotBlank(message: 'Le nom du locataire est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom du locataire ne peut pas dépasser {{ limit }} caractères')]
    private ?string $locataireNom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['edl:read', 'edl:write'])]
    #[Assert\Email(message: 'L\'email du locataire n\'est pas valide')]
    #[Assert\Length(max: 255, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères')]
    private ?string $locataireEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['edl:read', 'edl:write'])]
    #[Assert\Length(max: 20, maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères')]
    private ?string $locataireTelephone = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['edl:read', 'edl:write'])]
    private ?array $autresLocataires = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['edl:read', 'edl:write'])]
    private ?string $observationsGenerales = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['edl:read', 'edl:write'])]
    #[Assert\Choice(choices: ['brouillon', 'en_cours', 'termine', 'signe'], message: 'Le statut doit être "brouillon", "en_cours", "termine" ou "signe"')]
    private ?string $statut = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['edl:read', 'edl:write'])]
    #[Assert\PositiveOrZero(message: 'Le dépôt de garantie doit être positif ou nul')]
    private ?float $depotGarantie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['edl:read', 'edl:write'])]
    private ?string $signatureBailleur = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['edl:read', 'edl:write'])]
    private ?string $signatureLocataire = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['edl:read'])]
    private ?\DateTime $dateSignatureBailleur = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['edl:read'])]
    private ?\DateTime $dateSignatureLocataire = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $signatureIp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $signatureUserAgent = null;

    #[ORM\Column]
    #[Groups(['edl:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['edl:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Piece>
     */
    #[ORM\OneToMany(targetEntity: Piece::class, mappedBy: 'etatDesLieux', orphanRemoval: true)]
    #[Groups(['edl:read'])]
    private Collection $pieces;

    /**
     * @var Collection<int, Compteur>
     */
    #[ORM\OneToMany(targetEntity: Compteur::class, mappedBy: 'etatDesLieux', orphanRemoval: true)]
    #[Groups(['edl:read'])]
    private Collection $compteurs;

    /**
     * @var Collection<int, Cle>
     */
    #[ORM\OneToMany(targetEntity: Cle::class, mappedBy: 'etatDesLieux', orphanRemoval: true)]
    #[Groups(['edl:read'])]
    private Collection $cles;

    /**
     * @var Collection<int, Partage>
     */
    #[ORM\OneToMany(targetEntity: Partage::class, mappedBy: 'etatDesLieux', orphanRemoval: true)]
    #[Groups(['edl:read'])]
    private Collection $partages;

    public function __construct()
    {
        $this->pieces = new ArrayCollection();
        $this->compteurs = new ArrayCollection();
        $this->cles = new ArrayCollection();
        $this->partages = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
        $this->updatedAt ??= new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogement(): ?Logement
    {
        return $this->logement;
    }

    public function setLogement(?Logement $logement): static
    {
        $this->logement = $logement;

        return $this;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDateRealisation(): ?\DateTime
    {
        return $this->dateRealisation;
    }

    public function setDateRealisation(\DateTime $dateRealisation): static
    {
        $this->dateRealisation = $dateRealisation;

        return $this;
    }

    public function getLocataireNom(): ?string
    {
        return $this->locataireNom;
    }

    public function setLocataireNom(string $locataireNom): static
    {
        $this->locataireNom = $locataireNom;

        return $this;
    }

    public function getLocataireEmail(): ?string
    {
        return $this->locataireEmail;
    }

    public function setLocataireEmail(?string $locataireEmail): static
    {
        $this->locataireEmail = $locataireEmail;

        return $this;
    }

    public function getLocataireTelephone(): ?string
    {
        return $this->locataireTelephone;
    }

    public function setLocataireTelephone(?string $locataireTelephone): static
    {
        $this->locataireTelephone = $locataireTelephone;

        return $this;
    }

    public function getAutresLocataires(): ?array
    {
        return $this->autresLocataires;
    }

    public function setAutresLocataires(?array $autresLocataires): static
    {
        $this->autresLocataires = $autresLocataires;

        return $this;
    }

    public function getObservationsGenerales(): ?string
    {
        return $this->observationsGenerales;
    }

    public function setObservationsGenerales(?string $observationsGenerales): static
    {
        $this->observationsGenerales = $observationsGenerales;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDepotGarantie(): ?float
    {
        return $this->depotGarantie;
    }

    public function setDepotGarantie(?float $depotGarantie): static
    {
        $this->depotGarantie = $depotGarantie;

        return $this;
    }

    public function getSignatureBailleur(): ?string
    {
        return $this->signatureBailleur;
    }

    public function setSignatureBailleur(?string $signatureBailleur): static
    {
        $this->signatureBailleur = $signatureBailleur;

        return $this;
    }

    public function getSignatureLocataire(): ?string
    {
        return $this->signatureLocataire;
    }

    public function setSignatureLocataire(?string $signatureLocataire): static
    {
        $this->signatureLocataire = $signatureLocataire;

        return $this;
    }

    public function getDateSignatureBailleur(): ?\DateTime
    {
        return $this->dateSignatureBailleur;
    }

    public function setDateSignatureBailleur(?\DateTime $dateSignatureBailleur): static
    {
        $this->dateSignatureBailleur = $dateSignatureBailleur;

        return $this;
    }

    public function getDateSignatureLocataire(): ?\DateTime
    {
        return $this->dateSignatureLocataire;
    }

    public function setDateSignatureLocataire(?\DateTime $dateSignatureLocataire): static
    {
        $this->dateSignatureLocataire = $dateSignatureLocataire;

        return $this;
    }

    public function getSignatureIp(): ?string
    {
        return $this->signatureIp;
    }

    public function setSignatureIp(?string $signatureIp): static
    {
        $this->signatureIp = $signatureIp;

        return $this;
    }

    public function getSignatureUserAgent(): ?string
    {
        return $this->signatureUserAgent;
    }

    public function setSignatureUserAgent(?string $signatureUserAgent): static
    {
        $this->signatureUserAgent = $signatureUserAgent;

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
     * @return Collection<int, Piece>
     */
    public function getPieces(): Collection
    {
        return $this->pieces;
    }

    public function addPiece(Piece $piece): static
    {
        if (!$this->pieces->contains($piece)) {
            $this->pieces->add($piece);
            $piece->setEtatDesLieux($this);
        }

        return $this;
    }

    public function removePiece(Piece $piece): static
    {
        if ($this->pieces->removeElement($piece)) {
            // set the owning side to null (unless already changed)
            if ($piece->getEtatDesLieux() === $this) {
                $piece->setEtatDesLieux(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Compteur>
     */
    public function getCompteurs(): Collection
    {
        return $this->compteurs;
    }

    public function addCompteur(Compteur $compteur): static
    {
        if (!$this->compteurs->contains($compteur)) {
            $this->compteurs->add($compteur);
            $compteur->setEtatDesLieux($this);
        }

        return $this;
    }

    public function removeCompteur(Compteur $compteur): static
    {
        if ($this->compteurs->removeElement($compteur)) {
            // set the owning side to null (unless already changed)
            if ($compteur->getEtatDesLieux() === $this) {
                $compteur->setEtatDesLieux(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Cle>
     */
    public function getCles(): Collection
    {
        return $this->cles;
    }

    public function addCle(Cle $cle): static
    {
        if (!$this->cles->contains($cle)) {
            $this->cles->add($cle);
            $cle->setEtatDesLieux($this);
        }

        return $this;
    }

    public function removeCle(Cle $cle): static
    {
        if ($this->cles->removeElement($cle)) {
            // set the owning side to null (unless already changed)
            if ($cle->getEtatDesLieux() === $this) {
                $cle->setEtatDesLieux(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Partage>
     */
    public function getPartages(): Collection
    {
        return $this->partages;
    }

    public function addPartage(Partage $partage): static
    {
        if (!$this->partages->contains($partage)) {
            $this->partages->add($partage);
            $partage->setEtatDesLieux($this);
        }

        return $this;
    }

    public function removePartage(Partage $partage): static
    {
        if ($this->partages->removeElement($partage)) {
            // set the owning side to null (unless already changed)
            if ($partage->getEtatDesLieux() === $this) {
                $partage->setEtatDesLieux(null);
            }
        }

        return $this;
    }
}
