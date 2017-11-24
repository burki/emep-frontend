<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ItemExhibitionMedia
 *
 * @ORM\Entity
 */
class ItemExhibitionMedia extends Media
{
    /**
     *
     * @var ItemExhibition
     *
     * @ORM\ManyToOne(targetEntity="ItemExhibition", inversedBy="media", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id")
     */
    protected $itemexhibition;

    public function getPathPrefix()
    {
        return 'catalogue';
    }

    public function getReferencedId()
    {
        return $this->itemexhibition->getId();
    }
}
