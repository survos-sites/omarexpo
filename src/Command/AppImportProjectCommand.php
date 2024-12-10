<?php

namespace App\Command;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\ConfigureWithAttributes;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;

#[AsCommand('app:import-project', 'import a project and items')]
final class AppImportProjectCommand extends InvokableServiceCommand
{
    use RunsCommands;
    use RunsProcesses;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectRepository $projectRepository
    )
    {
        parent::__construct();
    }

    public function __invoke(
        IO $io,

        #[Autowire('%kernel.project_dir%')] string $projectDir,
        #[Argument(description: 'the project code')]
        string $code = '',

        #[Option(description: 'delete the project before reloading')]
        bool $reset = false,
    ): void {
        if (!$project = $this->projectRepository->findOneBy(['code' => $code])) {
            $project = (new Project())
                ->setCode($code);
            $this->entityManager->persist($project);
        }



        $this->entityManager->flush();
        $io->success('app:import-project success.');
    }
}
