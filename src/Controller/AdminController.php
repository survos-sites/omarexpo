<?php

namespace App\Controller;

use App\Entity\Project;
use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{

    #[Route('/admin/trans', name: 'admin_translate')]
    public function trans(TranslationService $translationService): Response
    {
        $translationService->translateEntities(Project::class);
        return $this->redirectToRoute('project_admin_index');

    }


    }
