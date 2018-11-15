<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BibitemExhibition
 *
 * @ORM\Table(name="ExhibitionPublication")
 * @ORM\Entity
 */
class BibitemExhibition
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
     * @var Bibitem
     *
     * @ORM\ManyToOne(targetEntity="Bibitem")
     * @ORM\JoinColumn(name="id_publication", referencedColumnName="id")
     */
    private $bibitem;

    /**
     * @var Exhibition
     *
     * @ORM\ManyToOne(targetEntity="Exhibition")
     * @ORM\JoinColumn(name="id_exhibition", referencedColumnName="id")
     */
    private $exhibition;

    /**
     * @var integer
     *
     * @ORM\Column(name="role", type="integer", nullable=false)
     */
    private $role;

    /* custom accessor */
    public function getBibitem()
    {
        return $this->bibitem;
    }

    public function setBibitem($bibitem)
    {
        $this->bibitem = $bibitem;

        return $this;
    }

    public function getExhibition()
    {
        return $this->exhibition;
    }

    public function setExhibition($exhibition)
    {
        $this->exhibition = $exhibition;

        return $this;
    }

    public function getRole() {
        return $this->role;
    }

    public function setRole($role) {
        $this->role = $role;

        return $this;
    }
}
