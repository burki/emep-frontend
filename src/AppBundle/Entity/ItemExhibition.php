<?php


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ItemPerson
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
     * @ORM\Column(name="catalogueId", type="string", length=50, nullable=true)
     */
    private $catalogueId;

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
     * @ORM\Column(name="displaydate", type="string", length=50, nullable=true)
     */
    private $displaydate;

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

    public function getStyle()
    {
        return $this->style;
    }

    public function getTitleFull()
    {
        $parts = [];

        if (!empty($this->catalogueId)) {
            $parts[] = $this->catalogueId . '.';
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

        if (!empty($this->displaydate)) {
            $parts[count($parts) - 1] .= ', ' . $this->displaydate;
        }

        return implode(' ', $parts);
    }
}
