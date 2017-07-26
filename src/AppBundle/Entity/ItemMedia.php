<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ItemMedia
 *
 * @ORM\Entity
 */
class ItemMedia extends Media
{
    /**
     *
     * @var Item
     *
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="media", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id")
     */
    protected $item;

    public function getPathPrefix()
    {
        return 'item';
    }

    public function getReferencedId()
    {
        return $this->item->getId();
    }
}
