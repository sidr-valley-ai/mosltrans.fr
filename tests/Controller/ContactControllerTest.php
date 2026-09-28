<?php

namespace App\Tests\Controller;

use App\Repository\LeadRepository;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ContactControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    public function testSubmittingContactFormSendsConfirmationEmail(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/contact');

        $form = $crawler->selectButton('Envoyer')->form([
            'lead[name]' => 'Jean Dupont',
            'lead[email]' => 'jean.dupont@example.com',
            'lead[message]' => 'Bonjour, je souhaite un devis pour un déménagement.',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/contact/merci');
        self::assertEmailCount(1);

        $email = self::getMailerMessage();

        self::assertEmailHtmlBodyContains($email, 'Jean Dupont');
        self::assertEmailHtmlBodyContains($email, 'Bonjour, je souhaite un devis pour un déménagement.');
        self::assertEmailAddressContains($email, 'To', 'jean.dupont@example.com');
        self::assertEmailAddressContains($email, 'From', 'no-reply@mosltrans.fr');
        self::assertEmailSubjectContains($email, 'Nous avons bien reçu votre demande');

        $container = static::getContainer();
        /** @var LeadRepository $leadRepository */
        $leadRepository = $container->get(LeadRepository::class);
        $lead = $leadRepository->findOneBy(['email' => 'jean.dupont@example.com']);

        self::assertNotNull($lead);
        self::assertSame('Jean Dupont', $lead->getName());
    }
}
