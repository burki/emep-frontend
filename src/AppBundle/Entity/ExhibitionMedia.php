<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ExhibitionMedia
 *
 * @ORM\Entity
 */
class ExhibitionMedia extends Media
{
    /**
     *
     * @var Item
     *
     * @ORM\ManyToOne(targetEntity="Exhibition", inversedBy="media", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id")
     */
    protected $exhibition;

    public function getPathPrefix()
    {
        return 'exhibition';
    }

    public function getReferencedId()
    {
        return $this->exhibition->getId();
    }
}
