<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // alias for Gedmo extensions annotations
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An organization such as a school, NGO, corporation, club, etc.
 *
 * @see http://schema.org/Organization Documentation on Schema.org
 *
 * @ORM\Entity
 * @ORM\Table(name="Holder")
 */
class Holder implements \JsonSerializable, JsonLdSerializable
{
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
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $status = 0;

    /**
     *
     * @ORM\Column(name="country", type="string", nullable=true)
     *
     */
    protected $countryCode;

    /**
     * @var Country|null
     *
     * @ORM\ManyToOne(targetEntity="Country", fetch="EAGER")
     * @ORM\JoinColumn(name="country", referencedColumnName="cc", nullable=true)
     */
    protected $country;

    /**
     * @var string A short description of the item.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $description;

    /**
     * @var string The date that this organization was dissolved.
     *
     * @Assert\Date
     * @ORM\Column(type="string", nullable=true)
     */
    // protected $dissolutionDate;

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
     * @var string
     *
     * @ORM\Column(name="name_translit", type="string", length=255, nullable=true)
     */
    private $nameTransliterated;

    /**
     * @var string Label of the address.
     *
     * @ORM\Column(nullable=true,name="address")
     */
    protected $placeAddress;

    /**
     * @var string Label of the place.
     *
     * @ORM\Column(nullable=true,name="town")
     */
    protected $placeLabel;

    /**
     * @var string URL of the item.
     *
     * @Assert\Url
     * @ORM\Column(nullable=true)
     */
    protected $url;

    /**
     * @var string
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    protected $gnd;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    protected $ulan;

    /**
     * @ORM\OneToMany(targetEntity="BibitemHolder", mappedBy="holder")
     */
    protected $holderOf;

    /**
     * @var Organization The organization that preceded this on.
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Organization", inversedBy="succeedingOrganization")
     * @ORM\JoinColumn(name="precedingId", referencedColumnName="id")
     */
    // protected $precedingOrganization;

    /**
     * @var Organization The organization that suceeded this on.
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\Organization", mappedBy="precedingOrganization")
     */
    // protected $succeedingOrganization;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created", type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTime The date on which the Holder or one of its related entities were last modified.
     */
    protected $dateModified;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="changed", type="datetime")
     */
    protected $changedAt;

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
     * Sets alternateName.
     *
     * @param array|null $alternateName
     *
     * @return $this
     */
    /*
    public function setAlternateName($alternateName)
    {
        $this->alternateName = $alternateName;

        return $this;
    }
    */

    /**
     * Gets alternateName.
     *
     * @return array|null
     */
    /*
    public function getAlternateName()
    {
        return self::ensureSortByPreferredLanguages($this->alternateName, self::stripAt($this->name));
    }
    */


    /**
     * Gets placeAdress.
     *
     * @return string
     */
    public function getPlaceAddress()
    {
        return $this->placeAddress;
    }

    /**
     * Gets placeLabel.
     *
     * @return string
     */
    public function getPlaceLabel()
    {
        return $this->placeLabel;
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

    /**
     * Sets dissolutionDate.
     *
     * @param string $dissolutionDate
     *
     * @return $this
     */
    /*
    public function setDissolutionDate($dissolutionDate = null)
    {
        $this->dissolutionDate = self::formatDateIncomplete($dissolutionDate);

        return $this;
    }
    */

    /**
     * Gets dissolutionDate.
     *
     * @return string
     */
    /*
    public function getDissolutionDate()
    {
        return $this->dissolutionDate;
    }
    */

    /**
     * Sets foundingDate.
     *
     * @param string $foundingDate
     *
     * @return $this
     */
    /*
    public function setFoundingDate($foundingDate = null)
    {
        $this->foundingDate = self::formatDateIncomplete($foundingDate);

        return $this;
    }
    */

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
        if (!empty($this->nameTransliterated)) {
            $append = [];

            if (!empty($this->nameTransliterated)) {
                $append[] = $this->nameTransliterated;
            }

            return sprintf('[%s]', implode(' : ', $append));
        }
    }

    public function getNameListing()
    {
        if (!empty($this->nameTransliterated)) {
            // show translit / translation in brackets instead of original
            $parts = [ $this->nameTransliterated ];

            if (!empty($this->nameAlternate)) {
                $parts[] = $this->nameAlternate;
            }

            return sprintf('[%s]', join(' : ', $parts));
        }

        return $this->getName();
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
     * Sets foundingLocation.
     *
     * @param Place $foundingLocation
     *
     * @return $this
     */
    /*
    public function setFoundingLocation(Place $foundingLocation = null)
    {
        $this->foundingLocation = $foundingLocation;

        return $this;
    }
    */

    /**
     * Gets foundingLocation.
     *
     * @return Place
     */
    /*
    public function getFoundingLocation()
    {
        return $this->foundingLocation;
    }
    */

    /**
     * Sets precedingOrganization.
     *
     * @param Organization $precedingOrganization
     *
     * @return $this
     */
    /*
    public function setPrecedingOrganization(Organization $precedingOrganization = null)
    {
        $this->precedingOrganization = $precedingOrganization;

        return $this;
    }
    */

    /**
     * Gets precedingOrganization.
     *
     * @return Organization
     */
    /*
    public function getPrecedingOrganization()
    {
        return $this->precedingOrganization;
    }
    */

    /**
     * Gets succeedingOrganization.
     *
     * @return Organization
     */
    /*
    public function getSucceedingOrganization()
    {
        return $this->succeedingOrganization;
    }
    */

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
     * Sets foundingLocation.
     *
     * @param Place $foundingLocation
     *
     * @return $this
     */
    /*
    public function setFoundingLocation(Place $foundingLocation = null)
    {
        $this->foundingLocation = $foundingLocation;

        return $this;
    }
    */

    /**
     * Gets foundingLocation.
     *
     * @return Place
     */
    /*
    public function getFoundingLocation()
    {
        return $this->foundingLocation;
    }
    */

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

    public function findBibitems($em, $catalogueEntriesOnly = false)
    {
        $qb = $em->createQueryBuilder();
        $qb->select([
            'B',
            'BH.signature', 'BH.url',
            "COALESCE(B.author, B.editor) HIDDEN creatorSort",
        ])
            ->distinct()
            ->from('AppBundle\Entity\Bibitem', 'B')
            ->innerJoin('AppBundle\Entity\BibitemHolder', 'BH', 'WITH', 'BH.bibitem=B')
            ->innerJoin('AppBundle\Entity\Holder', 'H', 'WITH', 'BH.holder=H')
            ->where('H = :holder AND B.status <> -1')
            ->orderBy('B.datePublished, creatorSort, B.title');

        if ($catalogueEntriesOnly) {
            $qb->innerJoin(
                'AppBundle\Entity\BibitemExhibition',
                'BE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'BE.bibitem = B AND BE.role = 1'
            ); // catalogues only
        }

        $results = $qb->getQuery()
            ->setParameter('holder', $this)
            ->getResult();

        return $results;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gnd' => $this->gnd,
            'url' => $this->url,
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

        /*
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
        */

        $description = $this->getDescription();
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
