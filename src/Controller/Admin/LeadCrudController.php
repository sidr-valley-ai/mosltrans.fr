<?php

namespace App\Controller\Admin;

use App\Entity\Lead;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

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

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'Nom'),
            EmailField::new('email', 'Email'),
            DateTimeField::new('createdAt', 'Date de création'),
            TextareaField::new('message', 'Message'),
        ];
    }


    public function createIndexQueryBuilder(
            \EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto $searchDto,
            \EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto $entityDto,
            \EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection $fields,
            \EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection $filters
    ): \Doctrine\ORM\QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->orderBy('entity.createdAt', 'DESC');
    }
}



