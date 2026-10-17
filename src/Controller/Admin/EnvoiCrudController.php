<?php

namespace App\Controller\Admin;

use App\Entity\Envoi;
use App\Service\Geocodeur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class EnvoiCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Geocodeur $geocodeur,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Envoi::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Envoi')
            ->setEntityLabelInPlural('Envois')
            ->setSearchFields(['reference', 'villeDepart', 'villeArrivee', 'noteInterne'])
            ->setHelp(Crud::PAGE_NEW, 'Le numéro de suivi est créé automatiquement à l\'enregistrement. Communiquez-le au client pour qu\'il suive sa livraison sur la page d\'accueil.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        $etapes = array_flip(Envoi::ETAPES);

        return [
            TextField::new('reference', 'Numéro de suivi')->hideOnForm(),
            TextField::new('villeDepart', 'Ville de départ')
                ->setHelp('Exemple : Metz. La ville est placée sur la carte automatiquement.'),
            TextField::new('villeArrivee', 'Ville d\'arrivée'),
            ChoiceField::new('etape', 'Étape')
                ->setChoices($etapes)
                ->renderAsBadges([
                    'commande_recue' => 'secondary',
                    'chargement' => 'info',
                    'en_transit' => 'primary',
                    'arrivee' => 'warning',
                    'livree' => 'success',
                ]),
            TextareaField::new('noteInterne', 'Note interne')
                ->setHelp('Visible uniquement dans l\'administration (nom du client, numéro de devis…). Jamais affichée sur le site.')
                ->hideOnIndex(),
            DateTimeField::new('updatedAt', 'Dernière mise à jour')->hideOnForm(),
            DateTimeField::new('createdAt', 'Créé le')->onlyOnDetail(),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->localiser($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $avant = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        $this->localiser($entityInstance, $avant['villeDepart'] ?? null, $avant['villeArrivee'] ?? null);
        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Place les deux villes sur la carte et harmonise leur nom, par exemple « metz » devient « Metz (57) ».
     * Une ville inchangée et déjà placée n'est pas relocalisée : changer l'étape n'appelle pas l'IGN.
     */
    private function localiser(Envoi $envoi, ?string $ancienDepart = null, ?string $ancienneArrivee = null): void
    {
        $departTrouve = true;
        $arriveeTrouvee = true;

        if ($envoi->getVilleDepart() !== $ancienDepart || null === $envoi->getLatitudeDepart()) {
            $depart = $this->geocodeur->localiserVille((string) $envoi->getVilleDepart());
            if (null !== $depart) {
                $envoi->setVilleDepart($depart['libelle'])->setCoordonneesDepart($depart['latitude'], $depart['longitude']);
            } else {
                $envoi->setCoordonneesDepart(null, null);
                $departTrouve = false;
            }
        }

        if ($envoi->getVilleArrivee() !== $ancienneArrivee || null === $envoi->getLatitudeArrivee()) {
            $arrivee = $this->geocodeur->localiserVille((string) $envoi->getVilleArrivee());
            if (null !== $arrivee) {
                $envoi->setVilleArrivee($arrivee['libelle'])->setCoordonneesArrivee($arrivee['latitude'], $arrivee['longitude']);
            } else {
                $envoi->setCoordonneesArrivee(null, null);
                $arriveeTrouvee = false;
            }
        }

        if (!$departTrouve || !$arriveeTrouvee) {
            $this->addFlash('warning', 'Une des villes n\'a pas été trouvée : l\'envoi est enregistré, mais la carte ne pourra pas afficher le trajet. Vérifiez l\'orthographe de la ville.');
        }
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->orderBy('entity.updatedAt', 'DESC');
    }
}
