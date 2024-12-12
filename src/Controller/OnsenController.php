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
    #[Route('/', name: 'project_mobile', options: ['expose' => true], methods: ['GET'])]
    public function mobile(Request $request, ProjectRepository $projectRepository): Response
    {
        foreach ([
                     'items',
                     'share',
                        'player',
//                     'gallery',
//                     'credits',
//                     'config',
//                     'login'
                 ] as $route) {
                $template = "mobile/$route.html.twig";
                $params = [
                    'route' => $route,
                    'template' => $template,
                    'debug' => $request->get('debug', false),
                ];
                try {
//                    $templates[$route]  = $this->twig->render($template, $params);
                $templates[$route] = $this->renderView($template, $params);
                } catch (\Exception $e) {
                    dd($route, $template, $e->getMessage(), $e);
                }
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
