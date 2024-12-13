<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Knp\Menu\FactoryInterface;
use Knp\Menu\Twig\Helper;
use Survos\MobileBundle\Event\KnpMenuEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Attribute\Route;

class OnsenController extends AbstractController
{

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        protected FactoryInterface $factory,
    )
    {
    }

    #[Route('/', name: 'project_mobile', options: ['expose' => true], methods: ['GET'])]
    public function mobile(Request $request, ProjectRepository $projectRepository): Response
    {

        $menu = $this->factory->createItem($options['name'] ?? KnpMenuEvent::class);
        foreach ([KnpMenuEvent::MOBILE_TAB_MENU  => 'tab', KnpMenuEvent::MOBILE_PAGE_MENU => 'page'] as $eventName=>$type) {
            $options = [];
            $options = (new OptionsResolver())
                ->setDefaults([

                ])
                ->resolve($options);
            $this->eventDispatcher->dispatch(new KnpMenuEvent($menu, $this->factory, $options), $eventName);
            foreach ($menu->getChildren() as $route=>$child) {
                $template = "mobile/$route.html.twig";
                $params = [
                    'type' => $type,
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
        }

        // finally, add the pages that aren't associated with a menu
        foreach (['player'] as $route) {
            $template = "mobile/$route.html.twig";
            $templates[$route] = $this->renderView($template, []);
        }

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
