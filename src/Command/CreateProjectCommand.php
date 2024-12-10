<?php

namespace App\Command;

use App\Entity\Project;
use App\Service\AppService;
use App\Service\LosAltosService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('app:create-project', "create and import a project")]
class CreateProjectCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Create a project')
            ->addArgument('code', InputArgument::REQUIRED, 'projectCode')
            ->addArgument('locale', InputArgument::REQUIRED, 'set locale')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'refresh data from spreadsheet first')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'purge existing items')
            ->addOption('label', null, InputOption::VALUE_OPTIONAL, 'project label',"A label would be helpful!")
            ->addOption('loc', 'loc', InputOption::VALUE_OPTIONAL, 'default location name')
            ->addOption('googleId', null, InputOption::VALUE_OPTIONAL, 'google sheets id')
            ->addOption('flickr', null, InputOption::VALUE_OPTIONAL, 'flickr album id')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'project description',"A description would be helpful for everyone!")
        ;
    }

    public function __construct(private EntityManagerInterface $em,
                                private AppService $appService,
                                #[Autowire('%kernel.project_dir%')] private string $projectDir,
                                string $name = null)
    {
        parent::__construct($name);

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {


        $repo = $this->em->getRepository(Project::class);

        $io = new SymfonyStyle($input, $output);
        $slug = $input->getArgument('code');

        $projectLocale = $input->getArgument('locale');
        if (!$project = $repo->findOneBy(['code' => $slug])) {
            $project = (new Project())
                ->setCode($slug)
                ->setLabel($slug, $projectLocale); // a default
            $this->em->persist($project);
        }
        if ($googleId = $input->getOption('googleId')) {
            $project->setGoogleSheetsId($googleId);
        }
        if ($id = $input->getOption('flickr')) {
            $project->setFlickrAlbumId($id);
        }
        $project->setLocale($projectLocale);

        if ($description = $input->getOption('description')) {
            $project->setDescription($description, $projectLocale);
        }

        // if items are missing a location, a default is required.  This could be tied to a collection someday
        if ($locationName = $input->getOption('loc')) {
            $this->appService->findOrCreateLocation($project, $locationName);
        }

        if ($label = $input->getOption('label')) {
            $project->setLabel($label, $projectLocale);
            $project->setName($label); // original
        }

        $this->em->flush();

        // refresh from Google, someday this will probably be direct
        if ($input->getOption('refresh')) {
            $this->appService->downloadSheetsToLocal($project);
        }

        assert($project->getLabel(), "no label");
        assert($project->getDescription(), "no description");
        $this->em->flush();

        $refresh = $input->getOption('refresh');
        $this->appService->import($project, $refresh );

        // the "source"

        $io->success(sprintf("project %s created/updated.", $project->getCode())) ;
        return 0;
    }
}
