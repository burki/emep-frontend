<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // alias for Gedmo extensions annotations

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Location
 *
 * @see TODO: http://schema.org/EventVenue Documentation on Schema.org
 *
 * @ORM\Entity
 * @ORM\Table(name="Location")
 */
class Location
implements \JsonSerializable, JsonLdSerializable
{
    use AddressesTrait;

    const FLAGS_NOT_VENUE = 0x100;

    static function formatDateIncomplete($dateStr)
    {
        if (preg_match('/^\d{4}$/', $dateStr)) {
            $dateStr .= '-00-00';
        }
        else if (preg_match('/^\d{4}\-\d{2}$/', $dateStr)) {
            $dateStr .= '-00';
        }
        else if (preg_match('/^(\d+)\.(\d+)\.(\d{4})$/', $dateStr, $matches)) {
            $dateStr = join('-', [ $matches[3], $matches[2], $matches[1] ]);
        }

        return $dateStr;
    }

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $status = 0;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $flags = 0;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $type = null;


    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $country = null;

    /**
     * @var string A short description of the item.
     *
     * @ORM\Column(type="json_array", nullable=true)
     */
    protected $description;

    /**
     * @var string The date that this organization was dissolved.
     *
     * @ORM\Column(name="dissolutiondate", type="string", nullable=true)
     */
    protected $dissolutionDate;

    /**
     * @var string The date that this organization was founded.
     *
     * @ORM\Column(name="foundingdate", type="string", nullable=true)
     */
    protected $foundingDate;

    /**
     * @var string The name of the item.
     *
     * @Assert\Type(type="string")
     * @ORM\Column(nullable=true)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="name_translit", type="string", length=255, nullable=true)
     */
    private $nameTransliterated;

    /**
     * @var string URL of the item.
     *
     * @Assert\Url
     * @ORM\Column(nullable=true)
     */
    protected $url;

    /**
     * @var Place The place of the location
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Place")
     * @ORM\JoinColumn(name="place_tgn", referencedColumnName="tgn")
     */
    protected $place;

    /**
     * @var string Label of the place.
     *
     * @ORM\Column(nullable=true,name="place")
     */
    protected $placeLabel;

    /**
     * @var string Latitude, longitude of the (current) address.
     *
     * @ORM\Column(name="place_geo", nullable=true)
     */
    protected $geo;

    /**
     * @var string Addresses.
     *
     * @ORM\Column(name="address_additional", type="json_array", nullable=true)
     */
    protected $addresses;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    protected $gnd;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    protected $ulan;

    /**
     * @var \DateTime
     *
     * #Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created", type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * #Gedmo\Timestampable(on="update")
     * @ORM\Column(name="changed", type="datetime")
     */
    protected $changedAt;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * #ORM\Column(nullable=true)
     */
    protected $slug;

    /**
    * @ORM\Column(type="json_array", nullable=true)
    */
    protected $additional;

    /**
     * @ORM\OneToMany(targetEntity="Exhibition", mappedBy="location",cascade={"all"}, fetch="EAGER")
     * @ORM\OrderBy({"startdate" = "ASC"})
     */
    protected $exhibitions;

    /**
     * @ORM\ManyToMany(targetEntity="Exhibition", mappedBy="organizers")
     * @ORM\OrderBy({"startdate" = "ASC"})
     */
    protected $organizerOf;

    /**
     * Sets id.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets status.
     *
     * @param int $status
     *
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Gets status.
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Gets flags.
     *
     * @return int
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * Gets country.
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Returns false if self::FLAGS_NOT_VENUE is set.
     *
     * @return bool
     */
    public function isVenue()
    {
        return 0 == ($this->flags & self::FLAGS_NOT_VENUE);
    }

    /**
     * Sets description.
     *
     * @param string $description
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }


    /**
     * Sets description.
     *
     * @param string $description
     *
     * @return $this
     */
    public function getType()
    {
        return $this->type;

    }

    /**
     * Gets description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    public function getDescriptionLocalized($locale)
    {
        if (empty($this->description)) {
            return;
        }

        if (is_array($this->description)) {
            if (array_key_exists($locale, $this->description)) {
                return $this->description[$locale];
            }
        }
        else {
            return $this->description;
        }
    }

    /**
     * Sets dissolutionDate.
     *
     * @param string $dissolutionDate
     *
     * @return $this
     */
    public function setDissolutionDate($dissolutionDate = null)
    {
        $this->dissolutionDate = self::formatDateIncomplete($dissolutionDate);

        return $this;
    }

    /**
     * Gets dissolutionDate.
     *
     * @return string
     */
    public function getDissolutionDate()
    {
        return $this->dissolutionDate;
    }

    /**
     * Sets foundingDate.
     *
     * @param string $foundingDate
     *
     * @return $this
     */
    public function setFoundingDate($foundingDate = null)
    {
        $this->foundingDate = self::formatDateIncomplete($foundingDate);

        return $this;
    }

    /**
     * Gets foundingDate.
     *
     * @return string
     */
    public function getFoundingDate()
    {
        return $this->foundingDate;
    }

    /**
     * Sets name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets nameTransliterated.
     *
     * @param string $nameTransliterated
     *
     * @return $this
     */
    public function setNameTransliterated($nameTransliterated)
    {
        $this->nameTransliterated = $nameTransliterated;

        return $this;
    }

    /**
     * Gets nameTransliterated.
     *
     * @return string
     */
    public function getNameTransliterated()
    {
        return $this->nameTransliterated;
    }

    public function getNameAppend()
    {
        if (!empty($this->nameTransliterated) || !empty($this->nameAlternate)) {
            $append = [];

            if (!empty(!empty($this->nameTransliterated))) {
                $append[] = $this->nameTransliterated;
            }

            if (!empty(!empty($this->nameAlternate))) {
                $append[] = $this->nameAlternate;
            }

            return sprintf('[%s]', implode(' : ', $append));
        }
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

    /**
     * Gets place.
     *
     * @return Place
     */
    public function getPlace()
    {
        return $this->place;
    }

    /**
     * Gets place label.
     *
     * @return string
     */
    public function getPlaceLabel()
    {
        return $this->placeLabel;
    }

    /**
     * Gets geo.
     *
     * @return string
     */
    public function getGeo()
    {
        return $this->geo;
    }

    /**
     * Sets gnd.
     *
     * @param string $gnd
     *
     * @return $this
     */
    public function setGnd($gnd)
    {
        $this->gnd = $gnd;

        return $this;
    }

    /**
     * Gets gnd.
     *
     * @return string
     */
    public function getGnd()
    {
        return $this->gnd;
    }

    /**
     * Sets ulan.
     *
     * @param string $ulan
     *
     * @return $this
     */
    public function setUlan($ulan)
    {
        $this->ulan = $ulan;

        return $this;
    }

    /**
     * Gets ulan.
     *
     * @return string
     */
    public function getUlan()
    {
        return $this->ulan;
    }

    /**
     * Gets exhibitions.
     *
     */
    public function getExhibitions()
    {
        if (is_null($this->exhibitions)) {
            return null;
        }

        return $this->exhibitions->filter(
            function ($entity) {
               return -1 != $entity->getStatus();
            }
        );
    }

    /**
     * Gets organized exhibitions.
     *
     */
    public function getOrganizerOf($filterDuplicateWithExhibitions = false)
    {
        if (is_null($this->organizerOf)) {
            return null;
        }

        $skip = [];
        if ($filterDuplicateWithExhibitions && !is_null($this->exhibitions)) {
            foreach ($this->exhibitions as $exhibition) {
                $skip[] = $exhibition->getId();
            }
        }

        return $this->organizerOf->filter(
            function ($entity) use ($skip) {
                return -1 != $entity->getStatus() && !in_array($entity->getId(), $skip);
            }
        );
    }

    /**
     * Gets both venue and organized exhibitions.
     *
     */
    public function getAllExhibitions()
    {
        $ret = [];

        $exhibitions = $this->getExhibitions();
        if (!is_null($exhibitions)) {
            $ret = $exhibitions->toArray();
        }

        $exhibitions = $this->getOrganizerOf(true);
        if (!is_null($exhibitions)) {
            $ret = array_merge($ret, $exhibitions->toArray());
        }

        return $ret;
    }


    /**
     * Sets additional.
     *
     * @param array $additional
     *
     * @return $this
     */
    public function setAdditional($additional)
    {
        $this->additional = $additional;

        return $this;
    }

    /**
     * Gets additional.
     *
     * @return array
     */
    public function getAdditional()
    {
        return $this->additional;
    }

    public function getAddressesSeparated()
    {
        return $this->buildAddresses($this->addresses);
    }

    /**
     * Sets slug.
     *
     * @param string $slug
     *
     * @return $this
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Gets slug.
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gnd' => $this->gnd,
            'url' => $this->url,
            'foundingDate' => $this->foundingDate,
            'dissolutionDate' => $this->dissolutionDate,

        ];
    }

    public function jsonLdSerialize($locale, $omitContext = false)
    {
        $ret = [
            '@context' => 'http://schema.org',
            '@type' => 'Organization',
            'name' => $this->getName(),
        ];
        if ($omitContext) {
            unset($ret['@context']);
        }

        foreach ([ 'founding', 'dissolution'] as $lifespan) {
            $property = $lifespan . 'Date';
            if (!empty($this->$property)) {
                $ret[$property] = \AppBundle\Utils\JsonLd::formatDate8601($this->$property);
            }

            if ('founding' == $lifespan) {
                $property = $lifespan . 'Location';
                if (!is_null($this->$property)) {
                    $ret[$property] = $this->$property->jsonLdSerialize($locale, true);
                }
            }
        }

        $description = $this->getDescriptionLocalized($locale);
        if (!empty($description)) {
            $ret['description'] = $description;
        }

        foreach ([ 'url' ] as $property) {
            if (!empty($this->$property)) {
                $ret[$property] = $this->$property;

            }
        }

        if (!empty($this->gnd)) {
            $ret['sameAs'] = 'http://d-nb.info/gnd/' . $this->gnd;
        }

        return $ret;
    }
}
