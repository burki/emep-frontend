<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations

/**
* The Country
*
 * @ORM\Entity
 * @ORM\Table(name="Country")
*/
class Country
{
    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(name="cc", type="string", nullable=false)
    */
    protected $countryCode;

    /**
    * @ORM\Column(type="string", nullable=false)
    */
    protected $name;

    /**
    * *ORM\Column(type="string", nullable=true)
    */
    protected $geonames;

    /**
     * Sets countryCode (iso2).
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
}
