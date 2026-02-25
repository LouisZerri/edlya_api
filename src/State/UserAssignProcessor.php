<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EtatDesLieux;
use App\Entity\Logement;
use Symfony\Bundle\SecurityBundle\Security;

class UserAssignProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();

        if ($user && $data instanceof Logement && $data->getUser() === null) {
            $data->setUser($user);
            $data->setCreatedAt(new \DateTimeImmutable());
            $data->setUpdatedAt(new \DateTimeImmutable());
        }

        if ($user && $data instanceof EtatDesLieux && $data->getUser() === null) {
            $data->setUser($user);
            $data->setCreatedAt(new \DateTimeImmutable());
            $data->setUpdatedAt(new \DateTimeImmutable());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}