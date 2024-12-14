<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use function Symfony\Component\String\u;

/**
 * @ ORM\Table(indexes={@ORM\Index(name="item_fts_idx", options={"using": "gin"}, columns={"title_fts"})})
use VertigoLabs\DoctrineFullTextPostgres\ORM\Mapping\TsVector;
 *
 */
#[ApiResource(
    normalizationContext: ['groups' => ['item.read','rp']],
    denormalizationContext: ['groups' => ['item.write','shape']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'project' => 'exact',
    'code' => 'exact',
])]

#[ORM\Entity(repositoryClass: ItemRepository::class)]
class Item implements RouteParametersInterface, \Stringable
{
    use RouteParametersTrait;
    use AttributesTrait;
    const UNIQUE_PARAMETERS=['itemId' => 'code'];

    public function getUniqueIdentifiers(): array
    {
        //
        return $this->getProject()->getRp([ 'itemId' => $this->getCode()??'bad-code']);
    }

        // icon: fas fa-vector-square # could also be a qr-code

    const ICON = 'fad fa-barcode';
    const PLACE_NEW = 'new';
    const PLACE_MAINTENANCE = 'maintenance';
    const PLACE_LIVE = 'live';
    const PLACE_PUBLISHED = 'published';
    const PLACE_PREVIEW = 'preview';
    const PLACE_REGISTERED = 'registered';
    const PLACE_PENDING = 'pending';
    const PLACE_WAITING = 'waiting';

