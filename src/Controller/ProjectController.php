<?php

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\Item;
use App\Entity\ItemCollection;
use App\Entity\Location;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Property;
use App\Form\AssetFormType;
use App\Form\PlannerFormType;
use App\Form\ProjectType;
use App\Form\ProjectWizardType;
use App\Form\PropertyMappingType;
use App\Repository\ItemRepository;
use App\Repository\ProjectRepository;
use App\Service\AppService;
use App\Service\LosAltosService;
use App\Service\SheetService;
use Aws\S3\S3Client;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer;
use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Survos\CoreBundle\Traits\JsonResponseTrait;
use Survos\FlickrBundle\Services\FlickrService;
use Survos\GoogleSheetsBundle\Service\GoogleApiClientService;
use Survos\GoogleSheetsBundle\Service\GoogleSheetsApiService;
use Survos\Scraper\Service\ScraperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/{_locale}/admin')] // , name: 'admin')]
//#[IsGranted('PROJECT_EDIT', 'project')]
class ProjectController extends AbstractController
{


    use JsonResponseTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager, private readonly AppService $appService, private readonly SerializerInterface $serializer
    )
    {
    }

    #[Route(path: '/', name: 'project_admin_index', methods: ['GET'])]
    public function manager_dashboard(ProjectRepository $projectRepository): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $projectRepository->findAll(),
        ]);
    }

    #[Route(path: '/{projectId}/sheet', name: 'project_google_sheet_redirect', methods: ['GET'])]
    public function google_sheet_redirect(Project $project): Response
    {
        return $this->redirect($project->getGoogleSheetsUrl());
    }

    #[Route(path: '/search', name: 'project_admin_search', methods: ['GET'])]
    public function search(Request $request, ProjectRepository $projectRepository): Response
    {
        $q = $request->get('q', 'Goitia');
        $projects = $projectRepository->findBySearch($q);
        return $this->render('project/index.html.twig', [
            'projects' => $projects
        ]);
    }

    #[Route('/{projectId}/flysystem_default', name: 's3_browser')]
    public function s3Browser(Project $project, FilesystemOperator $defaultStorage): Response
    {
        $images = $defaultStorage->listContents('/' . $project->getCode() . '/', deep: true);
        $listing = [];
        foreach ($images as $image) {
            if ($image->isDir()) {
                continue;
            }
            $listing[] = $image;
        }
        return $this->render('project/flysystem.html.twig', get_defined_vars() +
            [
                'listing' => $listing,
                'controller_name' => 'FlysystemController',
                'project' => $project,
            ]);
    }

    #[Route(path: '/{projectId}/labels/{layout}', name: 'project_admin_labels', methods: ['GET'])]
    public function labels(Request $request, Project $project, string $layout = 'card5'): Response
    {
        return $this->render('project/labels.html.twig', [
            'layout' => $layout,
            'project' => $project
        ]);
    }

    /**
     * layout the locations (aka walls) on the stage
     *
     * @param Project $project
     * @return int[]
     */
    private function layoutStage(Project $project, $maxStageWidth = 900): array
    {
        return [];
        // at least during testing, calculate the stage
        $widthTotal = $heightTotal = 0;
        $locMargin = 20; // in cm!
        $maxHeight = 0;
        $x = $y = 0;
        $stageWidth = 0;
        $stageHeight = 0;
        foreach ($project->getLocations() as $location) {
            if (!$location->isActive()) {
                continue;
            }
            $needsNewRow = $x + $location->getWidth() + $locMargin > $maxStageWidth;
            if ($needsNewRow) {
//                dd($location);
                $y += $maxHeight + (2 * $locMargin); // move to next line
                $maxHeight = 0;
                $x = 0;
            }
            $widthTotal += $location->getWidth();
            $heightTotal += $location->getHeight();

            // again?? Ugh.
            $location->setX($x);
            $location->setY($y);
            $maxHeight = max($maxHeight, $location->getHeight());
            $stageHeight = max($stageHeight, $y + $maxHeight); // the current maxHeight for this row
            $stageWidth = max($stageWidth, $x, $location->getWidth());
            $x += $locMargin + $location->getWidth(); // the next item
        }

        $stageWidth = 0;
        foreach ($project->getLocations() as $location) {
            $stageWidth = max($stageWidth, $location->getX() + $location->getWidth() + $locMargin);
        }

        // there's probably a way to get the screen size
        $margin = 10;
//        $stageWidth = (100 * $widthTotal) + ($margin * ($project->getLocations()->count() + 1));
//        $stageHeight = (100 * $heightTotal) + ($margin * ($rows + 2));
        $rows = 1;
//        assert($stageWidth <= $maxStageWidth, "stage width is too wide. $maxStageWidth $stageWidth");
//        dd($stageHeight, $stageWidth);

        // add the final row height
        $stageHeight += $maxHeight;
        return [$stageHeight, $stageWidth];
    }

    #[Route(path: '/{projectId}/wall-layout.{_format}', name: 'project_wall_layout', methods: ['GET'])]
    public function layout(Request                  $request, Project $project,
                           NormalizerInterface $normalizer,
                           CacheManager $imagineCacheManager,
                           #[MapQueryParameter] int $width = 800,
                                                    $_format = 'html'): Response
    {
        $stagingAreaWidth = 0;
        [$stageHeight, $stageWidth] = $this->layoutStage($project, $width);
        if ($_format === 'json') {
            $loc = $normalizer->normalize($project->getLocations(), null, ['groups' => ['location.read', 'translation', 'shape', 'item.read', 'location.items']]);
            return new JsonResponse([
                'locations' => $loc,
                    'stageWidth' => $stageWidth,
                    'stageHeight' => $stageHeight,
                    ]
            );
        }
        return $this->render('project/items-layout.html.twig', [
            'stageWidth' => $stageWidth,
            'stageHeight' => $stageHeight,
            'windowWidth' => $width,
            'project' => $project,
            'items' => $project->getItems()->filter(fn(Item $item) => $item->getLocation()),
        ]);
//        assert($stagingAreaWidth, "no staging area");
//        assert($stageWidth, "no staging width");
    }

    #[Route(path: '/{projectId}/planner-form', name: 'project_planner_form', methods: ['POST', 'GET'])]
    public function plannerForm(Request                  $request,
                            CacheManager $imagineCacheManager,
                            Project                  $project,
                            #[MapQueryParameter] int $width = 800
    ): Response
    {
        $maxRoomHeight = 0;
        $minRoomHeight = INF;
        foreach ($project->getLocations() as $location) {
            $maxRoomHeight = max($maxRoomHeight, $location->getHeight());
            $minRoomHeight = min($maxRoomHeight, $location->getHeight());
        }

        $formData = [
            'width' => 800,
            'locations' => [], // $project->getLocations()->toArray()
            'maxHeight' => $maxRoomHeight
        ];
        if ($project->getLocations()->isEmpty()) {
            $this->addFlash('ERROR', "You must have locations to use the planner");
            return $this->redirectToRoute('project_dashboard', $project->getrp());
        }
//        dd($minRoomHeight, $maxRoomHeight);
        $form = $this->createForm(PlannerFormType::class,
            $formData, [
                'locations' => $project->getLocations(),
                'maxLocHeight' => $maxRoomHeight,
                'minLocHeight' => $minRoomHeight,
            ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $queryParams = $form->getData();
            $x = [];
            foreach ($queryParams['locations'] as $location) {
                $x[] = $location->getId();
            }
            $queryParams['locations'] = $x;
//            dd($queryParams);
//            $x = array_map(fn(Location $location, $idx) => $location->getCode(), $queryParams('locations'));
//            dd($x);
            return $this->redirectToRoute('project_planner', $project->getRP($queryParams));
        }
        return $this->render('project/planner_form.html.twig', [
            'project' => $project,
            'form' => $form->createView()
            ]
        );
    }

    #[Route(path: '/{projectId}/planner', name: 'project_planner', methods: ['GET'])]
    public function planner(Request                  $request, CacheManager $imagineCacheManager,
                            Project                  $project,
                            #[MapQueryParameter] int $width = 800,
                            #[MapQueryParameter] array $locations = []
    ): Response
    {
            // get the thumbnail urls
        $thumbs = [];
        $stagingAreaWidth = 0;
        foreach ($project->getItems() as $item) {
            foreach ($item->getImages() as $image) {
                $resolvedPath = $imagineCacheManager->getBrowserPath($image->getFilename(), 'tiny');
                $thumbs[$item->getCode()] = $resolvedPath;
                if (!$item->getLocation()) {
                    $stagingAreaWidth = max($stagingAreaWidth, $item->getWidth());
                }
            }
        }

        // order the inventory by size.  Rendered in twig, not planner_controller
        $items = $project->getItems()
            ->filter(fn(Item $item) => !$item->getLocation());
        $iterator = $items->getIterator();
        $iterator->uasort(fn(Item $a, Item $b) =>
            //            dd($a, $b->getHeight());
            //            return ($a->getSize() < $b->getSize()) ? -1 : 1;
            ($a->getHeight() < $b->getHeight()) ? 1 : -1);
//        iterator_apply($iterator, function () {
//            dump($iterator->getHeight());
//        }, $items->toArray());
        $sortedInventoryItems = new ArrayCollection(iterator_to_array($iterator));
//        $sortedInventoryItems->map( fn(Item $item) =>
//            dump($item->getHeight())
//        );
//        dd($sortedInventoryItems);

        // @todo: move to JSON call in planner_controller
        [$stageHeight, $stageWidth] = $this->layoutStage($project, $width);
//        dd($stageWidth, $stageHeight);

        // maybe we _should_ pass the locations and the API should simply calculate the positions?
        // otherwise, we need to pass the filters.  but that would allow us to move the filters to
        // the planner.
        return $this->render('project/planner.html.twig', [
            'locationIds' => $locations,
            'project' => $project,
            'inventoryItems' => $sortedInventoryItems,
            'thumbs' => $thumbs,
            'windowWidth' => $width,
            'scale' => $request->get('scale', 100),
            // these are for debugging the first load, will be eventually removed.
            'stageWidth' => $stageWidth,
            'stageHeight' => $stageHeight,
        ]);
    }

    #[Route(path: '/{projectId}/sync', name: 'project_sync', methods: ['GET'])]
    public function sync(Request                $request, Project $project,
                         ItemRepository         $itemRepository,
                         SheetService           $ourSheetService,
                         GoogleSheetsApiService $sheetService): Response
    {
        $sheetService->setSheetServices($project->getGoogleSheetsId());
        // hackish, for testing.  get the items from the spreadsheet and update them from the db.
        // @todo: store cell value in database?
        foreach ($project->getCollections() as $collection) {
            $values = $sheetService->getValues($collection->getCode(), refresh: true);
            // again??
            foreach ($values as $idx => $row) {
                if ($idx == 0) {
                    $keys = $row;
                    continue;
                }
                try {
                    if (count($row) < count($keys)) {
                        $row = array_pad($row, count($keys), null);
                    }
                    $record = array_combine($keys, $row);
                } catch (\Exception) {
                    continue;
                }
                // first, get the db record
                $item = $itemRepository->findOneBy(['project' => $project, 'code' => $code = $record['code*']]);
                assert($item, "missing $code in database");
                // if there's no position, continue
                if ($item->getX() + $item->getY() === 0) {
                    continue;
                }
                // need space to avoid treating as european number
                $position = sprintf("%d, %d", $item->getX(), $item->getY());
                $fields = [
                    'position' => $position,
                    '#loc' => $item->getLocation() ? $item->getLocation()->getCode() : null
                ];

                foreach ($fields as $key => $value) {
                    if (array_key_exists($key, $record) && ($record[$key] <> $value)) {
                        $keyIdx = array_search($key, $keys);
                        $colByLetter = range('A', 'Z')[$keyIdx];

                        $values = array_values([$value]);
                        $cell = $collection->getCode() . '!' . $colByLetter . $idx + 1;
                        $ourSheetService->updateCell($project->getGoogleSheetsId(),
                            $cell, $values);
                    }
                }
            }
        }


//        $data  = $sheetService->getData($project->getGoogleSheetsId()); dd($data);
//        $sheetService->importGoogleSheet($project);
        $this->entityManager->flush();
        return $this->redirectToRoute('project_dashboard', $project->getrp());
    }

    #[Route(path: '/wizard', name: 'project_wizard', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function wizard(Request $request, SluggerInterface $asciiSlugger): Response
    {
        $project = new Project();
        $project->setName('Test ' . random_int(0, 1000))
            ->setCode($asciiSlugger->slug($project->getName()));

        $form = $this->createForm(ProjectWizardType::class, $project);
        $form->handleRequest($request);

        return $this->render('project/wizard.html.twig', [
            'form' => $form->createView(),
            'project' => $project
        ]);
    }


    #[Route(path: '/new', name: 'project_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, SluggerInterface $asciiSlugger, SheetService $sheetService): Response
    {

        $project = new Project();
        $project = new Project();
        $project->setName('Test ' . random_int(0, 1000))
            ->setLocale('en')
            ->setCode($asciiSlugger->slug($project->getName()));

        $form = $this->createForm(ProjectType::class, $project, $formConfig = ['includeProperties' => false]);
        $form->handleRequest($request);
        $entityManager = $this->entityManager;

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($project);

            if (empty($project->getCode())) {
                $project->setCode(AppService::createShortCode($project->getName()));
            }

            $sheetService->importGoogleSheet($project);

            // create a root node with the projectCode as the name?
            $rootNode = $project->createCollection(['name' => $project->getCode()])
                ->setPath('/' . $project->getCode());

            // first time only.  Could also be a YAML file
            if ($form->has('collectionNames'))
                foreach (explode("\n", (string) $form->get('collectionNames')->getData()) as $roomName) {
                    $room = (new ItemCollection(['name' => $roomName, 'parent' => $rootNode]));
                    // $project->addCollection($room); // this is done in the constructor if parent is set.
                }

            if ($form->has('propertyNames'))
                foreach (explode("\n", (string) $form->get('propertyNames')->getData()) as $propertyName) {
                    $property = (new Property(['project' => $project, 'name' => $propertyName])
                    );
                    $project->addProperty($property);
                }

            if ($form->has('locationNames'))
                foreach (explode("\n", (string) $form->get('locationNames')->getData()) as $name) {
                    $location = (new Location(['project' => $project, 'name' => $name]));
                    $project->addLocation($location);
                }

            $member = (new Member(['project' => $project]))
                ->setRoles(['MANAGE'])
                ->setProject($project)
                ->setUser($this->getUser());
            $entityManager->persist($member);

            $entityManager->flush();


            return $this->redirectToRoute('project_dashboard', $project->getRP());
        }

        return $this->render('project/new.html.twig', array_merge([
            'project' => $project,
            'form' => $form->createView(),
        ], $formConfig));
    }

    #[Route(path: '/show/{projectId}', name: 'project_show', methods: ['GET', 'POST'], options: ['expose' => true])]
    public function show(Request                     $request,
                         Project                     $project,
                         AppService                  $appService,
                         EntityManagerInterface      $entityManager,
                         #[MapQueryParameter] string $filterString = '',
    ): Response
    {


        //  $page = $this->viewcounter->saveView($article);
        /** @var \App\Entity\ViewCounter $projectViewCounter */
        if (false)
            if (!empty($viewCounter)) {

                $projectViewCounter = $viewCounter->getViewCounter($project);

                if ($viewCounter->isNewView($projectViewCounter)) {
                    $views = $viewCounter->getViews($project);
                    $projectViewCounter->setIp($request->getClientIp());
                    $projectViewCounter->setProject($project);
                    $projectViewCounter->setProperty('project');
                    $projectViewCounter->setLocale($request->getLocale());
//            $projectViewCounter->set($article);
                    $projectViewCounter->setViewDate(new \DateTime('now'));
                    $project->setViews($views);

                    $em = $this->entityManager;
                    $em->persist($projectViewCounter);
                    $em->persist($project);
                    $em->flush();
                }
            }

        // copied from ItemController show, make a trait?

        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/import/{projectId}', name: 'project_import')]
    #[Route('/action/{projectId}/{action}', name: 'project_action')]
    public function action(Request $request, Project $project, AppService $appService,
                           ?string  $action = null): Response
    {
        $action = match ($request->get('_route')) {
            'project_import' => 'import',
            default => $action
        };

        $referer = (string)$request->headers->get('referer'); // get the referer, it can be empty!
        if ($request->get('refresh', false)) {
            $files = $appService->downloadSheetsToLocal($project);
            foreach ($files as $file) {
                $this->addFlash('info', "Created " . $file);
            }
        }
        switch ($action) {
            case 'import':
                $dirname = sprintf('./../data/%s', $project->getCode());
                $appService->import($project, $dirname);
                break;
            case 'reset_locations':
                foreach ($project->getLocations() as $location) {
                    foreach ($location->getItems() as $item) {
                        $item->setLocation(null)
                            ->setX(0)
                            ->setY(0);
                    }
                }
                $this->entityManager->flush();
        }
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('project_dashboard', $project->getrp());
    }

    #[Route('/admin/download-sheets/{projectId}', name: 'download_sheet')]
    public function download_sheets(
        Project                $project,
        AppService             $appService,
        GoogleApiClientService $clientService,
        GoogleSheetsApiService $sheetService,
    ): Response
    {
        $files = $appService->downloadSheetsToLocal($project);
        return $this->redirectToRoute('project_dashboard', $project->getrp());


        $sheetService->setSheetServices($project->getGoogleSheetsId());
        $spreadsheetService = $sheetService->getGoogleSpreadSheets();
        foreach ($spreadsheetService->getSheets() as $sheet) {
            $range = $sheet->getProperties()->getTitle();
            $values = $sheetService->getValues($range, true);

//            $values = $sheetService->getGoogleSpreadSheets()->->get($project->getGoogleSheetsId(), $range);

            dump($spreadsheetService, $values, count($values), $sheet->getProperties());
        }
        dd('stop');
        $sheetService->getValues();

        $sheetService->addNewSheetWithoutData('xx');
        $sheetService->setSheetServices($project->getGoogleSheetsId());
        $sheetService->getSheetIdByTitle('_p');
        $sheetService->
        $data = [['name', 'age'], ['Alice', 30], ['Bob']];
        $sheetTitle = '_test';
        if (!$id = $sheetService->getSheetIdByTitle($sheetTitle)) {
            $response = $sheetService->createNewSheet($sheetTitle, $data);
            dump(newSheetResponse: $response);
        }

        $updateResponse = $sheetService->updateSheet($sheetTitle, $data, 0);
        dd($id, $sheetTitle, updateResponse: $updateResponse);

        dd($response);

        $sheets = $sheetService->getGoogleSpreadSheets();
        foreach ($sheets as $sheet) {
            dd($sheet->properties);
            if (isset($sheet->properties->title) && $sheet->properties->title == $title) {
                return $sheet->properties->sheetId;
            }
        }

        dd($sheetService->getSheetRangeByData('_p'));
        $files = $appService->downloadSheetsToLocal($project);
        // hack for los altos
        if ($project->getCode() == 'mach') {
//            $altosService->populateImages($project);
        }
        return $this->redirectToRoute('project_show', $project->getrp());
    }


    #[Route(path: '/export/{projectId}', name: 'project_export', methods: ['GET', 'POST'], options: ['expose' => true])]
    public function export(Request             $request,
                           SerializerInterface $serializer,
                           Project             $project, AppService $appService, EntityManagerInterface $entityManager): Response
    {
        $data = $serializer->serialize($project, 'json', ['groups' => ['export', 'project.read', 'browse', 'item.read']]);
        return new Response($data, Response::HTTP_OK, [], true);
        return $this->render('project/export.html.twig', [

            'project' => $project,
            'roomRepo' => $entityManager->getRepository(ItemCollection::class)
        ]);
    }

    #[Route(path: '/gallery/{projectId}', name: 'project_gallery', methods: ['GET', 'POST'], options: ['expose' => true])]
    public function gallery(Request $request, Project $project, AppService $appService, EntityManagerInterface $entityManager): Response
    {
        return $this->render('project/gallery.html.twig', [
            'repairs' => file_get_contents(__DIR__ . '/../../repairs.md'),
            'project' => $project,
            'roomRepo' => $entityManager->getRepository(ItemCollection::class)
        ]);
    }

    #[Route(path: '/s3/{projectId}', name: 'project_s3')]
    #[IsGranted('MANAGE', subject: 'project')]
    public function s3Assets(S3Client $s3, Project $project)
    {

// register a 's3://' wrapper with the official AWS SDK
        $s3->registerStreamWrapper();

        $finder = new Finder();
        $bucket = $this->getParameter('s3_bucket'); // . '/' . $project->getCode();

        $result = $s3->listObjects(['Bucket' => $bucket, 'Prefix' => $project->getCode(), 'MaxKeys' => 100]);
        return $this->render('media/list.html.twig', [
            'results' => $result['Contents'],
            'files' => $finder->depth('<2')->in('s3://' . $bucket)
        ]);
    }


    #[Route(path: '/{projectId}/download/{type}', name: 'project_download')]
    public function download(Request $request)
    {
        // for yaml and json, use serializer

        // for excel, prompt for what to download

        $filename = $request->get('filename');

        $response = new BinaryFileResponse(new File($filename), 200, [
            'Content-Type' => 'application/vnd.ms-excel'
        ]);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename((string) $filename)
        );
        return $response;

    }

    /**
     * we     */
    #[Route(path: '/detail/{projectId}', name: 'project_detail', methods: ['GET', 'POST'], options: ['expose' => true])] // we
    public function detail(Request $request, Project $project, AppService $appService, EntityManagerInterface $entityManager): Response
    {
        $summary = $appService->getRelatedClassesSummary(Project::RELATED_CLASSES, $project, $request);
        return $this->render('project/detail.html.twig', [
            'summary' => $summary,
            'project' => $project,
            'roomRepo' => $entityManager->getRepository(ItemCollection::class)
        ]);
    }


    #[Route(path: '/flickr/{projectId}', name: 'project_flickr', methods: ['GET'])]
    public function flickr(Request $request, Project $project, FlickrService $flickr): Response
    {

        $perm = 'read';
        $url = $flickr->getAuthUrl($perm, $callbackUrl);

//        $flickr = $flickrService->getFlickr();
//        $flickr->uploader()->upload('abc.jpg')
        // flickr.urls.lookupUser
        $userId = $flickr->test()->login();
        dd($userId);

        // Create storage.
//        $recent = $flickr->photos()->getRecent([], 10);
        $userId = '26016159@N00';
        $result = $flickr->photosets()->getList($userId);
        $result = $flickr->photosets()->getPhotos(
            72177720317358478,
            $userId,
            ['media' => 'photos, url_o, tags']
        );

        return $this->render('project/flickr.html.twig', get_defined_vars() + [
                'result' => $result
            ]);

        dd($recent);
        // $key is your Flickr API key. $format is optional, it sets the Flickr response format.
        $FLICKR_API_KEY = '5dc85891c4d74a63260b589f37a533d7';
        $FLICKR_SECRET = '14a296ed7b3bed99';
        $flickr = new Flickr(new Api($FLICKR_API_KEY));
//        $photosets = $flickr->listSets(['user_id' => '26016159@N00']);
//        dd($photosets->photosets);

        $result = $flickr->request('flickr.photosets.getPhotos', [

        ]);
        return $this->render('project/flickr.html.twig', [
            'project' => $project,
            'photoset' => $result->photoset // magic method
        ]);
        dd($result);
//        https://www.flickr.com/services/rest/?method=flickr.photosets.getPhotos&api_key=c3f932f18d75658a6151f15a79911950&photoset_id=72177720317358478&user_id=26016159%40N00&media=&format=json&nojsoncallback=1

// https://www.flickr.com/services/api/flickr.test.echo.html
        $echoTest = $flickr->echoThis('helloworld');
// https://www.flickr.com/services/api/flickr.photosets.getList.html

// Setting up other API requests. See https://www.flickr.com/services/api

    }

    /**
     * @IsGranted("MANAGE", subject="project")
     */
    #[Route(path: '/old-dashboard/{projectId}', name: 'project_old_dashboard', methods: ['GET', 'POST'])]
    #[Route(path: '/dashboard/{projectId}', name: 'project_dashboard', methods: ['GET', 'POST'])]
    public function dashboard(Request $request, Project $project, EntityManagerInterface $em, AppService $appService): Response
    {


//        $summary = $appService->getRelatedClassesSummary(Project::RELATED_CLASSES, $project, $request );
        $summary = [];
        return $this->render('project/dashboard.html.twig', [
            'project' => $project,
            'summary' => $summary
        ]);
    }

    /**
     * @Cache(expires="+2 days")
     */
    #[Route(path: '/form-builder-data/{projectId}.{_format}', name: 'project_form_data')]
    public function formBuilderData(Request $request, Project $project, NormalizerInterface $normalizer, $_format = 'html')
    {
        $data = $normalizer->normalize($project->getProperties(), null, ['groups' => ['export']]);

        return $this->jsonResponse($data, $request);
    }

    #[Route(path: '/tree/{projectId}/{display}', name: 'project_collection_tree')] // @ Cache(expires="+2 days")
    public function tree(Request $request, Project $project, $display = 'name')
    {
        $roomRepository = $this->entityManager->getRepository(ItemCollection::class);

        // $rootNodes = $categoryRepository->getRootNodes();
        $rootNodes = $roomRepository->getRootNodes();

        return $this->render('category-tree.html.twig', [
            // 'properties' => json_decode(file_get_contents(__DIR__ . '/../../../sa/assets/js/avataar-properties.json'), true),

            'rootNodes' => $rootNodes,
            'project' => $project,
            'display' => $display,

        ]);

        // could be a passthrough, since really this looks for /docs in /public
    }


    /** This is the public TOUR (not admin), old
     */
    #[Route(path: '/app/{projectId}', name: 'project_app', methods: ['GET'])]
    public function app(Request $request, Project $project): Response
    {
        return $this->render('tour/app.html.twig', [
            'playNow' => $request->get('playNow', true),
            'project' => $project,
        ]);
    }


    #[Route(path: '/form-jstree/{projectId}.{_format}', name: 'form_jstree_json', methods: ['GET'], options: ['expose' => true])]
    public function jsPropertyTreeJson(Request $request, Project $project, AppService $appService, SerializerInterface $serializer, EntityManagerInterface $em, NormalizerInterface $normalizer, $_format = 'html'): Response
    {
        $formElements = $appService->getFormDataElements($project); // parent is now set
        $json = $serializer->serialize($formElements, 'json', ['groups' => ['jstree']]);
        $data = $normalizer->normalize($formElements, null, ['groups' => ['jstree']]);
        return $this->jsonResponse($data, $request);

    }

    #[Route(path: '/jstree/{projectId}.{_format}', name: 'project_jstree_json', methods: ['GET'], options: ['expose' => true])]
    public function jsTreeJson(Request $request, Project $project, EntityManagerInterface $em, NormalizerInterface $normalizer, $_format = 'html'): Response
    {
        // for now, this is the admin call
        $roomIds = $request->get('roomIds');
        $project->setRoomIdFilter($roomIds);

        $data = $normalizer->normalize($project->getCollections(), null, ['groups' => ['jstree']]);

        return $this->jsonResponse($data, $request, $_format);

    }

    #[Route(path: '/api/{projectId}.{_format}', name: 'project_json', methods: ['GET'], options: ['expose' => true])]
    public function projectJson(Request $request, Project $project, EntityManagerInterface $em, NormalizerInterface $normalizer, $_format = 'html'): Response
    {
        // filter by rooms, exhibit names, etc.  @todo: move to API Platform

        $roomIds = $request->get('roomIds');
        $project->setRoomIdFilter($roomIds);

        $data = $normalizer->normalize($project, null, ['groups' => ['project', 'playlist', 'main', 'roomSlug']]);
//         dd($data, $project);

        return $this->jsonResponse($data, $request, $_format);
    }


    #[Route(path: '/{projectId}/property-mapping', name: 'project_property_mapping', methods: ['GET', 'POST'])]
    public function editProperties(Request $request, Project $project, SerializerInterface $serializer, AppService $appService): Response
    {
        $form = $this->createForm(PropertyMappingType::class,
            $project);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formElements = $appService->getFormDataFromProperties($project);
            $json = $serializer->serialize($formElements, 'json', ['groups' => ['export']]);
            // \dd($formElements, $json);

            $project->setFormData(json_decode($json));
            $this->entityManager->flush();


            return $this->redirectToRoute('project_dashboard', $project->getRP());
        }

        return $this->render('project/mapping_form.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/{projectId}/edit', name: 'project_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project): Response
    {
        $form = $this->createForm(ProjectType::class,
            $project, $formConfig = ['includeProperties' => true]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
//            assert($project->getImageSize(), "upload was not sucessful");
            $this->entityManager->flush();
            return $this->redirectToRoute('project_show', $project->getRP());
        }

        return $this->render('project/edit.html.twig', $formConfig + [
                'project' => $project,
                'form' => $form->createView(),
            ]);
    }

    #[Route(path: '/{projectId}', name: 'project_delete', methods: ['DELETE'])]
    public function delete(Request $request, Project $project): Response
    {
        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {
            $entityManager = $this->entityManager;
            $entityManager->remove($project);
            $entityManager->flush();
        }

        return $this->redirectToRoute('project_index');
    }

    #[Route(path: '/{projectId}/dummy', name: 'project_dummy')]
    public function dummy(Project $project, SheetService $sheetService, ScraperService $scraperService): void
    {
        // location
        $data = $scraperService->fetchData('https://dummyjson.com/products?limit=0', asData: 'object');
        foreach ($data->products as $p) {
            $tabs[$p->category][] = $p;
            $code = implode('-', array_map(fn($part) => substr($part, 0, 3), explode('-', (string) $p->category)));
            $p->id = $code . '-' . count($tabs[$p->category]);

//            dd($code, $p);
        }

        foreach ($tabs as $tabName => $data) {
            $sheetService->getOrCreateSheet($project, $tabName);
            // @todo: move to service and optimize

            $csv = Writer::createFromString();

            foreach ($data as $idx => $row) {
                $row = (array)$row;
                if (!$idx) {
                    $csv->insertOne(array_keys($row));
                } else {
                    $csv->insertOne(array_values($row));
                }
            }


            dd($csv->toString());
        }
    }

}
