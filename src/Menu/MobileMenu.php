<?php

namespace App\Menu;

use Survos\MobileBundle\Event\KnpMenuEvent;
use Survos\BootstrapBundle\Service\MenuService;
use Survos\BootstrapBundle\Traits\KnpMenuHelperInterface;
use Survos\BootstrapBundle\Traits\KnpMenuHelperTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

// events are
/*
// #[AsEventListener(event: KnpMenuEvent::NAVBAR_MENU2)]
#[AsEventListener(event: KnpMenuEvent::SIDEBAR_MENU, method: 'sidebarMenu')]
#[AsEventListener(event: KnpMenuEvent::PAGE_MENU, method: 'pageMenu')]
#[AsEventListener(event: KnpMenuEvent::FOOTER_MENU, method: 'footerMenu')]
#[AsEventListener(event: KnpMenuEvent::AUTH_MENU, method: 'appAuthMenu')]
*/

final class MobileMenu implements KnpMenuHelperInterface
{
    use KnpMenuHelperTrait;

    public function __construct(
        #[Autowire('%kernel.environment%')] protected string $env,
        private MenuService                                  $menuService,
        private Security                                     $security,
        private ?AuthorizationCheckerInterface               $authorizationChecker = null
    )
    {
    }

    public function appAuthMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $this->menuService->addAuthMenu($menu);
    }

    #[AsEventListener(event: KnpMenuEvent::MOBILE_PAGE_MENU)]
    public function pageMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $this->add($menu, id: 'about', icon: 'fa-info');
    }

    #[AsEventListener(event: KnpMenuEvent::MOBILE_TAB_MENU)]
    public function tabMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $this->add($menu, id: 'items', label: 'items', icon: 'fa-list');
        $this->add($menu, id: 'share', label: 'share', icon: 'fa-qrcode');

    }
}
