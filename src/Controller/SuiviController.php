<?php

namespace App\Controller;

use App\Entity\Envoi;
use App\Repository\EnvoiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SuiviController extends AbstractController
{
    /**
     * API publique de suivi, appelée par la page d'accueil.
     * Ne renvoie que des informations non personnelles : numéro, villes, étape et date.
     */
    #[Route('/suivi/{reference}', name: 'app_suivi', methods: ['GET'], requirements: ['reference' => '[A-Za-z0-9 -]{1,40}'])]
    public function suivi(string $reference, EnvoiRepository $envoiRepository): JsonResponse
    {
        $envoi = $envoiRepository->findOneByReference($reference);

        if (null === $envoi) {
            return $this->reponse(['erreur' => 'Aucun envoi ne correspond à ce numéro de suivi.'], Response::HTTP_NOT_FOUND);
        }

        return $this->reponse([
            'reference' => $envoi->getReference(),
            'depart' => [
                'ville' => $envoi->getVilleDepart(),
                'latitude' => $envoi->getLatitudeDepart(),
                'longitude' => $envoi->getLongitudeDepart(),
            ],
            'arrivee' => [
                'ville' => $envoi->getVilleArrivee(),
                'latitude' => $envoi->getLatitudeArrivee(),
                'longitude' => $envoi->getLongitudeArrivee(),
            ],
            'etape' => [
                'numero' => $envoi->getNumeroEtape(),
                'code' => $envoi->getEtape(),
                'libelle' => $envoi->getLibelleEtape(),
                'total' => count(Envoi::ETAPES),
            ],
            'misAJourLe' => $envoi->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /** @param array<string, mixed> $donnees */
    private function reponse(array $donnees, int $statut = Response::HTTP_OK): JsonResponse
    {
        $reponse = $this->json($donnees, $statut);
        // l'étape change : le navigateur ne doit pas garder une ancienne réponse en cache
        $reponse->headers->set('Cache-Control', 'no-store');
        $reponse->headers->set('X-Robots-Tag', 'noindex');

        return $reponse;
    }
}
