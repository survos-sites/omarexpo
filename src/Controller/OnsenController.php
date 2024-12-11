<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OnsenController extends AbstractController
{
    #[Route('/{_locale}/mobile/start', name: 'project_mobile', options: ['expose' => true], methods: ['GET'])]
    public function mobile(Request $request, ProjectRepository $projectRepository): Response
    {
        foreach ([
                     'tab/items',
                     'tab/share',
            'player',
                     'tours','gallery','blank',
                     'credits', 'config','login'] as $route) {
            $templates[$route] =
                $this->renderView("mobile/$route.html.twig", [
                'debug' => $request->get('debug', false),
                'projects' => $projectRepository->findAll(),
            ]);
        }

//        return $this->render('mobile/index.html.twig', [
        return $this->render('start.html.twig', [
            'templates' => $templates,
            'playNow' => $request->get('playNow', true),
        ]);
    }

    // do we need the project here? Or is it all from the database?  I guess from dexie!
    #[Route(path: '/mobile/{page}', name: 'project_page', methods: ['GET'])]
    public function page(Request $request, string $page): Response
    {
        return $this->render("mobile/{$page}.html.twig");
    }
}
