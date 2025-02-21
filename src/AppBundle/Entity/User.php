<?php

// src/AppBundle/Entity/User.php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Table(name="User")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UserRepository")
 * @UniqueEntity(fields="email", message="This E-Mail is already registered")
 * */
class User implements UserInterface, PasswordAuthenticatedUserInterface, \Serializable
{
    static $ROLE_TO_PRIVS = [
        'ROLE_EXPERT' => 0x10, // access to works
        'ROLE_ADMIN' => 0x200, // additional rights to edit / delete / publish
    ];

    public static function generateToken($numBytes = 20)
    {
        return rtrim(strtr(base64_encode(random_bytes($numBytes)), '+/', '-_'), '=');
    }

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
     * @Assert\NotBlank()
     * @Assert\Length(min=6, max=4096)
     */
    private $plainPassword;

    /**
     * @ORM\Column(name="pwd", type="string", length=64)
     */
    private $password;

    /**
    * @ORM\Column(name="recover", type="string", length=32, nullable=true)
    */
    private $confirmationToken;

    /**
     * @ORM\Column(type="integer")
     */
    private $status;

    /**
     * @ORM\Column(type="integer")
     */
    private $privs = 0;

    public function __construct()
    {
        $this->status = 0;
    }

    public function getUserIdentifier(): string
    {
        return $this->getEmail();
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->email;
    }

    public function getSalt()
    {
        // you *may* need a real salt depending on your encoder
        // see section on salt below
        return null;
    }

    public function getPlainPassword()
    {
        return $this->plainPassword;
    }

    public function setPlainPassword($password)
    {
        $this->plainPassword = $password;
    }

    /**
     * @inheritDoc
     */
    public function getPassword(): ?string
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

    public function getConfirmationToken()
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken($confirmationToken)
    {
        $this->confirmationToken = $confirmationToken;

        return $this;
    }

    public function setGeneratedConfirmationToken()
    {
        $this->setConfirmationToken(self::generateToken());

        return $this;
    }

    public function getRoles(): array
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

    public function eraseCredentials() {}

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
        [
            $this->id,
            $this->email,
            $this->password,
            // see section on salt below
            // $this->salt
        ] = unserialize($serialized);
    }
}
