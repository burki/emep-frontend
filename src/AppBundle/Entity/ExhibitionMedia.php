<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PersonMedia
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
}
