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
     * @ORM\Column(name="title_extended", type="string", length=255, nullable=true)
     */
    private $titleExtended;

    /**
     * @var string
     *
     * @ORM\Column(name="title_alternate", type="string", length=255, nullable=true)
     */
    private $titleAlternate;

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
     * @var string
     *
     * @ORM\Column(name="organizer_type", type="string", length=30, nullable=true)
     */
    private $organizerType;

    /**
     * @var integer
     *
     * @ORM\Column(name="flags", type="integer", nullable=false)
     */
    private $flags = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="title_translit", type="string", length=255, nullable=true)
     */
    private $titleTranslit;

    /**
     * @var string
     *
     * @ORM\Column(name="organizer", type="string", length=4095, nullable=true)
     */
    private $organizer;

    /**
     * @ORM\ManyToMany(targetEntity="Item", mappedBy="exhibitions")
     * @ORM\OrderBy({"earliestdate" = "ASC", "catalogueId" = "ASC"})
     */
    protected $items;

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
    
    public function getItems()
    {
        return $this->items;
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
