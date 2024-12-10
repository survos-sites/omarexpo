<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Knp\DoctrineBehaviors\Model\Translatable\TranslatableTrait;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;
use Tchoulom\ViewCounterBundle\Model\ViewCountable;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

//use VertigoLabs\DoctrineFullTextPostgres\ORM\Mapping\TsVector;

#[ORM\Table]
//#[ORM\Index(name: 'search_idx', options: ['using' => 'gin'], columns: ['fts'])]
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
#[ApiResource(normalizationContext: ['groups' => [
    'Default', 'project.read', 'rp', 'browse', 'translation']
], denormalizationContext: ['groups' => ['Default', 'project', 'browse']])]
#[ApiFilter(SearchFilter::class, properties: [
    'code' => 'exact',
])]

//, attributes: ['order' => ['id' => 'DESC'], 'pagination_items_per_page' => 10, 'maximum_items_per_page' => 300, 'pagination_client_items_per_page' => true])]
#[Vich\Uploadable]
class Project extends SurvosBaseEntity
{


    const PLACE_NEW = 'new';
    const PLACE_PROPERTIES_ADDED = 'properties_added';
    const PLACE_LOCATIONS_ADDED = 'locations_added';
    const PLACE_COLLECTIONS_ADDED = 'collections_added';

    const TRANSITION_ADD_PROPERTIES = 'add_properties';
    const TRANSITION_ADD_LOCATIONS = 'add_locations';
    const TRANSITION_ADD_COLLECTIONS = 'add_collections';
    const TRANSITION_CONFIGURE_COLLECTIONS = 'configure_collections';

    const RELATED_CLASSES = [
        Item::class,
    ];
    const VISIBILITY_PRIVATE = 'private'; // login required
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_UNLISTED = 'unlisted';

    const ICON = 'fal fa-inventory';
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['project.read'])]
    #[ApiProperty(identifier: false)]
    private $id;

    #[ORM\Column(type: 'string', length: 32)]
//    #[Gedmo\Slug(fields: ['name'], updatable: false)]
    #[Groups(['project.read', 'item.read'])]
    #[Assert\NotBlank()]
    #[Assert\Length(min: 4, max: 32)]
    #[Assert\Regex(pattern: "/^[a-z0-9\-]+$/",
        htmlPattern: "^[a-z0-9\-]+$",
        message: "lowercase letters, hyphens or numbers only")]
    #[ApiProperty(identifier: true)]
    private $code;

    #[ORM\OneToMany(targetEntity: Item::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['export'])]
    private $items;


    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): void
    {
        $this->imageName = $imageName;
    }

    public function getImageSize(): ?int
    {
        return $this->imageSize;
    }

    public function setImageSize(?int $imageSize): void
    {
        $this->imageSize = $imageSize;
    }

    #[ORM\Column(nullable: true)]
    #[Groups('project.read')]
    private ?int $imageSize = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('project.read')]
    private ?string $imageName = null;

    // NOTE: This is not a mapped field of entity metadata, just a simple property, but maps to the two fields above
    #[Vich\UploadableField(mapping: 'project', fileNameProperty: 'imageName', size: 'imageSize')]
    private ?File $homePageImage = null;

    /**
     * @return Item[]
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    #[Groups(['project.read'])]
    public function getItemCount(): int
    {
        return $this->getItems()->count();
    }

    /**
     * @param mixed $items
     * @return Project
     */
    public function setItems($items)
    {
        $this->items = $items;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['project.read'])]
    private $name;

    //    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    //    private ?string $marking = self::PLACE_NEW; // self::INITIAL_MARKING;
    #[ORM\Column(type: 'text', nullable: true)]
    #[Gedmo\Versioned]
    #[Groups(['project.read'])]
    private $description;

    /**
     * @ var \VertigoLabs\DoctrineFullTextPostgres\DBAL\Types\TsVector
     * @ TsVector(name="fts", fields={"name", "description"})
     * Also see: https://blog.lateral.io/2015/05/full-text-search-in-milliseconds-with-postgresql/
     */
    private $full_text_search;


    #[ORM\Column(type: 'string', length: 16)]
    private $visibility = self::VISIBILITY_PRIVATE;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $city;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $address;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $localImportRootDir;

    private $roomIdFilter = [];


    #[ORM\Column(type: 'integer', nullable: true)]
    private $roomCount;

    #[ORM\Column(type: 'json', nullable: true)]
    private $formData = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleSheetsId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $sheetDbId = null;

    #[ORM\Column(nullable: true)]
    private ?int $views = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $flickrAlbumId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $flickrUsername = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $locale = null;

