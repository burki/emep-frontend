<?php

// src/AppBundle/Entity/User.php
namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Table(name="User")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UserRepository")
 */
class User
implements UserInterface, \Serializable
{
    static $ROLE_TO_PRIVS = [
        'ROLE_EXPERT' => 0x10, // access to works
        'ROLE_ADMIN' => 0x200, // additional rights to edit / delete / publish
    ];

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=254)
     */
    private $email;

    /**
     * @ORM\Column(name="pwd", type="string", length=64)
     */
    private $password;

    /**
     * @ORM\Column(type="integer")
     */
    private $status;

    /**
     * @ORM\Column(type="integer")
     */
    private $privs;

    public function __construct()
    {
        $this->status = 0;
    }

    public function getUsername()
    {
        return $this->email;
    }

    public function getSalt()
    {
        // you *may* need a real salt depending on your encoder
        // see section on salt below
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getPassword()
    {
        return $this->password;
    }

    /* don't update on '' so we don't update when the form-field is empty */
    public function setPassword($password = '')
    {
        if (is_null($password)) {
            $this->password = null;
        }
        if ('' !== $password) {
            $this->password = $password;
        }
    }

    public function getRoles()
    {
        if ($this->status < 0) {
            return [];
        }

        $roles = [ 'ROLE_USER' ];

        if (0 != ($this->privs & self::$ROLE_TO_PRIVS['ROLE_ADMIN'])) {
            // admin gets all roles
            $roles = array_merge($roles, array_keys(self::$ROLE_TO_PRIVS));
        }
        else {
            foreach (self::$ROLE_TO_PRIVS as $role => $mask) {
                if (0 != ($this->privs & $mask)) {
                    $roles[] = $role;
                }
            }
        }

        return $roles;
    }

    public function eraseCredentials()
    {
    }

    /** @see \Serializable::serialize() */
    public function serialize()
    {
        return serialize([
            $this->id,
            $this->email,
            $this->password,
            // see section on salt below
            // $this->salt,
        ]);
    }

    /** @see \Serializable::unserialize() */
    public function unserialize($serialized)
    {
        list (
            $this->id,
            $this->email,
            $this->password,
            // see section on salt below
            // $this->salt
        ) = unserialize($serialized);
    }
}
