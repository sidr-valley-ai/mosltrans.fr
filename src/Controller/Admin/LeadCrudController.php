<?php

namespace App\Controller\Admin;

use App\Entity\Lead;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class LeadCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $mailerFrom,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Lead::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Demande de contact')
            ->setEntityLabelInPlural('Demandes de contact');
    }

    public function configureActions(Actions $actions): Actions
    {
        $sendEmail = Action::new('sendEmail', 'Envoyer un e-mail', 'fa fa-envelope')
            ->linkToCrudAction('sendEmail')
            ->renderAsForm();

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $sendEmail);
    }

    #[AdminRoute(path: '/{entityId}/send-email', name: 'send_email', options: ['methods' => ['POST']])]
    public function sendEmail(AdminContext $context): RedirectResponse
    {
        /** @var Lead $lead */
        $lead = $context->getEntity()->getInstance();

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'MOSLTRANS'))
            ->to($lead->getEmail())
            ->subject('Suivi de votre demande')
            ->htmlTemplate('emails/lead_follow_up.html.twig')
            ->context([
                'lead' => $lead,
            ]);

        $this->mailer->send($email);

        $this->addFlash('success', sprintf('E-mail envoyé à %s.', $lead->getEmail()));

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($lead->getId())
            ->generateUrl());
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addCssFile('styles/lead.css');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'Nom'),
            EmailField::new('email', 'Email'),
            DateTimeField::new('createdAt', 'Date de création')->hideOnForm(),
            DateTimeField::new('followUpAt', 'Date de relance'),
            TextareaField::new('message', 'Message'),
            ChoiceField::new('status', 'Statut')
                ->setChoices([
                    'Nouveau' => 'nouveau',
                    'En cours' => 'en_cours',
                    'Traité' => 'traite',
                    'Clos' => 'clos',
                ])
                ->renderAsBadges([
                    'nouveau' => 'primary',   // bleu
                    'en_cours' => 'warning',  // orange
                    'traite' => 'success',    // vert
                    'clos' => 'secondary',    // gris
                ]),
            Field::new('statusHistory', 'Historique des statuts')
                ->onlyOnDetail()
                ->setTemplatePath('admin/field/status_history.html.twig'),
        ];
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->orderBy('entity.createdAt', 'DESC');
    }
}



