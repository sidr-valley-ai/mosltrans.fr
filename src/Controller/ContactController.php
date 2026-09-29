<?php

namespace App\Controller;

use App\Entity\Lead;
use App\Form\LeadType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $mailerFrom,
    ) {
    }

    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LeadType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lead = $form->getData();

            $lead->setCreatedAt(new \DateTimeImmutable());

            $em->persist($lead);
            $em->flush();

            $this->sendConfirmationEmail($lead);

            return $this->redirectToRoute('app_contact_success');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
        ]);
    }

    private function sendConfirmationEmail(Lead $lead): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'MOSLTRANS'))
            ->to($lead->getEmail())
            ->subject('Nous avons bien reçu votre demande')
            ->htmlTemplate('emails/contact_confirmation.html.twig')
            ->context([
                'lead' => $lead,
            ]);

        $this->mailer->send($email);
    }

    #[Route('/contact/merci', name: 'app_contact_success')]
    public function success(): Response
    {
        return $this->render('contact/success.html.twig');
    }
}