<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DevisController extends AbstractController
{
    /**
     * Page de demande de devis.
     *
     * Le formulaire est pour l'instant une maquette fonctionnelle côté navigateur.
     * Pour l'enregistrer en base, créer une entité Devis puis un DevisType sur le
     * même modèle que Lead et LeadType, et traiter la soumission ici.
     */
    #[Route('/devis', name: 'app_devis')]
    public function index(): Response
    {
        return $this->render('devis/index.html.twig');
    }
}
