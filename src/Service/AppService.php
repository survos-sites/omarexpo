<?php


namespace App\Service;


use App\Entity\Asset;
use App\Entity\FormElement;
use App\Entity\Item;
use App\Entity\ItemCollection;
use App\Entity\Location;
use App\Entity\Member;
use App\Entity\Project;
use App\Entity\Property;
use App\Entity\User;
use App\Message\Resize;
use App\Repository\ItemCollectionRepository;
use App\Repository\ItemRepository;
use App\Repository\LocationRepository;
use App\Repository\ProjectRepository;
use App\Services\Symfony;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Sheets\Sheet;
use League\Csv\Reader;
use League\Csv\Writer;
use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Mailbox;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use function Symfony\Component\String\u;


class AppService
{
    const CODE_COLUMN = 'CODE';
    const LABEL_COLUMN = 'LABEL';
    const DESCRIPTION_COLUMN = 'DESCRIPTION';
    const COLOR_COLUMN = 'COLOR';
    const SIZE_COLUMN = 'SIZE';
    const POSITION_COLUMN = 'POS';
    const HEIGHT_COLUMN = '_H';
    const WIDTH_COLUMN = '_W';
    const STATUS_COLUMN = 'STATUS';

    const REPEATABLE_COLUMNS = [self::SIZE_COLUMN, self::COLOR_COLUMN, self::HEIGHT_COLUMN];

    private $project;

    public function __construct(private EntityManagerInterface                          $em,
                                private ProjectRepository                               $projectRepository,
                                private ItemRepository                                  $itemRepository,
                                private FormFactoryInterface                            $formFactory,
                                private LoggerInterface                                 $logger,
                                private SerializerInterface                             $serializer,
                                private NormalizerInterface                             $normalizer,
                                private DenormalizerInterface                           $denormalizer,
                                private MailerInterface                                 $mailer,
                                #[Autowire('%kernel.project_dir%/public/omar')] private string $dataDir,
                                private CacheManager                                    $imagineCacheManager,
                                private SluggerInterface                                $asciiSlugger,

                                private FilesystemOperator                              $defaultStorage,
                                #[Autowire('%local_uri_prefix%')] private string        $localUriPrefix,
                                private MessageBusInterface                             $bus,
                                private PropertyAccessorInterface                       $accessor,
    )
    {
    }



    // for collections, not items
    static public function createShortCode($string): ?string
    {
        // if there's a number, strip it out, but we'll import files and directories in order.
        // @todo: check for >1 uc words
        return $string ? u($string)->ascii()->title()->slice(0, 3) : null;

    }

    public function getFormDataFromProperties(Project $project)
    {
        $elements = [];
        foreach ($project->getProperties() as $property) {
            // awkward, would be nicer if this could just be the same entity!!
            $element = (new FormElement())
                ->setName($property->getCode())
                ->setLabel($property->getName());
            switch ($propertyType = $property->getType()) {
                case Property::TYPE_STRING:
                    $element->setType(FormElement::TEXT);
                    break;
                case Property::TYPE_TEXT:
                    $element->setType(FormElement::TEXTAREA);
                    break;
                case Property::TYPE_INTEGER:
                    $element->setType(FormElement::NUMBER);
                    break;
                case Property::TYPE_CHOICE:
                    $element->setType(FormElement::RADIO);
                    break;
                case 'alias':
                    $element = false;
                    break;
                default:
                    throw new \Exception("Missing $propertyType mapping to FormElement");
            }
            if ($element) {
                array_push($elements, $element);
            }
        }
        return $elements;
    }

    public function getFormDataElements(Project $project)
    {
        $formElements = [];
        $parent = false;
        foreach ($project->getFormData() as $data) {
            /** @var FormElement $formElement */
            $formElement = $this->denormalizer->denormalize($data, FormElement::class);
            $formElement->setRawData($data);
            if ($formElement->isHeader()) {
                $parent = $formElement->getLabel();
            } else {
                if ($parent) {
                    $formElement->setParent($parent);
                }
            }

            // if this is a header, keep track of the level and make it the parent (for jsTree)
            array_push($formElements, $formElement);
        }
        return $formElements;
        $elements = $this->denormalizer->denormalize($project->getFormData(), FormElement::class);
        dd($elements, $project->getFormData());
        return $this->serializer->denormalize($project->getFormData(), FormElement::class);

    }

