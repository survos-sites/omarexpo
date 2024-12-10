<?php

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\Item;
use App\Entity\ItemCollection;
use App\Entity\Location;
use App\Entity\Project;
use App\Form\AssetFormType;
use App\Form\ItemAssetRequestFormType;
use App\Form\ItemAttributesType;
use App\Form\ItemType;
use App\Form\StopType;
use App\Repository\ItemRepository;
use App\Repository\LocationRepository;
use App\Service\AppService;
use App\Service\UploadService;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Spipu\Html2Pdf\Html2Pdf;
use Survos\BaseBundle\Controller\LandingController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Tchoulom\ViewCounterBundle\Counter\ViewCounter;

/**
 * @IsGranted("ROLE_USER")
 */
#[Route(path: '/item/')]
class ItemController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em,
                                private AppService $appService,
                                private readonly NormalizerInterface $normalizer,
                                private readonly SerializerInterface $serializer)
    {
        // because of gedmo tree
    }

    #[Route(path: '/', name: 'item_index', methods: ['GET'])]
    public function index(Request $request, Project $project, ItemRepository $itemRepository): Response
    {

        $value = $request->get('value');
        $locationRepository = $this->locationRepository; // can't auto-wire

        if ($attribute = $request->get('attribute')) {
            $itemsArray = $itemRepository->findByAttribute(
                $attribute = $request->get('attribute'),
                $value);

        } elseif ($locationCode = $request->get('locationCode')) {
            $location = $locationRepository->findBy([
                'project' => $project,
                'code' => $locationCode
            ]);

            $itemsArray = $itemRepository->findBy([
                'location' => $location
            ]);
        } else {
            if ($searchQuery = $request->get('q')) {
                $itemsArray = $itemRepository->findBySearch($searchQuery);
            } else {
                $itemsArray = $itemRepository->findBy([
                    'project' => $project
                ], [], 50);
            }
        }


        // just get the IDs now

        return $this->render('item/index.html.twig', [
            'attribute' => $attribute,
            'value' => $value,
            'items' => $itemsArray
        ]);
    }

    #[Route(path: '/{itemId}/new-asset', name: 'asset_new', methods: ['GET', 'POST'], options: ['expose' => true])]
    public function newAsset(Item $item, Request $request): Response
    {

        $asset = (new Asset())
            ->setItem($item);
        $form = $this->createForm(AssetFormType::class, $asset);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->em;
            $entityManager->persist($asset);
            $entityManager->flush();

            // if we wanted to send an email or something
//            $this->sendToDataCollectors($form, $asset);

            return $this->redirectToRoute('item_show', $item->getRP());
        }

        return $this->render('item/show.html.twig', [
            'project' => $item->getProject(),
            'item' => $item,
            'form' => $form->createView(),
        ]);
    }




    #[Route(path: '/{itemId}', name: 'item_delete', methods: ['DELETE'])]
    public function delete(Request $request, Item $item): Response
    {
        if ($this->isCsrfTokenValid('delete' . $item->getId(), $request->request->get('_token'))) {
            $entityManager = $this->entityManager;
            $entityManager->remove($item);
            $entityManager->flush();
        }

        return $this->redirectToRoute('item_index');
    }

    #[Route(path: '/tomb/{itemId}/{layout}', name: 'item_tomb', methods: ['GET'])]
    public function tomb(Item $item, string $layout = 'fiverr'): Response
    {
        $html = $this->renderView("item/tomb/$layout.html.twig", [
            'i' => $item
        ]);
        return $this->render('item/label_page.html.twig', ['html' => $html]);

    }

    #[Route(path: '/player/{itemId}', name: 'item_player', methods: ['GET'])]
    public function player(Item $item): Response
    {
        return $this->render('acmi/_layouts/episode.html.twig', [
            'exhibit' => $item,
            'site' => $item->getCollection()->getProject(),
            'page' => $item,
            'my_page' => $item,
            'content' => $item->getTranscript()
        ]);
    }

    #[Route(path: '/amplitude-player/{itemId}', name: 'amplitude_player', methods: ['GET'])]
    public function amplitudePlayer(Item $item): Response
    {
        return $this->render('exhibit/player.html.twig', [
            'exhibit' => $item,
            'site' => $item->getCollection()->getProject(),
            'project' => $item->getCollection()->getProject(),
            'page' => $item,
            'my_page' => $item,
            'content' => $item->getTranscript()
        ]);
    }

    /**
     * @param Item $item
     * @return Response
     */
    #[Route(path: '/recorder/{itemId}', name: 'item_recorder', methods: ['GET'])]
    public function recorder(Item $item): Response
    {
        return $this->render('exhibit/microphone.html.twig', [
            'exhibit' => $item
        ]);
    }

    #[Route(path: '/save_audio/{itemId}', name: 'item_save_audio', methods: ['POST'])]
    public function saveAudio(Request $request, S3Client $s3, Item $exhibit, UploadService $uploaderHelper, EntityManagerInterface $em): ?Response
    {

        foreach ($request->files as $file) {
            /** @var UploadedFile $fileName */
            $tempName = $file->getPath() . '/' . $file->getFilename();
            if (!file_exists($tempName)) {
                throw new \Exception("Problem reading " . $fileName);
            }

            dd($file, 'USE UPLOAD HELPER!');
            $uploaderHelper->uploadItemAsset($tempName);
            $newFilename = $request->get('video-filename');

            // upload to s3 bucket, really we should use upload helper!!


            $bucket = $this->getParameter('s3_bucket') ?: 'museo.survos.com'; //@hack!
            $result = $s3->upload($bucket, $newFilename, fopen($tempName, 'rb'), 'public-read');

            // dump($result);

            $exhibit
                ->setDuration(round(filesize($tempName) / 50));
            $em->flush();

            $url = sprintf('https://s3.amazonaws.com/%s/%s', $bucket, $newFilename);


            /*

            dump($result); die();

            $dir = 'uploads';
            $result = $file->move($dir, $newFilename);
            dump($result, $dir, $newFilename);

            $filePath = 'uploads/' . $newFilename;
            if (!move_uploaded_file($tempName, $filePath)) {
                echo ('Problem saving file.');
            }

            die();
            */
            return new JsonResponse([
                'url' => $url,
                // 'result' => $result,
                'uploaded-filename' => $newFilename]);
        }
    }

    /* This was something when using the built-in recorder on a web page.

        if (!isset($_POST['audio-filename']) && !isset($_POST['video-filename'])) {
            echo 'PermissionDeniedError';
        }



        $fileName = '';
        $tempName = '';

        if (isset($_POST['audio-filename'])) {
            $fileName = $_POST['audio-filename'];
            $tempName = $_FILES['audio-blob']['tmp_name'];
        } else {
            $fileName = $_POST['video-filename'];
            $tempName = $_FILES['video-blob']['tmp_name'];
        }

        if (empty($fileName) || empty($tempName)) {
            echo 'PermissionDeniedError';
        }
        $filePath = 'uploads/' . $fileName;

        // make sure that one can upload only allowed audio/video files
        $allowed = array(
            'webm',
            'wav',
            'mp4',
            'mp3',
            'ogg'
        );
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!$extension || empty($extension) || !in_array($extension, $allowed)) {
            echo 'PermissionDeniedError';
        }

        if (!move_uploaded_file($tempName, $filePath)) {
            echo ('Problem saving file.');
        }

        echo ($filePath);
        dump($request); die();
        return $this->render('exhibit/microphone.html.twig', [
            'exhibit' => $exhibit
        ]);
        */
    #[Route(path: '/add/{itemCollectionId}', name: 'item_new', methods: ['GET', 'POST'], options: ['expose' => true])]
    public function newItem(Request $request, Project $project, ItemCollection $collection): Response
    {
        // this handles the firstStep form relations
        if (($itemData = $request->get('item')) && array_key_exists('collection', $itemData)) {
            $itemData['collection'] = ($collection = $this->collectionRepository->find($itemData['collection']));
            $itemData['location'] = $this->locationRepository->find($itemData['location']);
        } else {
        }

        // old way
//        if ($longCode = $request->get('longCode')) {
//            $collection = $this->collectionRepository->findOneBy([
//                'longCode' => $longCode,
//                'project' => $project
//            ]);
//            $itemData = [
//                'collection' => $collection
//            ];
//        }

        $item = new Item($project, $collection);
        $collection->addItem($item);
        $item->setLocale($project->getLocale());

        $quick_add = $request->get('quick_add');
        // these could also contain the item defaults, based on the room attributes
        $item
            ->setStopId($collection->getItems()->count() + 1)
            ->setLocalCode($collection->getItems()->count() + 1)
            // ->setTitle($collection->getName() . ' ' . $item->getStopId())
            // ->setDescription("Description of what this is")
            ->setCollection($collection);

        $form = $this->createForm(ItemType::class, $item, ['quick_add' => $quick_add]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $exhibits = [];

            if ($quick_add) {
                // sigh, should handle this better!  Maybe even an embedded type?  Check for duplicates, add default stop, even assign collectors.
                foreach (explode("\n", $form->get('titles')->getData()) as $title) {
                    $quickExhibit = clone $item;
                    $collection->addItem($quickExhibit);

                    $quickExhibit
                        ->setTitle($title)
                        ->setStopId($item->getCollection()->getItems()->count() + 1);

                    $this->entityManager->persist($quickExhibit);
                    array_push($exhibits, $quickExhibit);
                }
                $redirect = $this->redirectToRoute('collection_show', $collection->getRP());

            } else {
                $collection->addItem($item);
                $this->em->persist($item);
                $redirect = $this->redirectToRoute('item_edit', $item->getRP()); // do we have this yet??  UUID?
                array_push($exhibits, $item);
            }
            $this->em->flush(); // good argument for uuid's!  or Room/stop, etc.

//            $this->sendToDataCollectors($form, $item);


            return $redirect;
        }

        return $this->render('item/new.html.twig', [
            'item' => $item,
            'quick_add' => $quick_add,
            'form' => $form->createView(),
        ]);
    }

    // both edit and new
    private function sendToDataCollectors(FormInterface $form, Item $item): void
    {
        // get the list of emails and only send to them.
        if ($form->has('data_collectors')) {
            if ($member = $form->get('data_collectors')->getData()) {
                $response = $this->appService->sendExisitingItemEmail($item, $member);
                $this->addFlash('notice', "Message sent to " . $member);
            }
        }
    }

    #[Route(path: '/pdf/{itemId}', name: 'item_pdf', methods: ['GET'])]
    public function pdf(Item $exhibit): Response
    {

        $html2pdf = new Html2Pdf();
        $html2pdf->writeHTML('<h1>HelloWorld</h1>This is my first test');
        $html2pdf->output();


        return $this->render('exhibit/show.html.twig', [
            'exhibit' => $exhibit,
        ]);
    }

    /**
     * @IsGranted("MANAGE", subject="item")
     */
    #[Route(path: '/show/{itemId}.{_format}', name: 'item_show', methods: ['GET', 'POST'], options: ['expose' => true])]
    public function itemShow(Request                     $request,
                             Item                        $item,
                             #[MapQueryParameter] string $filterString = '',
    string $_format = 'html'
    ): Response
    {

        return $this->render('item/show.html.twig', [
            'item' => $item,
            'project' => $item->getProject(),
        ]);
    }

    /**
     * @IsGranted("MANAGE", subject="item")
     */
    #[Route(path: '/{itemId}/edit', name: 'item_edit', methods: ['GET', 'POST'], options: ['expose' => true])]
    public function itemEdit(Request $request, Item $item, AppService $appService, UploadService $uploaderHelper): Response
    {
        /* moved to item edit/new form, not a separate form (for now...) */
        $assetRequestForm = $this->createForm(ItemAssetRequestFormType::class, $item->getproject());
        $assetRequestForm->handleRequest($request);

        if ($assetRequestForm->isSubmitted() && $assetRequestForm->isValid()) {
            // get the list of emails and only send to them.
            $member = $assetRequestForm->get('data_collectors')->getData();
            $response = $appService->sendExisitingItemEmail($item, $member);
            $this->addFlash('notice', "Message sent to " . $member);
            // transition to waiting?

            return $this->redirectToRoute('item_edit', $item->getRP());

            // return $this->redirectToRoute('item_index', $item->getCollection()->getRP());
        }

        $attributesForm = $this->createForm(ItemAttributesType::class, $item->getAttributes(), ['item' => $item]);
        $attributesForm->handleRequest($request);
        if ($attributesForm->isSubmitted() && $attributesForm->isValid()) {
            $item->setAttributes($attributesForm->getData());
            $this->em->flush();
            return $this->redirectToRoute('item_show', $item->getRP());
        }

        $form = $this->createForm(ItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var UploadedFile $uploadedFile */
            /* not using this anymore...
            $uploadedFile = $form['imageFile']->getData();

            if ($uploadedFile) {
                $newFilename = $uploaderHelper->uploadExhibitAudio($uploadedFile, $exhibit->getFilename());
                $exhibit->setFilename($newFilename);
            }
            */

            $this->entityManager->flush();
            $this->sendToDataCollectors($form, $item, $appService);

            return $this->redirectToRoute('item_show', $item->getRp());
        }

        return $this->render('item/edit.html.twig', [
            'item' => $item,
            'attributesForm' => $attributesForm->createView(),
            'assetRequestForm' => $assetRequestForm->createView(),
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/{itemId}', name: 'item_delete', methods: ['DELETE'])]
    public function exhibitDelete(Request $request, Item $item): Response
    {
        $collection = $item->getCollection();
        if ($this->isCsrfTokenValid('delete' . $item->getId(), $request->request->get('_token'))) {
            $entityManager = $this->entityManager;
            $entityManager->remove($item);
            $entityManager->flush();
        }
        return $this->redirectToRoute('collection_show', $collection->getRP());
    }
}

