<?php

namespace App\Tests\Controller;

use App\Repository\EmailHistoryRepository;
use App\Repository\LeadRepository;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

class ContactControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    public function testSubmittingContactFormSendsConfirmationEmail(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/contact');

        $leadEmail = sprintf('jean.dupont-%s@example.com', uniqid());
        $form = $crawler->selectButton('Envoyer')->form([
            'lead[name]' => 'Jean Dupont',
            'lead[email]' => $leadEmail,
            'lead[message]' => 'Bonjour, je souhaite un devis pour un déménagement.',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/contact/merci');
        self::assertEmailCount(1);

        $email = self::getMailerMessage();

        self::assertEmailHtmlBodyContains($email, 'Jean Dupont');
        self::assertEmailHtmlBodyContains($email, 'Bonjour, je souhaite un devis pour un déménagement.');
        self::assertEmailAddressContains($email, 'To', $leadEmail);
        self::assertEmailAddressContains($email, 'From', 'no-reply@mosltrans.fr');
        self::assertEmailSubjectContains($email, 'Nous avons bien reçu votre demande');

        $container = static::getContainer();
        /** @var LeadRepository $leadRepository */
        $leadRepository = $container->get(LeadRepository::class);
        $lead = $leadRepository->findOneBy(['email' => $leadEmail]);

        self::assertNotNull($lead);
        self::assertSame('Jean Dupont', $lead->getName());

        $history = $container->get(EmailHistoryRepository::class)->findOneBy(['lead' => $lead->getId()]);
        self::assertNotNull($history);
        self::assertTrue($history->isSuccess());
    }

    public function testContactFormStillSavesLeadWhenConfirmationEmailFails(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        static::getContainer()->set(MailerInterface::class, new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('Connection refused');
            }
        });

        $crawler = $client->request('GET', '/contact');

        $email = sprintf('echec-%s@example.com', uniqid());
        $form = $crawler->selectButton('Envoyer')->form([
            'lead[name]' => 'Client Test',
            'lead[email]' => $email,
            'lead[message]' => 'Bonjour, je souhaite un devis pour un transport.',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/contact/merci');

        /** @var LeadRepository $leadRepository */
        $leadRepository = static::getContainer()->get(LeadRepository::class);
        $lead = $leadRepository->findOneBy(['email' => $email]);

        self::assertNotNull($lead);
        $history = static::getContainer()->get(EmailHistoryRepository::class)->findOneBy(['lead' => $lead->getId()]);
        self::assertNotNull($history);
        self::assertFalse($history->isSuccess());
        self::assertSame('Connection refused', $history->getError());
    }
}
