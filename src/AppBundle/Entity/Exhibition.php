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
{
    const FLAGS_CATIDBYARTIST = 0x20;

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

    /*
     * Expanded $info
     */
    protected $infoExpanded = [];

    /**
     * @ORM\ManyToMany(targetEntity="Item", mappedBy="exhibitions")
     * @ORM\OrderBy({"earliestdate" = "ASC", "catalogueId" = "ASC"})
     */
    protected $items;

    /**
     * @var ArrayCollection<ItemExhibition> The catalogue entri(s) of this exhibition.
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

    public function getDisplaydate()
    {
        return $this->displaydate;
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

    public function hasInfo()
    {
        return !empty($this->info);
    }

    public function buildInfoFull($em, $citeProc)
    {
        // lookup publications
        $publicationsById = [];
        foreach ($this->info as $entry) {
            if (!empty($entry['id_publication'])) {
                $publicationsById[$entry['id_publication']] = null;
            }
        }

        if (!empty($publicationsById)) {
            $qb = $em->createQueryBuilder();

            $qb->select([ 'B' ])
                ->from('AppBundle:Bibitem', 'B')
                ->andWhere('B.id IN (:ids) AND B.status <> -1')
                ->setParameter('ids', array_keys($publicationsById))
                ;

            $results = $qb->getQuery()
                ->getResult();
            foreach ($results as $bibitem) {
                $publicationsById[$bibitem->getId()] = $bibitem;
            }
        }

        $this->infoExpanded = [];
        foreach ($this->info as $entry) {
            if (!empty($entry['id_publication'])
                && !is_null($publicationsById[$entry['id_publication']]))
            {
                $bibitem = $publicationsById[$entry['id_publication']];
                if (!empty($entry['pages'])) {
                    $bibitem->setPagination($entry['pages']);
                }
                $entry['citation'] = $bibitem->renderCitationAsHtml($citeProc, false);
            }
            $this->infoExpanded[] = $entry;
        }
    }

    public function getInfoExpanded()
    {
        return $this->infoExpanded;
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
