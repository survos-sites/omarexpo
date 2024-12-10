<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Project;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingController extends AbstractController
{
    #[Route('/landing', name: 'app_landing', priority: 500)]
    public function landing(Request $request): Response
    {
        return $this->redirectToRoute('app_homepage', ['_locale' => $request->getLocale()]);
    }

    #[Route('/p/{projectId}', name: 'project_redirect', priority: 500)]
    public function projectRedirect(Project $project, Request $request): Response
    {
        return $this->redirectToRoute('project_mobile', $project->getrp($request->query->all()));
    }

    #[Route('/i/{projectId}/{itemId}', name: 'item_redirect', priority: 500)]
    public function itemRedirect(Project $project, Item $item, Request $request): Response
    {
        return $this->redirectToRoute('project_mobile', $item->getrp($request->query->all()));
    }

}


