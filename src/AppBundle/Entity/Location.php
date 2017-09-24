<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // alias for Gedmo extensions annotations

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Exhibtion
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
     * @var string A short description of the item.
     *
     * @ORM\Column(type="json_array", nullable=true)
     */
    protected $description;

    /**
     * @var string The date that this organization was dissolved.
     *
     * #ORM\Column(type="string", nullable=true)
     */
    protected $dissolutionDate;

    /**
     * @var string The date that this organization was founded.
     *
     * #ORM\Column(type="string", nullable=true)
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
     * @ORM\OneToMany(targetEntity="Exhibition", mappedBy="location",cascade={"all"}, fetch="EAGER")
     * @ORM\OrderBy({"startdate" = "ASC"})
     */
    protected $exhibitions;

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
     * Gets localized name.
     *
     * @return string
     */
    public function getNameLocalized($locale = 'en')
    {
        if (is_array($this->alternateName)
            && array_key_exists($locale, $this->alternateName)) {
            $name = $this->alternateName[$locale];
        }
        else {
            $name = $this->getName();
        }

        return self::stripAt($name);
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
     * Gets exhibitions.
     *
     */
    public function getExhibitions()
    {
        return $this->exhibitions;
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
        ];
    }

    public function jsonLdSerialize($locale, $omitContext = false)
    {
        $ret = [
            '@context' => 'http://schema.org',
            '@type' => 'Organization',
            'name' => $this->getNameLocalized($locale),
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
