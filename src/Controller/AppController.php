<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ItemRepository;
use App\Repository\ProjectRepository;
use App\Service\AppService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Yaml\Yaml;

//#[Route('/{_locale}')] // , name: 'app')]
#[Route('/admin')] // , name: 'app')]
//#[IsGranted("ROLE_ADMIN")]
class AppController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    )
    {

    }

    #[Route(path: '/tours.{_format}', name: 'projects_json', methods: ['GET'])]
    public function ProjectJson(Request $request, NormalizerInterface $normalizer, $_format = 'html'): Response
    {
        $groups = $request->get('groups', ['project']);
        $projectSlug = $request->get('projectSlug', null);

        // really should only do the public projects
        $projects = $this->entityManager->getRepository(Project::class)
            ->findBy($projectSlug ? ['code' => $projectSlug] : []);
        $data = $normalizer->normalize($projects, null, ['groups' => $groups]);
        return $this->jsonResponse($data, $request, $_format);
    }

    #[Route(path: '/profile', name: 'app_profile', methods: ['GET'])]
    public function profile()
    {
        $user = $this->getUser();
        return $this->render('security/profile.html.twig', [
            'user' => $user
        ]);
    }

    #[Route(path: '/', name: 'app_homepage', methods: ['GET'])]
    public function homepage(Request $request, ProjectRepository $projectRepository)
    {
        return $this->render('project/index.html.twig', [
            'projects' => $projectRepository->findAll(),
        ]);

        /* @var Project $project */
        if ($project = $request->attributes->get('project')) {
            // during dev.  mobile app in production
            return $this->redirectToRoute('project_show', $project->getRp());
        }
        $user = $this->getUser();
//        return $this->render('starter.html.twig', [
        return $this->render('app/homepage.html.twig', []);
        return $this->render('@SurvosMobile/start.html.twig', [
            'user' => $user,
            'projects' => []
        ]);
    }


    /**
     * @Cache(expires="+2 days")
     */
    #[Route(path: '/api/project-json/{projectId}.{_format}', name: 'project_json', methods: ['GET'], options: ['expose' => true])]
    public function TourJson(Request $request, Project $project, NormalizerInterface $normalizer, $_format = 'json'): Response
    {
        $groups = $request->get('groups', ['project']);
        $data = $normalizer->normalize($project, null, ['groups' => $groups]);
        return $this->jsonResponse($data, $request, $_format);
    }

    #[Route(path: '/_docs/html', name: 'docs')]
    public function docs(): void
    {
        // could be a passthrough, since really this looks for /docs in /public
    }

    #[Route(path: '/md/', name: 'app_markdown')]
    public function markdown()
    {
        return $this->render('docs/markdown.html.twig', [
            'content' => file_get_contents(__DIR__ . '/../../README.md')
        ]);
        // could be a passthrough, since really this looks for /docs in /public
    }

    #[Route(path: '/receive-email', name: 'app_receive_email')]
    public function receiveEmail(Request $request, LoggerInterface $logger)
    {
        // the to address has the item id.
        // the FROM contains the instruction: Pedro Coronel Photo Request
        // upload the attachments
        // upload the title, etc.
        // save the item

        $attachments = [];
        if ($attachmentCount = $request->get('attachments', 0)) {
            // get the attachments
            for ($i = 1; $i < $attachmentCount; $i++) {
                $attachments[] = ($attachment = $request->get('attachment' . $i));
            }
        }

        foreach ($request->files as $file) {
            /** @var UploadedFile $fileName */
            $tempName = $file->getPath() . '/' . $file->getFilename();
            die($tempName . get_class($file));
        }


        foreach ($request->request->all() as $var => $val) {
            if (!in_array($var, ['charsets', 'envelope', 'dkim', 'SPF'])) {
                $logger->info($var, ['value' => $val]);
            }
        }

        /** @var $this AbstractController */
        return $this->render("email/receiveEmail.html.twig", [
            'vars' => $request->request->all(),
            'attachments' => $attachments,
        ]);
        // could be a passthrough, since really this looks for /docs in /public
    }

    #[Route(path: '/files', name: 'app_files')]
    public function listFiles(AppService $appService)
    {
        $finder = new Finder();


        $rootDir = '/home/tac/data/inventory';
        $finder->in($rootDir)->name('*.xlsx');
        return $this->render('app/index.html.twig', [
            'files' => $finder->files(),
            'rootDir' => $rootDir
        ]);
    }

    #[Route(path: '/download', name: 'app_download')]
    public function download(Request $request)
    {
        $filename = $request->get('filename');

        $response = new BinaryFileResponse(new File($filename), 200, [
            'Content-Type' => 'application/vnd.ms-excel'
        ]);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($filename)
        );
        return $response;

    }

    #[Route(path: '/sb', name: 'app_sb_example')]
    public function sb(Request $request)
    {
        return $this->render('dashboard_example.html.twig');

    }


    /** todo: move to service and CLI, for cron.  Maybe leave here for manual check?
     */
    #[Route(path: '/admin/check-email/', name: 'app_check_email')]
    public function checkEmail(ConnectionInterface $exampleConnection, AppService $appService, ItemRepository $exhibitRepository)
    {
//        $mailbox = $imap->get('gmail_connection');
        $mailbox = $exampleConnection->getMailbox();
        // Get all emails (messages)
        // PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php

        $items = $appService->importEmail($mailbox);

        // in case we got new items.
        foreach ($items as $item) {
            $this->entityManager->persist($item);
        }
        $this->entityManager->flush();


        // print_r($mail->getAttachments());

        return $this->render("mailbox.html.twig", [
            'items' => $items,
        ]);
    }

    #[Route(path: '/m', name: 'project_index', methods: ['GET'])]
    public function index(Request $request): Response
    {

        // mobile-friendly SPA, top level (tour list).
        return $this->render('tour/.html.twig', [
            // hack!
            'project' => (new Project())
        ]);
    }


    private function array_trim($input)
    {
        return is_array($input) ? array_filter($input,
            function (&$value) {
                return $value = $this->array_trim($value);
            }
        ) : $input;
    }

    #[Route(path: '/{projectId}/exhibits-feed.{_format}', name: 'exhibits_feed', options: ['expose' => true])]
    public function exhibitsFeed(Request $request, Project $project, ItemRepository $repo, NormalizerInterface $normalizer, SerializerInterface $serializer, $_format = 'json')
    {
        //testing with all exhibits in one large array

        // $exhibits = $project->getExhibitsWithAudio();

        $groups = ['project', 'playlist', 'rooms'];
        if ($_format === 'html') {
            $data = $normalizer->normalize($project, $_format, ['groups' => $groups]);
            // dd($data);
            return $this->jsonResponse($data, $request);
        }

        $data = $serializer->serialize($project, $_format, ['groups' => $groups]);
        $data = $normalizer->normalize($project, $_format, ['groups' => $groups]);
        $data = $this->array_trim($data);
        $yaml = Yaml::dump(['tour' => $data], 6);
        return new Response($yaml, 200, ['Content-Type' => 'text/plain']); //  . $_format]);

        return new Response($data, 200, ['Content-Type' => 'application/' . $_format]);

        return $this->json(json_decode($data, true));
    }

    #[Route(path: '/{projectId}/audio-guide', name: 'player')]
    public function playlist(Project $project, ItemRepository $repo)
    {
        $exhibits = $project->getExhibitsWithAudio();
        return $this->render("playlist.html.twig", [
            'project' => $project,
            'exhibits' => $exhibits
        ]);
    }

}
