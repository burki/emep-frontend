<?php

// src/AppBundle/Repository/UserRepository.php

namespace AppBundle\Repository;

use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Doctrine\ORM\EntityRepository;

use AppBundle\Entity\User;

class UserRepository
extends EntityRepository
implements UserLoaderInterface
{
    // see https://symfony.com/doc/5.4/security/user_providers.html
    public function loadUserByIdentifier(string $usernameOrEmail): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email AND u.status <> -1')
            ->setParameter('email', $usernameOrEmail)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @deprecated since Symfony 5.3 */
    public function loadUserByUsername(string $usernameOrEmail): ?User
    {
        return $this->loadUserByIdentifier($usernameOrEmail);
    }
}