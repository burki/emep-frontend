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
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Exhibition")
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
     * @ORM\ManyToOne(targetEntity="Person")
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
    public function getExhibition()
    {
        return $this->exhibition;
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
        return $this->displaydate;
    }

    public function getDisplaylocation()
    {
        return $this->displaylocation;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function isForsale()
    {
        return !is_null($this->forsale) && 'Y' === $this->forsale;
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

        if (!empty($this->displaycreator)) {
            $parts[] = $this->displaycreator . ':';
        }

        $parts[] = $this->title;

        if (!empty($this->titleTransliterated) || !empty($this->titleAlternate)) {
            $append = [];

            if (!empty(!empty($this->titleTransliterated))) {
                $append[] = $this->titleTransliterated;
            }
            if (!empty(!empty($this->titleAlternate))) {
                $append[] = $this->titleAlternate;
            }

            $parts[] = sprintf('[%s]', implode(' : ', $append));
        }

        return implode(' ', $parts);
    }

    public function getTypeParts()
    {
        $typeParts = [];
        if (!is_null($this->type)) {
            $type =  $this->type->getName();
            if ('0_unknown' != $type) {
                $typeParts[] = $type;
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
}
