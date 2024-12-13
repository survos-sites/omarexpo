<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class MobileController extends AbstractController
{

    public function __construct(
        private Environment $twig
    )
    {
    }

    #[Route('/offline', name: 'app_offline', priority: 1)]
    public function offline(): Response {
        return $this->render('app/offline.html.twig');
    }

    #[Route('/home', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('app/home.html.twig', [
        ]);
    }
    #[Route('/share', name: 'app_share')]
    public function share(): Response
    {
        return $this->render('app/share.html.twig', [
        ]);
    }

    #[Route('/pokemon', name: 'app_pokemon')]
    public function pokemon(): Response
    {
        return $this->render('app/pokemon.html.twig', [
        ]);
    }

    #[Route('/saved', name: 'app_saved')]
    public function saved(): Response
    {
        return $this->render('app/saved.html.twig', [
        ]);
    }
    #[Route('/detail', name: 'app_detail')]
    #[Template('app/detail.html.twig')]
    public function detail(): array
    {
        return [];
    }

    #[Route('/about', name: 'app_about')]
    #[Template('app/about.html.twig')]
    public function about(): array
    {
        return [];
    }

    #[Route('/layout', name: 'app_layout')]
    #[Template('@SurvosMobile/initial-layout.html.twig')]
    public function layout(): array
    {
        return [];
    }

    #[Route('/{type}/{pageCode}', name: 'app_page')]
    public function page(Request $request, string $type, string $pageCode): Response
    {
        return $this->render("mobile/{$pageCode}.html.twig",
            array_merge([
                'type' => $type,
                $request->query->all()
            ])
        );
    }

}
