<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations

use Symfony\Component\Validator\Constraints as Assert;

/**
 * UserItem
 *
 * @ORM\Table(name="UserItem")
 * @ORM\Entity
 */
class UserItem
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
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="id_user", referencedColumnName="id")
     * @var User
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="Item")
     * @ORM\JoinColumn(name="id_item", referencedColumnName="id")
     * @var Item
     */
    private $item;

    /**
     * @ORM\ManyToOne(targetEntity="Term", cascade={"all"}, fetch="EAGER")
     * @ORM\JoinColumn(name="style", referencedColumnName="id")
     * @Assert\NotNull
     */
    private $style;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created", type="datetime")
     */
    protected $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="changed", type="datetime")
     */
    protected $changedAt;

    /* make private properties public through a generic __get / __set */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }

    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            return $this->$name = $value;
        }
    }

    /* custom accessor */
    public function getId()
    {
        return $this->id;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getItem()
    {
        return $this->item;
    }

    public function setItem(Item $item)
    {
        $this->item = $item;
    }

    public function getStyle()
    {
        return $this->style;
    }

    public function setStyle(Term $style)
    {
        $this->style = $style;
    }
}
