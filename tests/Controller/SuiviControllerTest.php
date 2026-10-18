<?php

namespace App\Tests\Controller;

use App\Entity\Envoi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class SuiviControllerTest extends WebTestCase
{
    public function testSuiviRenvoieLesInformationsPubliquesDeLEnvoi(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $envoi = (new Envoi())
            ->setVilleDepart('Metz (57)')
            ->setVilleArrivee('Paris (75)')
            ->setCoordonneesDepart(49.108385, 6.194891)
            ->setCoordonneesArrivee(48.859, 2.347)
            ->setEtape('en_transit')
            ->setNoteInterne('Client : Société Dupont, devis n° 42');
        $em->persist($envoi);
        $em->flush();

        // le client peut saisir le numéro en minuscules et sans tirets
        $saisie = strtolower(str_replace('-', '', $envoi->getReference()));
        $client->request('GET', '/suivi/'.$saisie);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $donnees = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame($envoi->getReference(), $donnees['reference']);
        self::assertSame('Metz (57)', $donnees['depart']['ville']);
        self::assertSame('Paris (75)', $donnees['arrivee']['ville']);
        self::assertEqualsWithDelta(49.108385, $donnees['depart']['latitude'], 0.000001);
        self::assertSame(3, $donnees['etape']['numero']);
        self::assertSame('En transit', $donnees['etape']['libelle']);
        self::assertSame(5, $donnees['etape']['total']);
        self::assertNotEmpty($donnees['misAJourLe']);

        // la note interne (données du client) ne doit jamais sortir de l'administration
        self::assertStringNotContainsString('Dupont', (string) $client->getResponse()->getContent());
        self::assertArrayNotHasKey('noteInterne', $donnees);
    }

    public function testSuiviNumeroInconnuRenvoieUneErreur404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/suivi/MOSL-AAAA-ZZZZ');

        self::assertResponseStatusCodeSame(404);
        $donnees = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Aucun envoi ne correspond à ce numéro de suivi.', $donnees['erreur']);
    }

    public function testSuiviRefuseUnNumeroAvecDesCaracteresInterdits(): void
    {
        $client = static::createClient();

        $client->request('GET', '/suivi/'.rawurlencode('<script>'));

        self::assertResponseStatusCodeSame(404);
    }

    public function testTropDeRecherchesDepuisLaMemeAdresseSontLimitees(): void
    {
        $client = static::createClient();

        // adresse dédiée à ce test, remise à zéro : indépendant des autres tests et des exécutions précédentes
        $ip = '203.0.113.10';
        /** @var RateLimiterFactoryInterface $limiteur */
        $limiteur = static::getContainer()->get('limiter.suivi');
        $limiteur->create($ip)->reset();

        // un usage normal (20 recherches par minute) n'est jamais bloqué
        for ($i = 0; $i < 20; ++$i) {
            $client->request('GET', '/suivi/MOSL-AAAA-ZZZZ', server: ['REMOTE_ADDR' => $ip]);
            self::assertResponseStatusCodeSame(404);
        }

        $client->request('GET', '/suivi/MOSL-AAAA-ZZZZ', server: ['REMOTE_ADDR' => $ip]);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        $donnees = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Trop de recherches en peu de temps. Réessayez dans une minute.', $donnees['erreur']);
    }

    public function testLeNumeroDeSuiviEstGenereAutomatiquementEtUnique(): void
    {
        $premier = new Envoi();
        $second = new Envoi();

        self::assertMatchesRegularExpression('/^MOSL-[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}$/', $premier->getReference());
        self::assertNotSame($premier->getReference(), $second->getReference());
        self::assertSame($premier->getReference(), Envoi::normaliserReference(' '.strtolower($premier->getReference()).' '));
    }
}