    public function sendNewItemEmail(ItemCollection $collection, Member $member)
    {
        $subject = sprintf("New %s Item", $collection->__toString());
        $from = str_replace('@', '+coll-' . $collection->getId() . '@', $this->exhibitEmail);
        $fromArray = new Address($from, 'Collection ' . $collection->getId());
        // dd($from, $fromArray);
        $email = (new TemplatedEmail())
            ->from($fromArray)
            ->to($member->getUser()->getEmail())
            ->replyTo($from)
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            ->priority(Email::PRIORITY_HIGH)
            ->subject($subject)
            ->htmlTemplate('email/newItem.html.twig')
            ->context([
                'member' => $member,
                'collection' => $collection
            ]);

        /** @var Symfony\Component\Mailer\SentMessage $sentEmail */
        $sentEmail = $this->mailer->send($email);
        return $sentEmail;
        // $messageId = $sentEmail->getMessageId();

        // ...
    }


    /**
     * @param Item $item
     * @param Member $member
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function sendExisitingItemEmail(Item $item, Member $member)
    {
        $subject = $item->__toString();
        $from = str_replace('@', '+item-' . $item->getId() . '@', $this->exhibitEmail);
        $fromArray = new Address($from, 'Item ' . $item->getId());
        // dd($from, $fromArray);
        $email = (new TemplatedEmail())
            ->from($fromArray)
            ->to($member->getUser()->getEmail())
            ->replyTo($from)
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject($subject)
            ->htmlTemplate('email/existingItem.html.twig')
            ->context([
                'member' => $member,
                'item' => $item
            ]);

        $sentEmail = $this->mailer->send($email);
        return $sentEmail;
        // $messageId = $sentEmail->getMessageId();

        // ...
    }

    public function findOrCreateLocation(Project $project, string $name, string $code = null): Location
    {
        if (!$code) {
            $code = $this->asciiSlugger->slug($name);
        }
        if (!$location = $this->locationRepository->findOneBy([
            'project' => $project,
            'code' => $code
        ])) {
            $location = (new Location(['project' => $project, 'code' => $code, 'name' => $name]));
            $project->addLocation($location);
        }
        return $location;

    }

    public function findOrCreateCollection(Project $project, $code, $data = [], $options = []): ItemCollection
    {
        $data = (new OptionsResolver())
            ->setDefaults([
                'name' => ucfirst($code)
            ])->resolve($data);

        if (!$collection = $this->collectionRepository->findOneBy([
            'project' => $project,
            'code' => $code
        ])) {
            $collection = (new ItemCollection(['project' => $project, 'name' => $data['name']]))
                ->setPath('/' . $code)
                ->setCode($code);
            $project->addCollection($collection);
        }
        return $collection;

    }

    // case-insentive, also removes accents
    static public function equals($a, $b)
    {

        $a = u($a)->ascii()->trim()->lower();
        $b = u($b)->ascii()->trim()->lower();
        return $a == $b;
    }

    public function createOrUpdateProject($slug, $data = [], $options = [])
    {

        $options = (new OptionsResolver())
            ->setDefaults([
                    'purge' => false]
            )->resolve($options);

        $data = (new OptionsResolver())
            ->setDefaults([
                'name' => ucfirst($slug),
                'localRootDir' => null,
                'location' => null,
                'address' => null,
                'managers' => []
            ])->resolve($data);

        $projectRepo = $this->em->getRepository(Project::class);

        // check if project already exists.
        if ($options['purge'] && $project = $projectRepo->findOneBy(['code' => $slug])) {
            foreach ($project->getCollections() as $collection) {
                $project->removeCollection($collection);
                $this->em->remove($collection);
            }
            $this->em->remove($project);
            $this->em->flush();
            $this->em->clear(); // for tree
        }

        if (!$project = $projectRepo->findOneBy(['code' => $slug])) {
            $project = (new Project())
                ->setCode($slug);
            $this->em->persist($project);
        }

        $project
            ->setCity($data['location'])
            ->setName($data['name']);

        if ($rootDir = $data['localRootDir']) {
            $project->setLocalImportRootDir($rootDir);
        }

        foreach ($data['managers'] as $managerEmail) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $managerEmail]);
            $project->addMember((new Member())->setUser($user)->setRoles(['PROJECT_MANAGER']));
        }

        return $project;
    }

    public function downloadSheetsToLocal(Project $project): array
    {
        $files = [];
        $dir = $this->dataDir . '/' . $project->getCode();
        if (!file_exists($dir)) {
            try {
                mkdir($dir, 0777, true);
            } catch (\Exception $exception) {
                throw new \Exception('Could not create directory ' . $dir . ' ' . $exception->getMessage()) ;
            }
        }


        $this->sheetService->getData($project->getGoogleSheetsId(),
            function(?array $values, Sheet $sheet)
                use (&$files, $dir, $project)
        {
            file_put_contents($files[] = $dir .  sprintf("/%s.csv", $sheet->getProperties()->getTitle()),
                $this->asCsv($values??[]));
        }
        );
        return $files;
    }

    private function writeFile()
    {

    }

    private function asCsv(array $data): string
    {
        $csv = Writer::createFromString();
        $csv->insertAll($data);
        return $csv->toString();
        foreach ($data as $idx => $record) {
//            $record = (array)$record;
            if ($idx == 0) {
                $keys = $record;
//                $keys = array_map(fn($key) => str_replace('*', '', $key), $record);
//                $csv->insertOne($keys);
//                continue;
            } else {
                foreach ($record as $columnIdx => $value) {
                    dd($keys, $record, $data);
                    if (str_ends_with($keys[$columnIdx], '!')) {
                        $record[$columnIdx] = null;
                    }
                }
            }
            $csv->insertOne($record);
        }

        return $csv->toString();
    }

    public function findProjectBySlug($slug)
    {
        return $this->projectRepository->findOneBy(['code' => $slug]);
    }

    // maybe for caching?
    public function setGlobalProject(Project $project): void
    {
        $this->project = $project;
    }

    public function getGlobalProject(): ?Project
    {
        return $this->project;
    }

    public function importEmail(Mailbox $mailbox, Project $project = null): array
    {
        $exhibits = [];
        // could add more filters, project or item specific
        $mailsIds = $mailbox->searchMailbox('UNSEEN');
        try {
        } catch (ConnectionException $ex) {
            dd("IMAP connection failed: " . $ex);
            die();
        }


        foreach ($mailsIds as $mailId) {
// If '__DIR__' was defined in the first line, it will automatically
// save all attachments to the specified directory

            $mail = $mailbox->getMail($mailId);

            if (!$mail->hasAttachments()) {
                $mailbox->markMailAsRead($mailId);
                continue;
            }

            $item = null;

            // Subject could contain ID, too, but maybe better just the 'from'
            $from = $mail->fromAddress;
            $subject = $mail->subject;
            $to = $mail->to;

            // if it's an existing item.
            if (preg_match('/\+item-(\d+)/', $mail->toString, $m)) {
                $id = $m[1];
                if (!$item = $this->itemRepository->find($id)) {
                    $msg = ("Item $id not found");
                    $this->logger->error($msg);
                    continue;
                }
                // if it's a new item.
            } elseif (preg_match($regex = '/\+coll-(\d+)/', $mail->toString, $m)) {
                $id = $m[1];
                if (!$collection = $this->collectionRepository->find($id)) {
                    $msg = ("Collection $id not found");
                    // the collection should set the default location.
                    $this->logger->error($msg);
                    continue;
                } else {
                    $item = (new Item($collection));
                }
            } else {
                // mark as error?  Maybe spam?
                $this->logger->error("Invalid To Address", [
                    'regex' => $regex,
                    'to' => $to,
                    'toString' => $mail->toString,
                    'subject' => $subject,
                    'from' => $from
                ]);
            }

            // Print all information of $mail

// Print all attachements of $mail
            foreach ($mail->getAttachments() as $attachment) {
                $file = new File($attachment->filePath);
                // this has moved!  Dispatch an event or move to another service
//                $asset = $this->uploaderHelper->uploadItemAsset($file, $item, true, true);

                $asset
                    ->setName($attachment->name);
                // dd($exhibit, $exhibit->getMedias());
            }
            array_push($exhibits, $item);

            // if we've made it here, mark as read
            $mailbox->markMailAsRead($mailId);
        }
        return $exhibits;

    }


    protected function createForm(string $type, $data = null, array $options = []): FormInterface
    {
        return $this->formFactory->create($type, $data, $options);
    }

    public function import(Project $project, bool $refresh = false)
    {
        if ($refresh) {
            $filesWritten = $this->downloadSheetsToLocal($project);
        }

        $csv = Reader::createFromPath($this->dataDir . '/omar.csv', 'r');
        $csv->setHeaderOffset(0);

        $header = $csv->getHeader(); //returns the CSV header record
//returns all the records as
        $records = $csv->getRecords(); // an Iterator object containing arrays
        foreach ($records as $record) {
            $item = $this->importItem($record, $project);
            // now get the images
            $finder = new Finder();
            foreach ($finder->files()->in($this->dataDir . '/' . $item->getCode()) as $file) {
                $filename = $file->getFilename();
                if (preg_match('/AUDIO/', $filename)) {
                    $item->setAudio($filename);
                } elseif (preg_match('/VIDEO/', $filename)) {
                    $item->setVideo($filename);
                } else {
                    if (in_array($file->getExtension(), ['JPG', 'JPEG'])) {
                        $item->setImage($filename);
                    }
                }
            }
        }
        $this->em->flush();
        return;

        dd($dir);
        $accessor = new PropertyAccessor();
        {
            $csvFiles = glob($dir . '/*.csv');
            sort($csvFiles);
            $coll = [];
            $locs = [];
            foreach ($csvFiles as $csvFilename) {
                $basename = basename($csvFilename, '.csv');
                assert(file_exists($csvFilename), $csvFilename);
                if (!file_exists($csvFilename)) {
                    $this->logger->warning($csvFilename . " does not exist");
                    return;
                }

                // handle relations first
                $reader = Reader::createFromPath($csvFilename, 'r');
                try {
                    $reader->setHeaderOffset(0); //set the CSV header offset
                    if (!$reader->count()) {
                        return;
                    }
                    $records = $reader->getRecords();
                } catch (\Exception $e) {
                    // probably an empty file
                    $this->logger->warning($e->getMessage());
                    continue;
                }
                $basename = basename($csvFilename, '.csv');
                assert(file_exists($csvFilename), $csvFilename);
                switch ($basename) {
                    case '@info':
                        dd($records);
                        break;
                    case '@col':
                        foreach ($records as $idx => $row) {
                            assert(array_key_exists(self::CODE_COLUMN, $row), $csvFilename . ' missing CODE');

                            $coll[$row[self::CODE_COLUMN]] = $row;
                        }
                        break;
                    case '@loc':
                        $prevRow = [];
                        foreach ($records as $idx => $row) {
                            // only for keys that allow repeat (or template, eventually)
                            $useValuesFromPreviousRow = [];
                            foreach ($prevRow as $var => $val) {
                                if (in_array($var, self::REPEATABLE_COLUMNS)) {
                                    $useValuesFromPreviousRow[$var] = $val;
                                }
                            }
                            $row = array_merge($useValuesFromPreviousRow, $row);

                            assert(array_key_exists(self::CODE_COLUMN, $row), "Missing code in @loc.csv");
                            assert(array_key_exists(self::LABEL_COLUMN, $row), "Missing LABEL_COLUMN in @loc.csv " . $project->getGoogleSheetsUrl());

                            $loc = $this->findOrCreateLocation($project, $row[self::CODE_COLUMN]);
                            $loc->setLabel($row[self::LABEL_COLUMN]);
                            $loc->setMarking($row[self::STATUS_COLUMN]??Location::MARKING_NEW);
                            $loc->setDimensions($row);

                            $loc->setType($row['type']??'WALL');
                            $loc->setBackgroundColor($row[AppService::COLOR_COLUMN]??null);
                            $locs[$loc->getCode()] = $loc;
                            $prevRow = $row;

//                            dump(withDefaults: $prevRow, row: $row);
                        }
                        break;
                }
                if (str_starts_with($basename, '@')) {
                    continue;
                }

                $collection = $this->findOrCreateCollection($project, $basename);
                // set the default location
                if ($collData = $coll[$collection->getCode()]?? false) {
                    if ($collData['#loc']??false) {
                        $collection->setDefaultLocation($locs[$collData['#loc']] ?? null);
                    } else {
                        // set to first? Prompt?

                    }
                }

                foreach ($records as $idx => $record) {

                    // @todo: handle meta sheets, like _REF, @p, @F
                    // check that the csv exists
                    if (!array_key_exists(self::CODE_COLUMN, $record)) {
                        throw new \Exception(sprintf("Missing %s in $csvFilename", self::CODE_COLUMN));
                    }
//            $code = trim(str_replace(' ', '', $record['code']));
                    $code = $this->asciiSlugger->slug($record[self::CODE_COLUMN])->toString();

                    // project,collection,location,item all have code/label/description
                    switch ($basename) {
                        default:
                            $item = $this->importItem($record, $code, $collection, $coll, $locs);
                        // import to Item
                    }

                    $this->handleAssets($record, $project, $item);

                }
                $this->em->flush();

            }
        }
    }

    private function importItem(array $record, Project $project): Item
    {
        $code = $record[self::CODE_COLUMN];
        $locale = 'es';
        if (!$item = $this->itemRepository->findOneBy(['code' => $code])) {
            $item = (new Item($code))
                ->setShortCode($code)
                ->setCode($code);
            $item->setProject($project);
            $this->em->persist($item);

        }
        $item
            ->setLabel($record[self::LABEL_COLUMN . ".{$locale}*"])
            ->setDescription($record['tecnica']);
        return $item;
        // @todo: tag size
        $item->setDimensions($record);
        $item->setPosition($record[self::POSITION_COLUMN]??null);
        $item->setAttributes($record);
        $item->setLocale($item->getProject()->getLocale());

        $item->setTitle($record[self::LABEL_COLUMN] ?? $item->getCode())
            ->setShortCode($item->getCode());
        $item->setDefaultLocale($collection->getDefaultLocale());
        $item
            ->setLabel($item->getTitle(), $locale)
            ->setDescription($record['description*'] ?? null, $locale);

// handle translations for all translatable entities
        foreach ($record as $var => $value) {
            if (preg_match('/(' . self::LABEL_COLUMN .'|' . self::DESCRIPTION_COLUMN . ')\.(.*?)\*/', $var, $m)) {
                [$ignore, $field, $lang] = $m;
                $translationEntity = $item->getTranslationEntity($lang);
                $this->accessor->setValue($translationEntity, $field, $value);
//                    dump($var, $value, $lang, $field, $translationEntity->getLabel());
            }
        }

        foreach ($item->getTranslations() as $translation) {
//                dump($translation->getLocale() . ':' . $translation->getLabel());
        }

        $project = $collection->getProject();
        if ($locCode = $record['#loc'] ?? false) {
            if (str_contains($locCode, ':')) {
                [$locCode, $orderIdx] = explode(':', $locCode);
            } else {
                $orderIdx = null; // or make it the database order?
            }
            $item->setOrderIdx($orderIdx);
            if (!$location = $locLookup[$locCode] ?? null) {
                $location = $this->findOrCreateLocation($project, $locCode);
            }
        } else {
            $collCode = $collection->getCode();
            assert($coll = $collLookup[$collCode]??null, "Missing $collCode in collLookup: " . join("\n", array_keys($collLookup)));
            if ( ($coll['#loc']??false) && !$location = $locLookup[$coll['#loc']] ?? null) {
//                throw new \Exception('if #loc is not defined, you must create a default unsorted location');
            }
        }
        // within the location
        if (isset($location)) {
            $location->addItem($item);
        }
        return $item;