    const TRANSITION_MAINTAIN = 'maintain';
    const TRANSITION_TO_LIVE = 'make_live';
    const TRANSITION_PREVIEW = 'preview';
    const TRANSITION_REGISTER = 'register';
    const TRANSITION_ANNOTATE = 'annotate';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['item.read'])]
    private $id;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['item.read'])]
    protected $attributes = [];

    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'items', cascade: ['persist'])]
    #[Groups(['item.read'])]
    private $project;

    /**
     * @return mixed
     */
    public function getProject()
    {
        return $this->project;
    }

    #[Groups(['item.read'])]
    public function getProjectId()
    {
        return $this->getProject()->getId();
    }

    #[Groups(['item.read'])]
    public function getProjectCode()
    {
        return $this->getProject()->getCode();
    }

    /**
     * @param mixed $project
     * @return Item
     */
    public function setProject($project)
    {
        $this->project = $project;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $filename;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item.read'])]
    private $transcript;

    /**
     * @  Gedmo\Slug(fields={"title"})
     */
    #[ORM\Column(type: 'string', length: 64, nullable: false)]
    #[Groups(['item.read'])]
    #[Assert\NotBlank()]
    private $code;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $relativePath;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['item.read'])]
    private $title;

    /**
     * @var \VertigoLabs\DoctrineFullTextPostgres\DBAL\Types\TsVector
     * @ TsVector(name="title_fts", fields={"title"})
     */
    private $titleFTS;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item.read'])]
    private $description;

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

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['item.read'])]
    private $duration;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private $status;

    #[ORM\Column(type: 'string', length: 4, nullable: false)]
    private $localCode='';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $sectionTitle;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['item.read'])]
    private $orderIdx;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private $stopId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $endRow;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $startRow;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $sourceFilename;


    #[ORM\Column(type: 'string', length: 9, nullable: true)]
    #[Groups(['item.read','item.write'])]
    private string $visibility;

    #[ORM\Column(type: 'string', length: 16)]
    private $shortCode;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $assetRelativePath;

    #[ORM\Column(nullable: true)]
    private ?int $views = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['item.read'])]
    private ?string $audio = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['item.read'])]
    private ?string $video = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['item.read'])]
    private ?string $image = null;

    #[ORM\Column(length: 255)]
    #[Groups(['item.read'])]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['item.read'])]
    private ?string $size = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['item.read'])]
    private ?int $year = null;

    #[ORM\Column(nullable: true)]
    private ?array $imageUrls = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $audioUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['item.read'])]
    private ?string $youtubeUrl = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['item.read'])]
    private ?int $price = null;


    public function __construct(string $code)
    {
        $this->setProject($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getTranscript(): ?string
    {
        return $this->transcript;
    }

    public function setTranscript(?string $transcript): self
    {
        $this->transcript = $transcript;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getFirstLine(): string
    {
        return explode("\n", $this->getTranscript())[0];

    }

    public function setAttributes(?array $attributes): self
    {
        foreach ($attributes as $attribute=>$value) {
            if (empty($attribute)) {
                continue;
            }
            if ($value) {
                $value = trim($value);
            }
            if (!mb_detect_encoding($attribute)) {
                dd($attribute, 'bad encoding!' );
            }
            // hacks to clean up, should probably be done elsewhere.

            if ($value && mb_detect_encoding($value) === false) {
                // skip it.
                $value = 'Invalid Value';
            }

            /*
            if ($attribute == 'usd') {
                dump($value, mb_detect_encoding($value));
                $value = str_replace('¬', '', $value);
                $value = u($value)->ascii()->trimEnd('‚¬. ')->toString();
            }
            */

            if (!str_ends_with($attribute, '!')) {
                $this->setAttribute($attribute, $value);
            } else {
                unset($attributes[$attribute]);
            }
        }
        $this->attributes = $attributes;

        return $this;
    }

    public function getAttribute($attribute, $throwError = false)
    {
        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        } else {
            if ($throwError) {
                throw new \Exception("Missing attribute $attribute for item");
            } else {
                return null;
            }
        }
    }

    public function getRelativePath(): ?string
    {
        return $this->relativePath;
    }

    public function setRelativePath(?string $relativePath): self
    {
        $this->relativePath = $relativePath;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        // b($title)->isUtf8() ? $title : b($title)->toUnicodeString()->ascii()->title());
        $this->title = u($title)->slice(0, 63);

        return $this;
    }


    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getLocalCode(): ?string
    {
        return $this->localCode;
    }

    public function getInventoryNumber()
    {
        return $this->getCode();
    }

    // alias for $code
    public function setInventoryNumber(?string $code): void {
        if ($code) {
            $this->setCode($code);
        }
    }

    public function setLocalCode(?string $localCode): self
    {
        $this->localCode = $localCode;

        return $this;
    }

    // for acmi
    public function getBaseUrl() {
        return '/';
    }

    public function headphones_icon_light() {
        return 'fas fa-headphones';
    }

    #[Groups(['item.read'])]
    public function getStopId() {
        return $this->stopId ?: $this->getLocalCode();
    }
    public function section_title() {
        return $this->getCollection()->getName();
    }
    public function hero_images() {
        return [

        ];
    }
    public function audio_file() { return '/mp3/' . $this->getCode() . '.mp3';}


    /**
     * @return Collection|Asset[]
     */
    public function getImages()
    {
        return $this->getByType(Asset::ASSET_IMAGE);
    }

    /**
     * @return Collection|Asset[]
     */
    public function getVideos()
    {
        return $this->getByType(Asset::ASSET_VIDEO);
    }

    /**
     * @return Collection|Asset[]
     */
    public function getTexts()
    {
        return $this->getByType(Asset::ASSET_TEXT);
    }

    public function getSectionTitle(): ?string
    {
        return $this->sectionTitle;
    }

    public function setSectionTitle(?string $sectionTitle): self
    {
        $this->sectionTitle = $sectionTitle;

        return $this;
    }



    function getPublicRP(?array $addlParams = [] ): array
    {
        return array_merge($addlParams, $this->getCollection()->getPublicRp(['stopId' => $this->getStopId()]));
    }

    public function __toString()
    {
        return sprintf("%s:%s/%s", $this->getProject()->getCode(),
            $this->getCollection()->getShortCode(), $this->getCode());
        return $this->getCode();
        try {
            $str = sprintf("#%s %s-%s", $this->getStopId(),
                $this->getCollection() ? $this->getCollection()->getLongCode() : 'NO-COLLECTION', $this->getTitle());
        } catch (\Exception $e) {
            dump($this->getCollection());
            $str = sprintf("ID: %d, %s, %s", $this->getId(), $this->getCollection()->getId(), $e->getMessage());
        }
        return $str;
    }

    public function getOrderIdx(): ?int
    {
        return $this->orderIdx;
    }

    public function setOrderIdx(?int $orderIdx): self
    {
        $this->orderIdx = $orderIdx;

        return $this;
    }

    public function setStopId(?int $stopId): self
    {
        $this->stopId = $stopId;

        return $this;
    }

    public function getEndRow(): ?int
    {
        return $this->endRow;
    }

    public function setEndRow(?int $endRow): self
    {
        $this->endRow = $endRow;

        return $this;
    }

    public function getStartRow(): ?int
    {
        return $this->startRow;
    }

    public function setStartRow(?int $startRow): self
    {
        $this->startRow = $startRow;

        return $this;
    }

    public function getSourceFilename(): ?string
    {
        return $this->sourceFilename;
    }

    public function setSourceFilename(?string $sourceFilename): self
    {
        $this->sourceFilename = $sourceFilename;

        return $this;
    }

    /**
     * @return Collection|Stop[]
     */
    public function getStops(): Collection
    {
        return $this->stops;
    }

    public function addStop(Stop $stop): self
    {
        if (!$this->stops->contains($stop)) {
            $this->stops[] = $stop;
            $stop->setItem($this);
        }

        return $this;
    }

    public function removeStop(Stop $stop): self
    {
        if ($this->stops->contains($stop)) {
            $this->stops->removeElement($stop);
            // set the owning side to null (unless already changed)
            if ($stop->getItem() === $this) {
                $stop->setItem(null);
            }
        }

        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    public function setVisibility(?string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getOnTour(): ?bool
    {
        return $this->onTour;
    }

    public function setOnTour(?bool $onTour): self
    {
        $this->onTour = $onTour;

        return $this;
    }

    public function getShortCode(): ?string
    {
        return $this->shortCode;
    }

    public function setShortCode(string $shortCode): self
    {
        $this->shortCode = $shortCode;

        return $this;
    }


    public function getViews(): ?int
    {
        return $this->views;
    }

    public function setViews($views): static
    {
        $this->views = $views;

        return $this;
    }



    public function getAudio(): ?string
    {
        return $this->audio;
    }

    public function setAudio(?string $audio): static
    {
        $this->audio = $audio;

        return $this;
    }

    public function getVideo(): ?string
    {
        return $this->video;
    }

    public function setVideo(?string $video): static
    {
        $this->video = $video;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

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

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getImageUrls(): ?array
    {
        return $this->imageUrls;
    }

    public function setImageUrls(?array $imageUrls): static
    {
        $this->imageUrls = $imageUrls;

        return $this;
    }

    public function getAudioUrl(): ?string
    {
        return $this->audioUrl;
    }

    public function setAudioUrl(?string $audioUrl): static
    {
        $this->audioUrl = $audioUrl;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }


}
