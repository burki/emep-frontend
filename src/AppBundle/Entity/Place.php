<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // alias for Gedmo extensions annotations

/**
 * Entities that have a somewhat fixed, physical extension.
 *
 * @see http://schema.org/Place Documentation on Schema.org
 *
 * @ORM\Entity
 * @ORM\Table(name="Geoname")
 *
 */
class Place
implements \JsonSerializable, JsonLdSerializable
{
    static $zoomLevelByType = [
        'neighborhood' => 12,
        'city district' => 11,
        'district' => 11,
        'inhabited place' => 10,
    ];

    public static function buildTypeLabel($type)
    {
        if ('root' == $type) {
            return '';
        }

        if ('inhabited place' == $type) {
            return 'place';
        }

        return $type;
    }

    public static function buildPluralizedTypeLabel($type, $count)
    {
        if (empty($type)) {
            return '';
        }

        $label = self::buildTypeLabel($type);
        if ($count > 1) {
            $label = \Doctrine\Common\Inflector\Inflector::pluralize($label);
        }

        return ucfirst($label);
    }

    public static function ensureSortByPreferredLanguages($assoc, $default = null)
    {
        $language_preferred_ordered = [ 'de', 'en' ];

        if (is_null($assoc)) {
            $assoc = [];
        }

        foreach ($language_preferred_ordered as $lang) {
            if (!array_key_exists($lang, $assoc)) {
                $assoc[$lang] = $default;
            }
        }

        // make sure order is as in $language_preferred_ordered
        uksort($assoc, function($langA, $langB) use ($language_preferred_ordered) {
            if ($langA == $langB) {
                return 0;
            }

            $langOrderA = array_search($langA, $language_preferred_ordered);
            if (false === $langOrderA) {
                $langOrderA = 99;
            }
            $langOrderB = array_search($langB, $language_preferred_ordered);
            if (false === $langOrderB) {
                $langOrderB = 99;
            }

            return ($langOrderA < $langOrderB) ? -1 : 1;
        });

        return $assoc;
    }

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     *
     */
    protected $id;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $status = 0;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $type = 'inhabited place';

    /**
     * @var double The latitude of the place.
     *
     * @ORM\Column(nullable=true)
     *
     */
    protected $latitude;

    /**
     * @var double The longitude of the place.
     *
     * @ORM\Column(nullable=true)
     *
     */
    protected $longitude;

    /**
     * @var string The name of the item.
     *
     * @ORM\Column(nullable=false)
     */
    protected $name;

    /**
     * @var string An alias for the item.
     *
     * @ORM\Column(name="name_alternate", type="string", nullable=true)
     */
    protected $alternateName;

    /**
     *
     * @ORM\Column(name="country_code", type="string", nullable=true)
     *
     */
    protected $countryCode;

    /**
     * @var Country|null
     *
     * @ORM\ManyToOne(targetEntity="Country", fetch="EAGER")
     * @ORM\JoinColumn(name="country_code", referencedColumnName="cc", nullable=true)
     */
    protected $country;

    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", nullable=false)
     */
    protected $tgn;

    /**
     * @var string
     * @ORM\Column(name="tgn_parent", type="string", nullable=true)
     */
    protected $parentTgn;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    protected $gnd;

    /**
     * @var string
     * @ORM\Column(name="geonames_id", type="string", nullable=true)
     */
    protected $geonames;

    /**
     * @ORM\OneToMany(targetEntity="Location", mappedBy="place",cascade={"all"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    protected $locations;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="changed", type="datetime")
     */
    protected $changedAt;

    /**
     * @var \DateTime The date on which the Person or one of its related entities were last modified.
     */
    protected $dateModified;

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
     * Sets type.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
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

    /**
     * Gets geo.
     *
     * @return string
     */
    public function getGeo()
    {
        if (is_null($this->latitude) || is_null($this->longitude)) {
            return null;
        }

        return implode(',', [$this->latitude, $this->longitude]);
    }

    public function getLocations()
    {
        if (is_null($this->locations)) {
            return null;
        }

        return $this->locations->filter(
            function($entity) {
               return -1 != $entity->getStatus();
            }
        );
    }

    public function getVenues()
    {
        if (is_null($this->locations)) {
            return null;
        }

        return $this->locations->filter(
            function($entity) {
               return $entity->isVenue() && -1 != $entity->getStatus();
            }
        );
    }

    public function showCenterMarker($em)
    {
        $hasPlaceParent = false;
        $ancestorOrSelf = $this;

        while (!is_null($ancestorOrSelf)) {
            if ($ancestorOrSelf->type == 'inhabited places') {
                return true;
            }

            $ancestorOrSelf = $ancestorOrSelf->getParent($em);
        }

        return false;
    }

    public function getDefaultZoomlevel()
    {
        if (array_key_exists($this->type, self::$zoomLevelByType)) {
            return self::$zoomLevelByType[$this->type];
        }

        return 8;
    }

    /**
     * Sets countryCode.
     *
     * @param string $countryCode
     *
     * @return $this
     */
    public function setCountryCode($countryCode)
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    /**
     * Gets countryCode.
     *
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
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
     * Sets alternateName.
     *
     * @param string|null $alternateName
     *
     * @return $this
     */
    public function setAlternateName($alternateName)
    {
        $this->alternateName = $alternateName;

        return $this;
    }

    /**
     * Gets alternateName.
     *
     * @return string|null
     */
    public function getAlternateName()
    {
        return self::ensureSortByPreferredLanguages($this->alternateName, $this->name);
    }

    /**
     * Sets Getty Thesaurus of Geographic Names Identifier.
     *
     * @param string $tgn
     *
     * @return $this
     */
    public function setTgn($tgn)
    {
        $this->tgn = $tgn;

        return $this;
    }

    /**
     * Gets Getty Thesaurus of Geographic Names.
     *
     * @return string
     */
    public function getTgn()
    {
        return $this->tgn;
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
     * Sets geonames.
     *
     * @param string $geonames
     *
     * @return $this
     */
    public function setGeonames($geonames)
    {
        $this->geonames = $geonames;

        return $this;
    }

    /**
     * Gets geonames.
     *
     * @return string
     */
    public function getGeonames()
    {
        return $this->geonames;
    }

    public function getParent($em)
    {
        if (is_null($this->parentTgn)) {
            return null;
        }

        return $em->getRepository('AppBundle:Place')->findOneBy(['tgn' => $this->parentTgn]);
    }

    public function getChildren($em)
    {
        if (is_null($this->tgn)) {
            return null;
        }

        $qb = $em->createQueryBuilder();

        $qb->select([
                'P',
                "COALESCE(P.alternateName,P.name) HIDDEN nameSort"
            ])
            ->from('AppBundle:Place', 'P')
            ->where("P.parentTgn = :tgn")
            ->setParameter('tgn', $this->tgn)
            ->orderBy('nameSort')
            ;

        return $qb->getQuery()->getResult();
    }

    public function getChildrenByType($em)
    {
        $children = $this->getChildren($em);
        if (is_null($children)) {
            return null;
        }

        $ret = [];

        foreach ($children as $child) {
            $type = $child->getType();
            if (!array_key_exists($type, $ret)) {
                $ret[$type] = [];
            }
            $ret[$type][] = $child;
        }

        $typeWeights = [
            'continents' => -10,
            'nations' => 0,
            'dependent states' => 1,
            'former primary political entities' => 2,
            'states' => 3,
            'general regions' => 5,
            'communities' => 10,
            'historical regions' => 11,
            'inhabited places' => 15,
            'archipelagos' => 20,
        ];

        uksort($ret, function($typeA, $typeB) use ($typeWeights) {
            if ($typeA == $typeB) {
                return 0;
            }
            $typeOrderA = array_key_exists($typeA, $typeWeights) ? $typeWeights[$typeA] : 99;
            $typeOrderB = array_key_exists($typeB, $typeWeights) ? $typeWeights[$typeB] : 99;
            return ($typeOrderA < $typeOrderB) ? -1 : 1;
        });

        return $ret;
    }

    /**
     * Gets localized name.
     *
     * @return string
     */
    public function getNameLocalized($locale = 'en')
    {
        if (!empty($this->alternateName)) {
            return $this->alternateName;
        }

        return $this->getName();
    }

    public function getTypeLabel()
    {
        return self::buildTypeLabel($this->type);
    }

    public function getPath($em)
    {
        $path = [];
        $parent = $this->getParent($em);
        while ($parent != null) {
            $path[] = $parent;
            $parent = $parent->getParent($em);
        }

        return array_reverse($path);
    }

    /**
     * Sets dateModified.
     *
     * @param \DateTime $dateModified
     *
     * @return $this
     */
    public function setDateModified(\DateTime $dateModified = null)
    {
        $this->dateModified = $dateModified;

        return $this;
    }

    /**
     * Gets dateModified.
     *
     * @return \DateTime
     */
    public function getDateModified()
    {
        if (!is_null($this->dateModified)) {
            return $this->dateModified;
        }

        return $this->changedAt;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'geo' => $this->getGeo(),
            'tgn' => $this->tgn,
            'gnd' => $this->gnd,
        ];
    }

    public function jsonLdSerialize($locale, $omitContext = false, $em = null)
    {
        $ret = [
            '@context' => 'http://schema.org',
            '@type' => 'Place',
            'name' => $this->getNameLocalized($locale),
        ];

        if ($omitContext) {
            unset($ret['@context']);
        }

        if (!empty($this->latitude) && !empty($this->longitude)) {
            $ret['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' =>  $this->latitude,
                'longitude' => $this->longitude,
            ];
        }

        if (!is_null($em)) {
            $parent = $this->getParent($em);
            if (!is_null($parent)) {
                $ret['containedInPlace'] = $parent->jsonLdSerialize($locale, true);
            }
        }

        $sameAs = [];
        if (!empty($this->tgn)) {
            $sameAs[] = 'http://vocab.getty.edu/tgn/' . $this->tgn;
        }
        if (!empty($this->gnd)) {
            $sameAs[] = 'http://d-nb.info/gnd/' . $this->gnd;
        }
        if (count($sameAs) > 0) {
            $ret['sameAs'] = (1 == count($sameAs)) ? $sameAs[0] : $sameAs;
        }

        return $ret;
    }
}