//    #[ORM\Column(length: 255, nullable: true)]
//    #[Assert\Image(
//        minWidth: 200,
//        maxWidth: 400,
//        minHeight: 200,
//        maxHeight: 400,
//    )]
//    protected ?string $homePageImage=null;
//    private ?string $homePageImage = null;


    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description): void
    {
        $this->description = $description;
    }

    public function clearRooms(): void
    {
        foreach ($this->getCollections() as $room) {
            $this->removeCollection($room);
        }
    }

    /** hack -- quick set, probematic if persisting  */
    public function setCollections($collections): void
    {
        $this->collections = $collections;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        if (empty($this->code)) {
            $slugger = new AsciiSlugger();
            $this->setCode($slugger->slug($name));
        }

        return $this;
    }

    public function getMarking(): ?string
    {
        return $this->marking;
    }

    public function setMarking(?string $marking): self
    {
        $this->marking = $marking;

        return $this;
    }

    public function getCurrentPlace()
    {
        return $this->getMarking();
    }

    public function __toString()
    {
        return $this->getName();
    }

    // for acmi
    public function getBaseUrl()
    {
        return '/acmi';
    }

    public function version_number()
    {
        return 1;
    }

    public function logo()
    {
        return '#';
    }

    public function title()
    {
        return $this->getName();
    }

    public function menu_heading()
    {
        return 'MENU HEADING';
    }

    public function audio_path()
    {
        return '/..';
    }

    public function getExhibitsWithAudio(): array
    {
        $exhibits = [];
        foreach ($this->getCollections() as $room) {
            /** @var Item $exhibit */
            foreach ($room->getItems() as $exhibit) {
                if ($exhibit->getAudios()) {
                    $exhibits[] = $exhibit;
                    $exhibit->setFilename($exhibit->getAudios()[0]->getFilePath()); // hack!
                }
            }
        }
        return $exhibits;
    }


    public function getUniqueIdentifiers(): array
    {
        return ['projectId' => $this->getCode()];
        // return ['projectSlug' => $this->getCode()];
    }
    const UNIQUE_PARAMETERS=['projectId' => 'code'];

    /**
     * @return mixed
     */
    public function getMembers()
    {
        return $this->members;
    }

    /**
     * @param mixed $members
     * @return Project
     */
    public function setMembers($members)
    {
        $this->members = $members;
        return $this;
    }

    public function addMember(Member $member): self
    {
        if (!$this->members->contains($member)) {
            $this->members[] = $member;
            $member->setProject($this);
        }

        return $this;
    }

    public function removeMember(Member $member): self
    {
        if ($this->members->contains($member)) {
            $this->members->removeElement($member);
            // set the owning side to null (unless already changed)
            if ($member->getProject() === $this) {
                $member->setProject(null);
            }
        }

        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getLocalImportRootDir(): ?string
    {
        return $this->localImportRootDir;
    }

    public function setLocalImportRootDir(?string $localImportRootDir): self
    {
        $this->localImportRootDir = $localImportRootDir;

        return $this;
    }

    public function getRoomIdFilter(): ?array
    {
        return $this->roomIdFilter;
    }

    public function setRoomIdFilter(?array $roomIdFilter): self
    {
        $this->roomIdFilter = $roomIdFilter;

        return $this;
    }

    public function getRoomCount(): ?int
    {
        return $this->roomCount;
    }

    public function setRoomCount(?int $roomCount): self
    {
        $this->roomCount = $roomCount;

        return $this;
    }

    /**
     * @return Collection|Tour[]
     */
    public function getTours(): Collection
    {
        return $this->tours;
    }

    public function addTour(Tour $tour): self
    {
        if (!$this->tours->contains($tour)) {
            $this->tours[] = $tour;
            $tour->setProject($this);
        }

        return $this;
    }

    public function removeTour(Tour $tour): self
    {
        if ($this->tours->contains($tour)) {
            $this->tours->removeElement($tour);
            // set the owning side to null (unless already changed)
            if ($tour->getProject() === $this) {
                $tour->setProject(null);
            }
        }

        return $this;
    }


    public function useMzFormat(): bool
    {
        return $this->getCode() == 'mz'; // hack, could be saved or configured.
    }

    /**
     * @return Collection|Asset[]
     */
    public function getAssets(): Collection
    {
        return $this->assets;
    }

    public function addAsset(Asset $asset): self
    {
        if (!$this->assets->contains($asset)) {
            $this->assets[] = $asset;
            $asset->setProject($this);
        }

        return $this;
    }

    public function removeAsset(Asset $asset): self
    {
        if ($this->assets->contains($asset)) {
            $this->assets->removeElement($asset);
            // set the owning side to null (unless already changed)
            if ($asset->getProject() === $this) {
                $asset->setProject(null);
            }
        }

        return $this;
    }

    public function getFormData(): ?array
    {
        return $this->formData;
    }

    public function getFormDataJson()
    {
        return json_encode($this->getFormData(), JSON_PRETTY_PRINT + JSON_UNESCAPED_LINE_TERMINATORS + JSON_UNESCAPED_SLASHES);
    }

    public function setFormDataJson($jsonString)
    {
        return $this->setFormData(json_decode($jsonString));
    }

    public function setFormData(?array $formData): self
    {
        $this->formData = $formData;

        return $this;
    }

    /**
     * @return Collection|Property[]
     */
    public function getProperties(): Collection
    {
        // return $this->serializer->deserialize($this->getFormData());

        return $this->properties;
    }

    public function getPropertyByCode($code): ?Property
    {
        $property = $this->getProperties()->filter(function (Property $property) use ($code) {
            return $property->getCode() == $code;
        })->first();

        return $property ? $property : null;
    }

    public function addProperty(Property $property): self
    {
        if (!$this->properties->contains($property)) {
            $this->properties[] = $property;
            $property->setProject($this);
        }

        return $this;
    }

    public function removeProperty(Property $property): self
    {
        if ($this->properties->contains($property)) {
            $this->properties->removeElement($property);
            // set the owning side to null (unless already changed)
            if ($property->getProject() === $this) {
                $property->setProject(null);
            }
        }

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getHomePageImage(): ?File
    {
        return $this->homePageImage;
    }

    public function setHomePageImage(?File $homePageImage): self
    {
        $this->homePageImage = $homePageImage;

        if (null !== $homePageImage) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getGoogleSheetsId(): ?string
    {
        return $this->googleSheetsId;
    }

    public function getGoogleSheetsUrl(): string
    {
        return sprintf('https://docs.google.com/spreadsheets/d/%s', $this->getGoogleSheetsId());

    }

    public function setGoogleSheetsId(?string $googleSheetsId): static
    {
        // https://docs.google.com/spreadsheets/d/1OSn0nMK8h7xZkZsT4OAPp_zhm-YOyIE6/edit#gid=192718484
        if (preg_match('|d/(.*?)/edit|', $googleSheetsId, $matches)) {
            $googleSheetsId = $matches[1];
        }
        $this->googleSheetsId = $googleSheetsId;

        return $this;
    }

    public function getSheetDbId(): ?string
    {
        return $this->sheetDbId;
    }

    public function setSheetDbId(?string $sheetDbId): static
    {
        $this->sheetDbId = $sheetDbId;

        return $this;
    }

    public function getViews(): ?int
    {
        return $this->views;
    }

    public function setViews($views)
    {
        $this->views = $views;

        return $this;
    }

    /**
     * @return Collection<int, ViewCounter>
     */
    public function getViewCounters(): Collection
    {
        return $this->viewCounters;
    }

    public function addViewCounter(ViewCounter $viewCounter): static
    {
        if (!$this->viewCounters->contains($viewCounter)) {
            $this->viewCounters->add($viewCounter);
            $viewCounter->setProject($this);
        }

        return $this;
    }

    public function removeViewCounter(ViewCounter $viewCounter): static
    {
        if ($this->viewCounters->removeElement($viewCounter)) {
            // set the owning side to null (unless already changed)
            if ($viewCounter->getProject() === $this) {
                $viewCounter->setProject(null);
            }
        }

        return $this;
    }

    public function getFlickrAlbumId(): ?string
    {
        return $this->flickrAlbumId;
    }

    public function setFlickrAlbumId(?string $flickrAlbumId): static
    {
        $this->flickrAlbumId = $flickrAlbumId;

        return $this;
    }

    public function setFlickrAlbumFromUrl(?string $flickrAlbumUrl): static
    {
        // https://www.flickr.com/photos/cheztac/albums/72177720317424964/
        if ($flickrAlbumUrl && preg_match('|/photos/(.+?)/albums/(.*?)$|', $flickrAlbumUrl, $matches)) {
            $this->setFlickrUsername($matches[1]);
            $this->setFlickrAlbumId($matches[2]);
        } else {
            assert(false, $flickrAlbumUrl);
            //
        }
        return $this;
    }

    public function getFlickrUsername(): ?string
    {
        return $this->flickrUsername;
    }

    public function setFlickrUsername(?string $flickrUsername): static
    {
        $this->flickrUsername = $flickrUsername;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

}
