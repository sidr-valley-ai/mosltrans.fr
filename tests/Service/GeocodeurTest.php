<?php

namespace App\Tests\Service;

use App\Service\Geocodeur;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GeocodeurTest extends TestCase
{
    public function testUneVilleConnueRenvoieSesCoordonneesEtSonDepartement(): void
    {
        $geocodeur = $this->geocodeurAvecReponse([
            'features' => [[
                'geometry' => ['coordinates' => [6.194891, 49.108385]],
                'properties' => ['city' => 'Metz', 'depcode' => '57', 'score' => 0.96],
            ]],
        ]);

        self::assertSame(
            ['latitude' => 49.108385, 'longitude' => 6.194891, 'libelle' => 'Metz (57)'],
            $geocodeur->localiserVille('metz'),
        );
    }

    public function testUnResultatPeuFiableEstIgnore(): void
    {
        $geocodeur = $this->geocodeurAvecReponse([
            'features' => [[
                'geometry' => ['coordinates' => [2.0, 48.0]],
                'properties' => ['city' => 'Ailleurs', 'depcode' => '28', 'score' => 0.3],
            ]],
        ]);

        self::assertNull($geocodeur->localiserVille('Mezt'));
    }

    public function testAucunResultatOuServiceIndisponibleRenvoieNull(): void
    {
        self::assertNull($this->geocodeurAvecReponse(['features' => []])->localiserVille('Nulle-part'));

        $enPanne = new Geocodeur(new MockHttpClient(new MockResponse('', ['http_code' => 503])), new NullLogger());
        self::assertNull($enPanne->localiserVille('Metz'));
    }

    /** @param array<string, mixed> $json */
    private function geocodeurAvecReponse(array $json): Geocodeur
    {
        return new Geocodeur(new MockHttpClient(new MockResponse(json_encode($json))), new NullLogger());
    }
}
