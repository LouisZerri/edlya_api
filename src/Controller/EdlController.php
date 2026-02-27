<?php

namespace App\Controller;

use App\Controller\Trait\AuthorizationTrait;
use App\Entity\Cle;
use App\Entity\Compteur;
use App\Entity\Element;
use App\Entity\EtatDesLieux;
use App\Entity\Piece;
use App\Entity\User;
use App\Repository\EtatDesLieuxRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EdlController extends AbstractController
{
    use AuthorizationTrait;
    #[Route('/api/edl/{id}/copier-depuis/{sourceId}', name: 'api_edl_copier_depuis', methods: ['POST'])]
    public function copierDepuis(
        int $id,
        int $sourceId,
        EntityManagerInterface $em,
        EtatDesLieuxRepository $edlRepo
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edlCible = $edlRepo->findWithFullRelations($id);
        if (!$edlCible) {
            return new JsonResponse(['error' => 'EDL cible non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edlCible, $user)) return $denied;

        $edlSource = $edlRepo->findWithFullRelations($sourceId);
        if (!$edlSource) {
            return new JsonResponse(['error' => 'EDL source non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edlSource, $user)) return $denied;

        // Vérifier que la cible n'a pas déjà de contenu
        if ($edlCible->getPieces()->count() > 0 || $edlCible->getCompteurs()->count() > 0 || $edlCible->getCles()->count() > 0) {
            return new JsonResponse([
                'error' => 'L\'EDL cible contient déjà des données. Impossible de copier.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $nbPieces = 0;
        $nbElements = 0;
        $nbCompteurs = 0;
        $nbCles = 0;

        // Copier les pièces et éléments
        foreach ($edlSource->getPieces() as $sourcePiece) {
            $newPiece = new Piece();
            $newPiece->setEtatDesLieux($edlCible);
            $newPiece->setNom($sourcePiece->getNom());
            $newPiece->setOrdre($sourcePiece->getOrdre());
            $newPiece->setObservations($sourcePiece->getObservations());
            // Photos non copiées
            $em->persist($newPiece);
            $nbPieces++;

            foreach ($sourcePiece->getElements() as $sourceElement) {
                $newElement = new Element();
                $newElement->setPiece($newPiece);
                $newElement->setType($sourceElement->getType());
                $newElement->setNom($sourceElement->getNom());
                $newElement->setEtat($sourceElement->getEtat());
                $newElement->setObservations($sourceElement->getObservations());
                $newElement->setDegradations($sourceElement->getDegradations());
                $newElement->setOrdre($sourceElement->getOrdre());
                // Photos non copiées
                $em->persist($newElement);
                $nbElements++;
            }
        }

        // Copier les compteurs (sans indexValue ni photos)
        foreach ($edlSource->getCompteurs() as $sourceCompteur) {
            $newCompteur = new Compteur();
            $newCompteur->setEtatDesLieux($edlCible);
            $newCompteur->setType($sourceCompteur->getType());
            $newCompteur->setNumero($sourceCompteur->getNumero());
            $newCompteur->setCommentaire($sourceCompteur->getCommentaire());
            // indexValue laissé vide — nouvelle lecture à faire
            // Photos non copiées
            $em->persist($newCompteur);
            $nbCompteurs++;
        }

        // Copier les clés (sans photo)
        foreach ($edlSource->getCles() as $sourceCle) {
            $newCle = new Cle();
            $newCle->setEtatDesLieux($edlCible);
            $newCle->setType($sourceCle->getType());
            $newCle->setNombre($sourceCle->getNombre());
            $newCle->setCommentaire($sourceCle->getCommentaire());
            // Photo non copiée
            $em->persist($newCle);
            $nbCles++;
        }

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => sprintf(
                'Copie effectuée : %d pièces, %d éléments, %d compteurs, %d clés',
                $nbPieces, $nbElements, $nbCompteurs, $nbCles
            ),
            'pieces' => $nbPieces,
            'elements' => $nbElements,
            'compteurs' => $nbCompteurs,
            'cles' => $nbCles,
        ]);
    }
}
