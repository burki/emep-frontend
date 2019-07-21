<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Exhibition
 * TODO: Sync with http://schema.org/ExhibitionEvent
 *
 * @ORM\Table(name="Exhibition")
 * @ORM\Entity
 */
class Exhibition
implements JsonLdSerializable
{
    use InfoTrait;

    const FLAGS_CATIDBYARTIST = 0x20;
    const FLAGS_TRAVELING = 0x40;
    const FLAGS_PARTICIPANTADDRESSESLISTED = 0x80;
    const FLAGS_MEMBERSLISTED = 0x200;
    const FLAGS_MEMBERADDRESSESLISTED = 0x400;
    const FLAGS_OTHERMEDIUMSLISTED = 0x800;
    const FLAGS_ALTEREDSTRUCTURE = 0x2000;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="status", type="integer", nullable=false)
     */
    private $status = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", nullable=false)
     */
    private $type = 'group';

    /**
     * @ORM\ManyToOne(targetEntity="Location", cascade={"all"}, fetch="EAGER")
     * @ORM\JoinColumn(name="id_location", referencedColumnName="id")
     */
    protected $location;

    /**
     * @var ArrayCollection<Location> The organizer(s) of this exhibition.
     *
     * @ORM\ManyToMany(targetEntity="Location", inversedBy="organizerOf")
     * @ORM\JoinTable(name="ExhibitionLocation",
     *      joinColumns={@ORM\JoinColumn(name="id_exhibition", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="id_location", referencedColumnName="id")}
     * )
     * @ORM\OrderBy({"id" = "ASC"})
     */
    protected $organizers;

    /**
     * @var string
     *
     * @ORM\Column(name="startdate", type="string", nullable=true)
     */
    private $startdate;

    /**
     * @var string
     *
     * @ORM\Column(name="enddate", type="string", nullable=true)
     */
    private $enddate;

    /**
     * @var string
     *
     * @ORM\Column(name="displaydate", type="string", length=80, nullable=true)
     */
    private $displaydate;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=511, nullable=true)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="title_translit", type="string", length=255, nullable=true)
     */
    private $titleTransliterated;

    /**
     * @var string
     *
     * @ORM\Column(name="title_alternate", type="text", nullable=true)
     */
    private $titleAlternate;

    /**
     * @var string
     *
     * @ORM\Column(name="title_extended", type="string", length=255, nullable=true)
     */
    private $titleExtended;

    /**
     * @var string
     *
     * @ORM\Column(name="subtitle", type="string", length=255, nullable=true)
     */
    private $subtitle;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", length=65535, nullable=true)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="hours", type="string", length=80, nullable=true)
     */
    private $hours;

    /**
     * @var string
     *
     * @ORM\Column(name="events", type="text", length=65535, nullable=true)
     */
    private $events;

    /**
     * @var string
     *
     * @ORM\Column(name="url", type="string", length=255, nullable=true)
     */
    private $url;

    /**
     * @var string
     *
     * @ORM\Column(name="comment_internal", type="text", length=65535, nullable=true)
     */
    private $commentInternal;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=true)
     */
    private $created;

    /**
     * @var integer
     *
     * @ORM\Column(name="created_by", type="integer", nullable=true)
     */
    private $createdBy;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="changed", type="datetime", nullable=true)
     */
    private $changed;

    /**
     * @var integer
     *
     * @ORM\Column(name="changed_by", type="integer", nullable=true)
     */
    private $changedBy;

    /**
     * @var string
     *
     * @ORM\Column(name="currency", type="string", length=255, nullable=true)
     */
    private $currency;

    /**
     * @var string
     *
     * @ORM\Column(name="price", type="string", length=255, nullable=true)
     */
    private $price;

    /**
     * @var string
     *
     * @ORM\Column(name="attendants", type="string", length=255, nullable=true)
     */
    private $attendants;

    /**
     * @var string
     *
     * @ORM\Column(name="title_short", type="string", length=255, nullable=true)
     */
    private $titleShort;

    /**
     * @var integer
     *
     * @ORM\Column(name="flags", type="integer", nullable=false)
     */
    private $flags = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="organizer_type", type="string", length=30, nullable=true)
     */
    private $organizerType;

    /**
     * @var string
     *
     * @ORM\Column(name="organizer", type="string", length=1025, nullable=true)
     */
    private $organizer;

    /**
     * @var string
     *
     * @ORM\Column(name="organizing_committee", type="string", nullable=true)
     */
    private $organizingCommittee;

    /**
     * @var string
     *
     * @ORM\Column(name="price_catalogue", type="string", nullable=true)
     */
    private $cataloguePrice;

    /**
     * @var string
     *
     * @ORM\Column(name="preface", type="string", nullable=true)
     */
    private $preface;

    /**
     * @var string
     *
     * @ORM\Column(name="structure_catalogue", type="text", length=65535, nullable=true)
     */
    private $catalogueStructure;

    /**
     * @var
     *
     * @ORM\Column(name="info", type="json_array", nullable=true)
     */
    protected $info;

    /**
     * @ORM\ManyToMany(targetEntity="Item", mappedBy="exhibitions")
     * @ORM\OrderBy({"earliestdate" = "ASC", "catalogueId" = "ASC"})
     */
    protected $items;

    /**
     * @var ArrayCollection<ItemExhibition> The catalogue entries of this exhibition.
     *
     * @ORM\OneToMany(targetEntity="ItemExhibition", mappedBy="exhibition")
     */
    public $catalogueEntries;

    /**
     * @ORM\ManyToMany(targetEntity="Person", mappedBy="exhibitions")
     * @ORM\OrderBy({"familyName" = "ASC", "givenName" = "ASC"})
     */
    protected $artists;

    public static function stripTime($datetime)
    {
        if (is_null($datetime)) {
            return $datetime;
        }

        return preg_replace('/\s+00:00:00$/', '', $datetime);
    }

    public static function extractYear($datetime)
    {
        if (is_null($datetime)) {
            return $datetime;
        }

        if (preg_match('/^([\d]+)\-/', $datetime, $matches)) {
            return (int)($matches[1]);
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getTitleAppend()
    {
        if (!empty($this->titleTransliterated) || !empty($this->titleAlternate)) {
            $append = [];

            if (!empty(!empty($this->titleTransliterated))) {
                $append[] = $this->titleTransliterated;
            }

            if (!empty(!empty($this->titleAlternate))) {
                $append[] = $this->titleAlternate;
            }

            return sprintf(' [%s]', implode(' : ', $append));
        }
    }

    public function getTitleListing()
    {
        if (!empty($this->titleTransliterated)) {
            // show translit / translation in brackets instead of original
            $parts = [ $this->titleTransliterated ];

            if (!empty($this->titleAlternate)) {
                $parts[] = $this->titleAlternate;
            }

            return sprintf('[%s]', join(' : ', $parts));
        }

        return $this->getTitle();
    }

    public function getSubtitle()
    {
        return $this->subtitle;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getStartdate()
    {
        return self::stripTime($this->startdate);
    }

    public function getEnddate()
    {
        return self::stripTime($this->enddate);
    }

    public function getStartyear()
    {
        return self::extractYear($this->startdate);
    }

    public function getDisplaydate()
    {
        return $this->displaydate;
    }

    /**
     * Gets type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function getNote()
    {
        return $this->description;
    }

    public function getOrganizer()
    {
        return $this->organizer;
    }

    public function getOrganizerType()
    {
        return $this->organizerType;
    }

    public function getOrganizers()
    {
        return $this->organizers;
    }

    public function getOrganizingCommittee()
    {
        return $this->organizingCommittee;
    }

    /**
     * Sets url.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Gets url.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function getHours()
    {
        return $this->hours;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getCataloguePrice()
    {
        return $this->cataloguePrice;
    }

    public function getPreface()
    {
        return $this->preface;
    }

    public function getCatalogueStructure()
    {
        return $this->catalogueStructure;
    }

    public function getItems($minStatus = 0)
    {
        if (is_null($this->items)) {
            return $this->items;
        }

        return $this->items->filter(
            function($entity) use ($minStatus) {
               return $entity->getStatus() >= $minStatus;
            }
        );
    }

    public function getArtists()
    {
        return $this->artists;
    }

    public function findBibitem($em, $role = null)
    {
        $qb = $em->createQueryBuilder();
        $qb->select([
                'B',
                "COALESCE(B.author, B.editor) HIDDEN creatorSort",
            ])
            ->distinct()
            ->from('AppBundle:Bibitem', 'B')
            ->innerJoin('AppBundle:BibitemExhibition', 'BE', 'WITH', 'BE.bibitem=B')
            ->innerJoin('AppBundle:Exhibition', 'E', 'WITH', 'BE.exhibition=E')
            ->where('E = :exhibition AND B.status <> -1')
            ->orderBy('creatorSort, B.datePublished, B.title')
            ;

        if (!is_null($role)) {
            $qb->andWhere('BE.role = :role')
                ->setParameter('role', $role);
        }

        $results = $qb->getQuery()
            ->setParameter('exhibition', $this)
            ->getResult();

        return $results;
    }

    public function isSortedByPerson()
    {
        return 0 <> ($this->flags & self::FLAGS_CATIDBYARTIST);
    }

    public function isTraveling()
    {
        return 0 <> ($this->flags & self::FLAGS_TRAVELING);
    }

    public function getInfoFromFlags()
    {
        $lines = [];

        if ($this->isTraveling()) {
            $lines[] = 'Traveling Exhibition';
        }

        if (0 <> ($this->flags & self::FLAGS_ALTEREDSTRUCTURE)) {
            $lines[] = 'Catalogue structure altered';
        }

        if (0 <> ($this->flags & self::FLAGS_OTHERMEDIUMSLISTED)) {
            $lines[] = 'Other Mediums Listed';
        }

        if (0 <> ($this->flags & self::FLAGS_PARTICIPANTADDRESSESLISTED)) {
            $lines[] = 'Participant Addresses listed';
        }

        if (0 <> ($this->flags & self::FLAGS_MEMBERSLISTED)) {
            $lines[] = 'Members listed in Catalogue';
        }

        if (0 <> ($this->flags & self::FLAGS_MEMBERADDRESSESLISTED)) {
            $lines[] = 'Member Addresses listed';
        }

        return implode("\n", $lines);
    }

    public function checkStatus($ignore)
    {
        if (-1 == $ignore) {
            // also ignore -2: INTERNAL_ONLINE
            return $this->status <> -1 && $this->status <> -2;
        }

        return $this->status <> $ignore;
    }

    public function getCatalogueEntries()
    {
        if (is_null($this->catalogueEntries)) {
            return [];
        }

        return $this->catalogueEntries->filter(
            function ($entity) {
               return $entity->isRegularEntry(); // corresponds to IE.title IS NOT NULL OR IE.item IS NULL
            }
        );
    }

    public function jsonLdSerialize($locale, $omitContext = false)
    {
        $ret = [
            '@context' => 'http://schema.org',
            '@type' => 'ExhibitionEvent',
            'name' => $this->getTitle() . $this->getTitleAppend(),
        ];

        if ($omitContext) {
            unset($ret['@context']);
        }

        foreach ([ 'start', 'end'] as $key) {
            $property = $key . 'date';
            if (!empty($this->$property)) {
                $ret[$property] = \AppBundle\Utils\JsonLd::formatDate8601($this->$property);
            }
        }

        if (!empty($this->location)) {
            $ret['location'] = [
                '@type' => 'Place',
                'name' => $this->location->getName(),
            ];

            $addresses = array_map(function ($address) { return $address['info']; }, $this->location->getAddressesSeparated());
            if (!empty($addresses)) {
                $ret['location']['address'] = join(', ', $addresses);
            }

            $place = $this->location->getPlace();
            if (!empty($place)) {
                $ret['location']['containedInPlace'] = $place->jsonLdSerialize($locale, true);
            }
        }

        /*
        $description = $this->getDescriptionLocalized($locale);
        if (!empty($description)) {
            $ret['description'] = $description;
        }
        */

        foreach ([ 'url' ] as $property) {
            if (!empty($this->$property)) {
                $ret[$property] = $this->$property;
            }
        }

        return $ret;
    }

    /* make private properties public through a generic __get / __set */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }

    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            return $this->$name = $value;
        }
    }
}
