<?php

namespace App\Controller\Admin;

use App\Entity\Lead;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class LeadCrudController extends AbstractCrudController
{
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
        return $actions->disable(Action::NEW)->add(Crud::PAGE_INDEX, Action::DETAIL);
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



