<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Lead;
use App\Entity\User;
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
    }

    public function testSendEmailRequiresAdmin(): void
    {
        $client = static::createClient();
        $client->request('POST', '/admin/lead/1/send-email');

        self::assertResponseRedirects('/login');
        self::assertEmailCount(0);
    }
}
