<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\PartageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    normalizationContext: ['groups' => ['partage:read']],
    denormalizationContext: ['groups' => ['partage:write']],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
        new Mutation(name: 'create'),
        new DeleteMutation(name: 'delete'),
    ]
)]
#[ORM\Entity(repositoryClass: PartageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Partage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['partage:read', 'edl:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'partages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['partage:read', 'partage:write'])]
    private ?EtatDesLieux $etatDesLieux = null;

    #[ORM\Column(length: 64, unique: true)]
    #[Groups(['partage:read'])]
    private ?string $token = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['partage:read', 'partage:write', 'edl:read'])]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $email = null;

    #[ORM\Column(length: 10)]
    #[Groups(['partage:read', 'partage:write', 'edl:read'])]
    #[Assert\Choice(choices: ['email', 'lien'], message: 'Le type doit être "email" ou "lien"')]
    private ?string $type = 'lien';

    #[ORM\Column]
    #[Groups(['partage:read', 'edl:read'])]
    private ?\DateTimeImmutable $expireAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['partage:read'])]
    private ?\DateTimeImmutable $consulteAt = null;

    #[ORM\Column]
    #[Groups(['partage:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['partage:read'])]
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

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

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

    public function getExpireAt(): ?\DateTimeImmutable
    {
        return $this->expireAt;
    }

    public function setExpireAt(\DateTimeImmutable $expireAt): static
    {
        $this->expireAt = $expireAt;

        return $this;
    }

    public function getConsulteAt(): ?\DateTimeImmutable
    {
        return $this->consulteAt;
    }

    public function setConsulteAt(?\DateTimeImmutable $consulteAt): static
    {
        $this->consulteAt = $consulteAt;

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

    public function isExpired(): bool
    {
        return $this->expireAt < new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        // Générer un token unique
        if ($this->token === null) {
            $this->token = bin2hex(random_bytes(32));
        }

        // Expiration par défaut: 7 jours
        if ($this->expireAt === null) {
            $this->expireAt = new \DateTimeImmutable('+7 days');
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
