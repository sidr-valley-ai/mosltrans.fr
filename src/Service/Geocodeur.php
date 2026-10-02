<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transforme un nom de ville en coordonnées GPS avec le service de géocodage
 * de la Géoplateforme de l'IGN (successeur de l'API Adresse, gratuit et sans clé).
 */
class Geocodeur
{
    private const URL = 'https://data.geopf.fr/geocodage/search';
    private const SCORE_MINIMUM = 0.6;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{latitude: float, longitude: float, libelle: string}|null null si la ville est introuvable ou le service indisponible
     */
    public function localiserVille(string $ville): ?array
    {
        $ville = trim($ville);
        if ('' === $ville) {
            return null;
        }

        try {
            $reponse = $this->httpClient->request('GET', self::URL, [
                'query' => ['q' => $ville, 'type' => 'municipality', 'limit' => 1],
                'timeout' => 5,
            ]);
            $donnees = $reponse->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Géocodage impossible pour « {ville} » : {erreur}', ['ville' => $ville, 'erreur' => $e->getMessage()]);

            return null;
        }

        $resultat = $donnees['features'][0] ?? null;
        if (!isset($resultat['geometry']['coordinates'][0], $resultat['geometry']['coordinates'][1])) {
            return null;
        }

        // en dessous de ce score, la ville trouvée ne correspond probablement pas à la saisie
        if (($resultat['properties']['score'] ?? 0) < self::SCORE_MINIMUM) {
            return null;
        }

        [$longitude, $latitude] = $resultat['geometry']['coordinates'];
        $nom = $resultat['properties']['city'] ?? $resultat['properties']['name'] ?? $ville;
        $departement = $resultat['properties']['depcode'] ?? null;

        return [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'libelle' => $departement ? sprintf('%s (%s)', $nom, $departement) : $nom,
        ];
    }
}
