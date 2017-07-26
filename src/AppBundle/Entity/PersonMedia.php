<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PersonMedia
 *
 * @ORM\Entity
 */
class PersonMedia extends Media
{
    /**
     *
     * @var Item
     *
     * @ORM\ManyToOne(targetEntity="Person", inversedBy="media", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id")
     */
    protected $person;
}
