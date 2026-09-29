<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Lead;
use App\Entity\User;
use App\Repository\EmailHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LeadCrudControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    public function testSendEmailButtonSendsFollowUpEmailToLead(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = (new User())
            ->setEmail(sprintf('admin-%s@example.com', uniqid()))
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('not-used');

        $lead = (new Lead())
            ->setName('Marie Martin')
            ->setEmail('marie.martin@example.com')
            ->setMessage('Je souhaite un devis pour un transport.')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setStatus('en_cours')
            ->setFollowUpAt(new \DateTimeImmutable('2026-10-15'));

        $em->persist($admin);
        $em->persist($lead);
        $em->flush();

        $client->loginUser($admin);

        $crawler = $client->request('GET', sprintf('/admin/lead/%d', $lead->getId()));
        self::assertResponseIsSuccessful();

        $client->submit($crawler->selectButton('Envoyer un e-mail')->form());

        self::assertResponseRedirects(sprintf('/admin/lead/%d', $lead->getId()));
        self::assertEmailCount(1);

        $email = self::getMailerMessage();

        self::assertEmailAddressContains($email, 'To', 'marie.martin@example.com');
        self::assertEmailAddressContains($email, 'From', 'no-reply@mosltrans.fr');
        self::assertEmailSubjectContains($email, 'Suivi de votre demande');
        self::assertEmailHtmlBodyContains($email, 'Marie Martin');
        self::assertEmailHtmlBodyContains($email, 'En cours de traitement');
        self::assertEmailHtmlBodyContains($email, '15/10/2026');

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'E-mail envoyé à marie.martin@example.com');

        $history = static::getContainer()->get(EmailHistoryRepository::class)
            ->findOneBy(['lead' => $lead->getId()]);
        self::assertNotNull($history);
        self::assertTrue($history->isSuccess());
        self::assertSame('Suivi de votre demande', $history->getSubject());
    }

    public function testSendEmailFailureIsReportedAndTraced(): void
    {
        // Pointe le Mailer vers un port inatteignable pour provoquer un vrai échec
        // d'envoi. Un double du service MailerInterface ne fonctionne pas ici : il
        // est injecté en lazy dans LeadMailer, et le proxy lazy généré par Symfony
        // exige une instance de la classe concrète Mailer, pas d'un simple double
        // de l'interface.
        $previousDsn = $_ENV['MAILER_DSN'] ?? getenv('MAILER_DSN');
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'smtp://127.0.0.1:1';
        putenv('MAILER_DSN=smtp://127.0.0.1:1');

        try {
            $client = static::createClient();

            /** @var EntityManagerInterface $em */
            $em = static::getContainer()->get(EntityManagerInterface::class);

            $admin = (new User())
                ->setEmail(sprintf('admin-%s@example.com', uniqid()))
                ->setRoles(['ROLE_ADMIN'])
                ->setPassword('not-used');

            $lead = (new Lead())
                ->setName('Paul Durand')
                ->setEmail('paul.durand@example.com')
                ->setMessage('Je souhaite un devis pour un transport.')
                ->setCreatedAt(new \DateTimeImmutable());

            $em->persist($admin);
            $em->persist($lead);
            $em->flush();

            $client->loginUser($admin);

            $crawler = $client->request('GET', sprintf('/admin/lead/%d', $lead->getId()));
            $client->submit($crawler->selectButton('Envoyer un e-mail')->form());

            self::assertResponseRedirects(sprintf('/admin/lead/%d', $lead->getId()));
            $client->followRedirect();
            self::assertSelectorTextContains('body', "L'e-mail à paul.durand@example.com n'a pas pu être envoyé");
            self::assertSelectorTextContains('body', 'Échec');

            $history = static::getContainer()->get(EmailHistoryRepository::class)
                ->findOneBy(['lead' => $lead->getId()]);
            self::assertNotNull($history);
            self::assertFalse($history->isSuccess());
            self::assertStringContainsString('127.0.0.1:1', $history->getError());
        } finally {
            $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = $previousDsn;
            putenv('MAILER_DSN='.$previousDsn);
        }
    }

    public function testSendEmailRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('POST', '/admin/lead/1/send-email');

        self::assertResponseRedirects('/login');
        self::assertEmailCount(0);
    }
}
