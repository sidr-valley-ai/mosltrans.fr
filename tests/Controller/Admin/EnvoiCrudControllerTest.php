<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Envoi;
use App\Entity\User;
use App\Repository\EnvoiRepository;
use App\Service\Geocodeur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EnvoiCrudControllerTest extends WebTestCase
{
    public function testLaPageEnvoisEstReserveeAuxAdministrateurs(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/envoi');

        self::assertResponseRedirects('/login');
    }

    public function testCreerUnEnvoiGenereLeNumeroEtPlaceLesVilles(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // pas d'appel réseau pendant les tests : le géocodage est simulé
        $geocodeur = $this->createMock(Geocodeur::class);
        $geocodeur->method('localiserVille')->willReturnMap([
            ['metz', ['latitude' => 49.108385, 'longitude' => 6.194891, 'libelle' => 'Metz (57)']],
            ['lyon', ['latitude' => 45.758, 'longitude' => 4.835, 'libelle' => 'Lyon (69)']],
        ]);
        static::getContainer()->set(Geocodeur::class, $geocodeur);

        $client->loginUser($this->creerAdmin());

        $crawler = $client->request('GET', '/admin/envoi/new');
        self::assertResponseIsSuccessful();

        $noteUnique = sprintf('Test %s', uniqid());
        $form = $crawler->filter('form[name="Envoi"]')->form([
            'Envoi[villeDepart]' => 'metz',
            'Envoi[villeArrivee]' => 'lyon',
            'Envoi[etape]' => 'chargement',
            'Envoi[noteInterne]' => $noteUnique,
        ]);
        $client->submit($form);

        self::assertResponseRedirects();

        /** @var Envoi|null $envoi */
        $envoi = static::getContainer()->get(EnvoiRepository::class)->findOneBy(['noteInterne' => $noteUnique]);

        self::assertNotNull($envoi);
        self::assertMatchesRegularExpression('/^MOSL-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $envoi->getReference());
        self::assertSame('Metz (57)', $envoi->getVilleDepart());
        self::assertSame('Lyon (69)', $envoi->getVilleArrivee());
        self::assertEqualsWithDelta(45.758, $envoi->getLatitudeArrivee(), 0.000001);
        self::assertSame('chargement', $envoi->getEtape());
        self::assertNotNull($envoi->getUpdatedAt());
    }

    public function testChangerLEtapeMetAJourLeSuiviDuClient(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $geocodeur = $this->createMock(Geocodeur::class);
        $geocodeur->method('localiserVille')->willReturnCallback(
            fn (string $ville) => ['latitude' => 48.0, 'longitude' => 6.0, 'libelle' => $ville],
        );
        static::getContainer()->set(Geocodeur::class, $geocodeur);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $envoi = (new Envoi())->setVilleDepart('Metz (57)')->setVilleArrivee('Paris (75)')->setEtape('en_transit');
        $em->persist($envoi);
        $em->flush();
        $miseAJourInitiale = $envoi->getUpdatedAt();

        $client->loginUser($this->creerAdmin());
        sleep(1);   // la date de mise à jour est à la seconde près

        $crawler = $client->request('GET', sprintf('/admin/envoi/%d/edit', $envoi->getId()));
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form[name="Envoi"]')->form(['Envoi[etape]' => 'livree']));
        self::assertResponseRedirects();

        $client->request('GET', '/suivi/'.$envoi->getReference());
        $donnees = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(5, $donnees['etape']['numero']);
        self::assertSame('Livrée', $donnees['etape']['libelle']);
        self::assertGreaterThan($miseAJourInitiale, new \DateTimeImmutable($donnees['misAJourLe']));
    }

    private function creerAdmin(): User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = (new User())
            ->setEmail(sprintf('admin-%s@example.com', uniqid()))
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('not-used');
        $em->persist($admin);
        $em->flush();

        return $admin;
    }
}
