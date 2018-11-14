<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Item
 *
 * @ORM\Table(name="Item")
 * @ORM\Entity
 */
class Item
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
    private $status = 0;

    /**
     * #ORM\ManyToOne(targetEntity="Collection", cascade={"all"}, fetch="EAGER")
     * #ORM\JoinColumn(name="collection", referencedColumnName="id")
     */
    private $collection;

    /**
     * @var string
     *
     * @ORM\Column(name="recordId", type="string", length=50, nullable=true)
     */
    private $recordId;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=50, nullable=true)
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="catalogueId", type="string", length=50, nullable=true)
     */
    private $catalogueId;

    /**
     * @ORM\ManyToOne(targetEntity="Term", cascade={"all"}, fetch="EAGER")
     * @ORM\JoinColumn(name="type", referencedColumnName="id")
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=true)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="title_descriptive", type="string", length=255, nullable=true)
     */
    private $titleDescriptive;

    /**
     * @var string
     *
     * @ORM\Column(name="title_alternate", type="text", nullable=true)
     */
    private $titleAlternate;

    /**
     * @var string
     *
     * @ORM\Column(name="title_related", type="text", nullable=true)
     */
    private $titleRelated;

    /**
     * @ORM\ManyToMany(targetEntity="Term")
     * @ORM\JoinTable(name="ItemTerm",
     *      joinColumns={@ORM\JoinColumn(name="id_item", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="id_term", referencedColumnName="id")}
     *      )
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private $materials;

    /**
     * @var string
     *
     * @ORM\Column(name="technique", type="string", length=255, nullable=true)
     */
    private $technique;

    /**
     * @var string
     *
     * @ORM\Column(name="measurements", type="string", length=255, nullable=true)
     */
    private $measurements;

    /**
     * @var string
     *
     * @ORM\Column(name="weight", type="string", length=255, nullable=true)
     */
    private $weight;

    /**
     * @var string
     *
     * @ORM\Column(name="duration", type="string", length=255, nullable=true)
     */
    private $duration;

    /**
     * @var string
     *
     * @ORM\Column(name="extent", type="string", length=255, nullable=true)
     */
    private $extent;

    /**
     * @var string
     *
     * @ORM\Column(name="creatordate", type="string", length=127, nullable=true)
     */
    private $creatordate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="earliestdate", type="string", nullable=true)
     */
    private $earliestdate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="latestdate", type="string", nullable=true)
     */
    private $latestdate;

    /**
     * @var string
     *
     * @ORM\Column(name="displaydate", type="string", length=50, nullable=true)
     */
    private $displaydate;

    /**
     * @var string
     *
     * @ORM\Column(name="reasoningdate", type="string", length=127, nullable=true)
     */
    private $reasoningdate;

    /**
     * @var string
     *
     * @ORM\Column(name="caption", type="text", nullable=true)
     */
    private $caption;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="signature", type="text", nullable=true)
     */
    private $signature;

    /**
     * @var string
     *
     * @ORM\Column(name="inscriptions", type="text", nullable=true)
     */
    private $inscriptions;

    /**
     * @var string
     *
     * @ORM\Column(name="markings", type="text", nullable=true)
     */
    private $markings;

    /**
     * @var string
     *
     * @ORM\Column(name="utilization", type="text", nullable=true)
     */
    private $utilization;

    /**
     * @ORM\ManyToOne(targetEntity="Term", cascade={"all"}, fetch="EAGER")
     * @ORM\JoinColumn(name="`condition`", referencedColumnName="id")
     */
    private $condition;

    /**
     * @var boolean
     *
     * @ORM\Column(name="multiple", type="boolean", nullable=false)
     */
    private $multiple = false;

    /**
     * @var string
     *
     * @ORM\Column(name="editionnr", type="string", length=255, nullable=true)
     */
    private $editionnr;

    /**
     * @var string
     *
     * @ORM\Column(name="editiontotal", type="string", length=255, nullable=true)
     */
    private $editiontotal;

    /**
     * @var string
     *
     * @ORM\Column(name="editionpublished", type="string", length=255, nullable=true)
     */
    private $editionpublished;

    /**
     * #ORM\ManyToOne(targetEntity="Publisher", cascade={"all"}, fetch="EAGER")
     * #ORM\JoinColumn(name="publisher_id", referencedColumnName="id")
     */

    private $publisher;

    /**
     * @var string
     *
     * @ORM\Column(name="editionmanufacturer", type="string", length=255, nullable=true)
     */
    private $editionmanufacturer;

    /**
     * @var string
     *
     * @ORM\Column(name="editionnotes", type="string", length=255, nullable=true)
     */
    private $editionnotes;

    /**
     * @var string
     *
     * @ORM\Column(name="fabricator", type="string", length=255, nullable=true)
     */
    private $fabricator;

    /**
     * @var string
     *
     * @ORM\Column(name="fabricationparts", type="text", nullable=true)
     */
    private $fabricationparts;

    /**
     * @var string
     *
     * @ORM\Column(name="creationlocation", type="string", length=255, nullable=true)
     */
    private $creationlocation;

    /**
     * @var string
     *
     * @ORM\Column(name="originallocation", type="string", length=255, nullable=true)
     */
    private $originallocation;

    /**
     * @var string
     *
     * @ORM\Column(name="currentlocation", type="string", length=255, nullable=true)
     */
    private $currentlocation;

    /**
     * @var string
     *
     * @ORM\Column(name="workId", type="string", length=50, nullable=true)
     */
    private $workId;

    /**
     * @var string
     *
     * @ORM\Column(name="currentpresentation", type="string", length=255, nullable=true)
     */
    private $currentpresentation;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="acquireddate", type="string", nullable=true)
     */
    private $acquireddate;

    /**
     * @var string
     *
     * @ORM\Column(name="acquiredcode", type="string", length=255, nullable=true)
     */
    private $acquiredcode;

    /**
     * @ORM\ManyToOne(targetEntity="Term", cascade={"all"}, fetch="EAGER")
     * @ORM\JoinColumn(name="acquiredtype", referencedColumnName="id")
     */
    private $acquiredtype;

    /**
     * @var string
     *
     * @ORM\Column(name="owner", type="string", length=255, nullable=true)
     */
    private $owner;

    /**
     * @var string
     *
     * @ORM\Column(name="borrower", type="string", length=255, nullable=true)
     */
    private $borrower;

    /**
     * @var string
     *
     * @ORM\Column(name="preowner", type="text", nullable=true)
     */
    private $preowner;

    /**
     * @var string
     *
     * @ORM\Column(name="ownershiphistory", type="text", nullable=true)
     */
    private $ownershiphistory;

    /**
     * @var string
     *
     * @ORM\Column(name="price", type="string", length=255, nullable=true)
     */
    private $price;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", length=255, nullable=true)
     */
    private $value;

    /**
     * @var string
     *
     * @ORM\Column(name="iconclass", type="text", nullable=true)
     */
    private $iconclass;

    /**
     * @var string
     *
     * @ORM\Column(name="exhibition", type="text", nullable=true)
     */
    private $exhibition;

    /**
     * @var string
     *
     * @ORM\Column(name="award", type="text", nullable=true)
     */
    private $award;

    /**
     * @var string
     *
     * @ORM\Column(name="impact", type="text", nullable=true)
     */
    private $impact;

    /**
     * @var string
     *
     * @ORM\Column(name="reprint", type="text", nullable=true)
     */
    private $reprint;

    /**
     * @var string
     *
     * @ORM\Column(name="literature", type="text", nullable=true)
     */
    private $literature;

    /**
     * @var string
     *
     * @ORM\Column(name="archive", type="text", nullable=true)
     */
    private $archive;

    /**
     * @var string
     *
     * @ORM\Column(name="coreset", type="string", length=1, nullable=true)
     */
    private $coreset;

    /**
     * @var string
     *
     * @ORM\Column(name="estateset", type="string", length=1, nullable=false)
     */
    private $estateset = 'N';

    /**
     * @var string
     *
     * @ORM\Column(name="image_status", type="string", length=255, nullable=true)
     */
    private $imageStatus;

    /**
     * @var string
     *
     * @ORM\Column(name="image_additional", type="string", length=255, nullable=true)
     */
    private $imageAdditional;

    /**
     * @var string
     *
     * @ORM\Column(name="image_note", type="text", nullable=true)
     */
    private $imageNote;

    /**
     * @var string
     *
     * @ORM\Column(name="comment_internal", type="text", nullable=true)
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

    /* related fields */

    /**
     * #ORM\OneToMany(targetEntity="ItemPerson", mappedBy="item", fetch="EAGER")
     * #ORM\OrderBy({"ord" = "ASC"})
     * #var PersonRefs[]
     */
    private $personRefs;

    /**
     * @var ArrayCollection<Person> The creator(s) of this item.
     *
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\Person", inversedBy="items")
     * @ORM\JoinTable(name="ItemPerson",
     *      joinColumns={@ORM\JoinColumn(name="id_item", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="id_person", referencedColumnName="id")}
     *  )
     */
    public $creators;

    /**
     * @var ArrayCollection<Exhibition> The exhibition(s) of this item.
     *
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\Exhibition", inversedBy="items")
     * @ORM\JoinTable(name="ItemExhibition",
     *      joinColumns={@ORM\JoinColumn(name="id_item", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="id_exhibition", referencedColumnName="id")}
     *  )
     */
    public $exhibitions;

    /**
     * @ORM\OneToMany(targetEntity="ItemKeyword", mappedBy="idItem", fetch="EAGER")
     * @ORM\OrderBy({"type" = "ASC", "ord" = "ASC"})
     * @var Keywords[]
     */
    private $keywords;

    /**
     * @ORM\OneToOne(targetEntity="Term", fetch="EAGER")
     * @ORM\JoinColumn(name="style", referencedColumnName="id")
     */
    private $style;

    /**
     * @ORM\OneToMany(targetEntity="ItemMedia", mappedBy="item", fetch="EAGER")
     * @ORM\OrderBy({"name" = "ASC", "ord" = "ASC"})
     */
    private $media;

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

    public function getEarliestdate()
    {
        return $this->earliestdate;
    }

    public function getLatestdate()
    {
        return $this->latestdate;
    }

    public function getDisplaydate()
    {
        return $this->displaydate;
    }

    public function getStyle()
    {
        return $this->style;
    }

    public function getPreviewImg()
    {
        if (is_null($this->media)) {
            return null;
        }

        foreach ($this->media as $img) {
            if ($img->getStatus() == -1) {
                continue;
            }
            if ('preview00' == $img->getName()) {
                return $img;
            }
        }

        return null;
    }

    public function setPersons($persons, $em) {
        $this->personRefs = [];
        $ord = 0;
        foreach ($persons as $person) {
            // might already exist
            $item_id = $this->id;
            $personRef = null;
            if (isset($item_id)) {
                $personRef = $em->getRepository('ItemPerson')
                    ->findOneBy([
                        'person' => $person,
                        'item' => $this,
                    ]);
            }
            if (!isset($personRef)) {
                $personRef = new ItemPerson();
                $personRef->setItem($this);
                $personRef->setPerson($person);
            }
            $personRef->setOrd($ord++);
            $em->persist($personRef);
            $this->personRefs[] = $personRef;
        }
    }

    /*
     * see http://stackoverflow.com/a/13522452
     *
     */
    public function toArray($em)
    {
        $className = get_class($this);

        $uow = $em->getUnitOfWork();
        $entityPersister = $uow->getEntityPersister($className);
        $classMetadata = $entityPersister->getClassMetadata();

        $result = [];
        foreach ($uow->getOriginalEntityData($this) as $field => $value) {
            if (isset($classMetadata->associationMappings[$field])) {
                $assoc = $classMetadata->associationMappings[$field];

                // Only owning side of x-1 associations can have a FK column.
                if ( ! $assoc['isOwningSide'] || ! ($assoc['type'] & \Doctrine\ORM\Mapping\ClassMetadata::TO_ONE)) {
                    continue;
                }

                if ($value !== null) {
                    $newValId = $uow->getEntityIdentifier($value);
                }

                $targetClass = $em->getClassMetadata($assoc['targetEntity']);
                $owningTable = $entityPersister->getOwningTable($field);

                foreach ($assoc['joinColumns'] as $joinColumn) {
                    $sourceColumn = $joinColumn['name'];
                    $targetColumn = $joinColumn['referencedColumnName'];

                    if ($value === null) {
                        $result[$sourceColumn] = null;
                    } else if ($targetClass->containsForeignIdentifier) {
                        $result[$sourceColumn] = $newValId[$targetClass->getFieldForColumn($targetColumn)];
                    } else {
                        $result[$sourceColumn] = $newValId[$targetClass->fieldNames[$targetColumn]];
                    }
                }
            } else if (isset($classMetadata->columnNames[$field])) {
                $columnName = $classMetadata->columnNames[$field];
                $result[$columnName] = $value;
            }
        }

        return $result;
    }
}