//            foreach (['audio'] as $position => $type) {
    }


    public function setPaths(Asset $asset, array $filters = ['tiny'])
    {
        $filesystem = $this->defaultStorage;
        $target = $asset->getFilename();
//        if (!$asset->getUrl()) {
        try {
            $url = $filesystem->publicUrl($target);
        } catch (\Exception $exception) {
            // local doesn't have a generator, hack in the prefix that must match the flysystem local config
            $url = $this->localUriPrefix . $asset->getFilename();
        }
        if ($asset->isAudio()) {
//                dump($url);
        }
        $asset->setUrl($url); // to the source image
//        }

        if (!$asset->isImage()) {
            return;
        }

        $thumbnails = $asset->getThumbnails() ?? [];
        foreach ($filters as $filter) {
            if (array_key_exists($filter, $thumbnails)) {
//                continue; // skip if we already have this.
            }
            $resolvedPath = $this->imagineCacheManager->resolve($target, $filter);
            if (str_contains($resolvedPath, 'cache')) {
                $thumbnails[$filter] = parse_url($resolvedPath, PHP_URL_PATH);
                $asset->setThumbnails($thumbnails);
//                $this->em->flush(); //
            } else {
                $this->em->flush(); // so the id is set for the message handler
                $this->bus->dispatch((new Resize($asset->getId(), $filter)));
            }
        }

    }

    public
    function getRelatedClassesSummary($classList, Project $project, Request $request)
    {
        $summary = [];
        foreach ($classList as $class) {
            $entityShortName = u((new \ReflectionClass($class))->getShortName())
                ->lower()->replace('itemcol', 'col');

            $entity = (new $class(['project' => $project]));
            $formClass = u($class)->replace('Entity', 'Form') . 'Type';

            $form = $this->createForm($formClass,
                $entity,
            // ['first_step' => true]
            );
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                // redirect to real new form
                $entityManager = $this->entityManager;
            }
            $summary[$class] = [
                'form' => $form->createView(),
                'entity' => $entityShortName,
                'count' => count($this->em->getRepository($class)->findBy(['project' => $project])),
                'icon' => constant($class . '::ICON')
            ];
        }
        return $summary;

    }

    /**
     * @param array $record
     * @param Project|null $project
     * @param Item|null $item
     * @param $key
     * @param $asset
     * @param $idx
     * @return void
     * @throws \Exception
     */

    // @todo: AssetInterface
    public function handleAssets(array $record, ?Project $project, Item|Location $item): void
    {
        $assetPath = sprintf('%s/%s/%s', $this->dataDir, $project->getCode(), $item->getCode());
        $finder = (new Finder())->in($assetPath);
        foreach ($finder->getIterator() as $file) {
            if ($file->isDir()) {
                continue;
            }
            $type = match (strtolower($file->getExtension())) {
                'png' => Asset::ASSET_IMAGE,
                'jpg' => Asset::ASSET_IMAGE,
                'mp3' => Asset::ASSET_AUDIO,
                'csv' => null,
                default => assert(false, "missing " . $file->getRealPath() . " file"),
            };
            if (!$type) {
                continue;
            }
            // flickr? sais?  Probably not here.
//            if ($asset = $this->uploaderHelper->uploadItemAsset($file, $item)) {
//
//            }

//            dd($file, $file->getType());
//            dd($file->getPathname());
        }
        return;
        dd($item->getCode());

//        foreach (['audio', 'image'] as $position => $type) {
        foreach (['audio'] as $position => $type) {
//        foreach (['image'] as $position => $type) {
            // the LOCAL path for assets to upload
            // if we already have it, just load the s3.
            $existing = $record[$type . '.s3*'] ?? null;
            if ($existing) {
//                dd(pathinfo($assetPath));
                if (!file_exists($assetPath)) {
                    mkdir($assetPath, recursive: true);
                }

                $fileName = $record['image*']??pathinfo($existing, PATHINFO_BASENAME);
                $downloadedFilename =  $assetPath . '/' . $fileName;

                if (!file_exists($downloadedFilename)) {
                    file_put_contents($downloadedFilename, file_get_contents($record[$type . '.s3*']));
                }
                $record[$type . '*'] = $fileName;
//                dd($record, $fileName, $existing, $downloadedFilename);
                // download it to local?  Just add it to asset?
                // unfortunately, it goes through uploadHelper
//                $this->uploadAsset();
//                continue;
            }
            if ($fileName = $record[$type . '*'] ?? false) {
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                // hack for los altos
                if ($ext === 'wmf') {
                    // extracted in SpreadsheetService::loadExcelImages
                    $fullImagePath = '/home/tac/ca/data/raw/x/images/xl/media/' . str_replace('wmf', 'png', $fileName);
                    if (!file_exists($fullImagePath)) {
                        continue;
                    }
                    assert(file_exists($fullImagePath), $fullImagePath);
                    // if a url, fetch and save as md5 in the local path for uploading
                } elseif (filter_var($fileName, FILTER_VALIDATE_URL)) {
//                    dd($assetPath, $fileName);
                    if (!file_exists($assetPath)) {
                        mkdir($assetPath, recursive: true);
                    }
                    $md5 = md5($fileName);
                    $fullImagePath = sprintf("%s/%s.%s", $assetPath, $md5, $ext

                    );
                    if (!file_exists($fullImagePath)) {
                        file_put_contents($fullImagePath, file_get_contents($fileName));
                    }
                } else {
                    $fullImagePath = sprintf("%s/%s/%s/%s",
                        $this->dataDir, $project->getCode(), $item->getCode(), $fileName);

                }

                if (!file_exists($fullImagePath)) {
                    $this->logger->error("Missing $fullImagePath");
//                        assert(false, $fullImagePath);
                    continue;
                }
                $file = new File($fullImagePath);
                if ($item::class === Location::class) {
                    continue;
                }
                if ($asset = $this->uploaderHelper->uploadItemAsset($file, $item, true)) {
//                        $asset->setUrl($asset->getS3Url($this->s3BucketName));
                    $asset->setPosition($position);
                    $this->em->persist($asset);
//                        dd($asset->getFilename(), $fullImagePath);
                    $this->setPaths($asset);
//                    dd($asset->getFilename(), $asset->getThumbnails(), $asset->getUrl());
//                        $this->logger->warning($msg = sprintf('@todo: update %s.s3 in %s to %s', $asset->getType(), $project->getGoogleSheetsUrl(), $asset->getUrl()));

                    $key = $type . '.s3*';
                    $this->logger->warning('NOT updated to ' . $asset->getUrl());
                    if (false)
                        if (array_key_exists($key, $record) && !$record['image.s3*']) {
                            $keyIdx = array_search($key, array_keys($record));
                            $colByLetter = range('A', 'Z')[$keyIdx];

                            $values = array_values([$asset->getUrl()]);
                            $this->sheetService->updateCell($project->getGoogleSheetsId(),
                                $cell = $colByLetter . $idx + 1, $values);
                            $this->logger->warning($cell . ' updated to ' . $asset->getUrl());
                        }
                    dd($type, $asset->getFilePath());
                     $asset->isAudio() && dd($asset, 'asset', $asset->getAltText(), $asset->getUri());
                    // dd($asset->getUri());
                }
                $this->em->flush();
                $this->setPaths($asset, ['tiny', 'medium']);

                // in this case, we have the asset locally, we don't need to scrape it.


            }
        }
    }

    public function uploadAsset()
    {
        if ($asset = $this->uploaderHelper->uploadItemAsset($file, $item, true, existing: $existing)) {
//                        $asset->setUrl($asset->getS3Url($this->s3BucketName));
            $asset->setPosition($position);
            $this->em->persist($asset);
//                        dd($asset->getFilename(), $fullImagePath);
            $this->setPaths($asset);
//                        $this->logger->warning($msg = sprintf('@todo: update %s.s3 in %s to %s', $asset->getType(), $project->getGoogleSheetsUrl(), $asset->getUrl()));

            $key = $type . '.s3*';
            $this->logger->warning('NOT updated to ' . $asset->getUrl());
            if (false)
                if (array_key_exists($key, $record) && !$record['image.s3*']) {
                    $keyIdx = array_search($key, array_keys($record));
                    $colByLetter = range('A', 'Z')[$keyIdx];

                    $values = array_values([$asset->getUrl()]);
                    $this->sheetService->updateCell($project->getGoogleSheetsId(),
                        $cell = $colByLetter . $idx + 1, $values);
                    $this->logger->warning($cell . ' updated to ' . $asset->getUrl());
                }
            // dd($asset, 'asset', $asset->getAltText(), $asset->getUri());
            // dd($asset->getUri());
        }
        $this->em->flush();
        $this->setPaths($asset, ['tiny', 'medium']);

    }


}
