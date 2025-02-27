<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ItemExhibition
 *
 * @ORM\Table(name="ItemExhibition")
 * @ORM\Entity
 */
class ItemExhibition
{
    public const TYPE_OTHER_MEDIA = 43; // if set, return empty properties for $title and similar property

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Exhibition", inversedBy="catalogueEntries")
     * @ORM\JoinColumn(name="id_exhibition", referencedColumnName="id")
     * @var Item
     */
    private $exhibition;

    /**
     * @ORM\ManyToOne(targetEntity="Item")
     * @ORM\JoinColumn(name="id_item", referencedColumnName="id")
     * @var Item
     */
    private $item;

    /**
     * @ORM\ManyToOne(targetEntity="Person", inversedBy="catalogueEntries")
     * @ORM\JoinColumn(name="id_person", referencedColumnName="id")
     * @var Person
     */
    private $person;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $displaycreator;

    /**
     * @var integer
     *
     * @ORM\Column(name="catalogue_section", type="integer")
     */
    private $catalogueSection;

    /**
     * @var string
     *
     * @ORM\Column(name="catalogueId", type="string", length=50, nullable=true)
     */
    private $catalogueId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $displaylocation;

    /**
     * @ORM\ManyToOne(targetEntity="Term", cascade={"all"}, fetch="EAGER")
     * @ORM\JoinColumn(name="type", referencedColumnName="id")
     */
    private $type;

    /**
     * @var int
     *
     * @ORM\Column(name="type", type="integer")
     */
    private $typeId;

    /**
     * @ORM\ManyToOne(targetEntity="Term", cascade={"all"}, fetch="EAGER")
     * @ORM\JoinColumn(name="style", referencedColumnName="id")
     */
    private $style;

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
     * @ORM\Column(name="title", type="string", length=255, nullable=true)
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
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $displaydate;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=1, nullable=true)
     */
    private $forsale;

    /**
     * @var string
     *
     * @ORM\Column(name="price", type="string", length=255, nullable=true)
     */
    private $price;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $owner;

    /**
     * @var string
     *
     * @ORM\Column(name="owner_alternate", type="string", length=255, nullable=true)
     */
    private $ownerAlternate;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity="ItemExhibitionMedia", mappedBy="itemexhibition", fetch="EAGER")
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

    /* custom accessor */
    public function getId()
    {
        return $this->id;
    }

    public function getExhibition()
    {
        return $this->exhibition;
    }

    public function getCatalogueIdSortIndex()
    {
        if (!is_null($this->catalogueId) && preg_match('/^(\d+)/', $this->catalogueId, $matches)) {
            return intval($matches[1]);
        }

        return 0;
    }

    public function setExhibition($exhibition)
    {
        $this->exhibition = $exhibition;
    }

    public function getPerson()
    {
        return $this->person;
    }

    public function setPerson($person)
    {
        $this->person = $person;
    }

    public function getItem()
    {
        return $this->item;
    }

    public function setItem($item)
    {
        $this->item = $item;
    }

    public function getDisplaydate()
    {
        if ($this->ignoreProperty()) {
            return;
        }

        return $this->displaydate;
    }

    public function getDisplaylocation()
    {
        return $this->displaylocation;
    }

    public function getPrice()
    {
        if ($this->ignoreProperty()) {
            return;
        }

        return $this->price;
    }

    /**
     * Returns true / false or null (unknown)
     */
    public function isForsale()
    {
        if (is_null($this->forsale) || $this->ignoreProperty()) {
            return null;
        }

        return 'Y' === $this->forsale;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getStyle()
    {
        return $this->style;
    }

    public function getDescription()
    {
        if ($this->ignoreProperty()) {
            return;
        }

        return $this->description;
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

    public function getTitleFull()
    {
        $parts = [];

        if (!empty($this->catalogueId)) {
            $parts[] = $this->catalogueId . '.';
        }

        if ($this->ignoreProperty()) {
            return implode(' ', $parts);
        }


        if (!empty($this->displaycreator)) {
            $parts[] = $this->displaycreator . ':';
        }

        $parts[] = $this->title;

        if (!empty($this->titleTransliterated) || !empty($this->titleAlternate)) {
            $append = [];

            if (!empty($this->titleTransliterated)) {
                $append[] = $this->titleTransliterated;
            }
            if (!empty($this->titleAlternate)) {
                $append[] = $this->titleAlternate;
            }

            $parts[] = sprintf('[%s]', implode(' : ', $append));
        }

        return implode(' ', $parts);
    }

    public function getTypeName()
    {
        $ret = '';

        if (!is_null($this->type)) {
            $type =  $this->type->getName();
            if ('0_unknown' != $type) {
                $ret = $type;
            }
        }

        return $ret;
    }

    public function getTypeParts($includeTypeName = true)
    {
        $typeParts = [];

        if ($includeTypeName) {
            $typeName = $this->getTypeName();
            if (!empty($typeName)) {
                $typeParts[] = $typeName;
            }
        }

        if (!empty($this->technique)) {
            $typeParts[] = $this->technique;
        }

        if (!empty($this->measurements)) {
            $typeParts[] = $this->measurements;
        }

        if (!empty($typeParts)) {
            return join(', ', $typeParts);
        }
    }

    public function getOwnerFull()
    {
        if (!empty($this->owner)) {
            $owner = $this->owner;
            if (!empty($this->ownerAlternate)) {
                $owner .= ' [' . $this->ownerAlternate . ']';
            }

            return $owner;
        }
    }

    public function isRegularEntry()
    {
        // corresponds to IE.title IS NOT NULL OR IE.item IS NULL
        return !empty($this->title) || is_null($this->item);
    }

    private function ignoreProperty()
    {
        if (is_null(self::TYPE_OTHER_MEDIA)) {
            return false;
        }

        return $this->typeId === self::TYPE_OTHER_MEDIA;
    }
}
