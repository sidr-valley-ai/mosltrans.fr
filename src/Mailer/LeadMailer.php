<?php

namespace App\Mailer;

use App\Entity\EmailHistory;
use App\Entity\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\ExceptionInterface as MailerExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Envoie les e-mails adressés à un lead et trace chaque tentative
 * (réussie ou non) dans son historique d'e-mails.
 */
class LeadMailer
{
    public function __construct(
        // Lazy : un MAILER_DSN invalide n'est détecté qu'au moment de l'envoi,
        // ce qui permet de l'attraper ci-dessous au lieu de faire planter la page.
        #[Autowire(lazy: true)]
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $mailerFrom,
    ) {
    }

    public function sendContactConfirmation(Lead $lead): bool
    {
        return $this->send($lead, 'Nous avons bien reçu votre demande', 'emails/contact_confirmation.html.twig');
    }

    public function sendFollowUp(Lead $lead): bool
    {
        return $this->send($lead, 'Suivi de votre demande', 'emails/lead_follow_up.html.twig');
    }

    private function send(Lead $lead, string $subject, string $template): bool
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'MOSLTRANS'))
            ->to($lead->getEmail())
            ->subject($subject)
            ->htmlTemplate($template)
            ->context([
                'lead' => $lead,
            ]);

        $history = (new EmailHistory())
            ->setSubject($subject)
            ->setSentAt(new \DateTimeImmutable());

        try {
            $this->mailer->send($email);
        } catch (MailerExceptionInterface $e) {
            $this->logger->error('Échec de l\'envoi de l\'e-mail "{subject}" au lead #{leadId} ({email}) : {error}', [
                'subject' => $subject,
                'leadId' => $lead->getId(),
                'email' => $lead->getEmail(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            $history
                ->setSuccess(false)
                ->setError($e->getMessage());
        }

        $lead->addEmailHistory($history);
        $this->em->persist($history);
        $this->em->flush();

        return $history->isSuccess();
    }
}
