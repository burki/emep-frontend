<?php

// src/AppBundle/Entity/UserAction.php
namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Gedmo\Mapping\Annotation as Gedmo; // this will be like an alias for Gedmo extensions annotations

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="UserAction")
 *
 */
class UserAction
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="id_user", referencedColumnName="id")
     * @var User
     */
    private $user;

    /**
     * @ORM\Column(type="integer")
     */
    private $status;

    /**
     * @var string The name of the action.
     *
     * @Assert\Type(type="string")
     * @ORM\Column(nullable=true)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private $route;

    /**
    * @ORM\Column(name="route_params", type="json_array", nullable=true)
    */
    protected $routeParams;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created", type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="changed", type="datetime")
     */
    protected $changedAt;

    public function __construct()
    {
        $this->status = 0;
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

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function setRoute($route)
    {
        $this->route = $route;

        return $this;
    }

    public function getRouteParams()
    {
        return $this->routeParams;
    }

    public function setRouteParams(array $routeParams)
    {
        $this->routeParams = $routeParams;
    }

}
