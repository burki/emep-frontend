<?php

// src/AppBundle/Repository/UserRepository.php

namespace AppBundle\Repository;

use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Doctrine\ORM\EntityRepository;

class UserRepository
extends EntityRepository
implements UserLoaderInterface
{
    // see https://symfony.com/doc/3.4/security/entity_provider.html
    public function loadUserByUsername($username)
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email AND u.status <> -1')
            ->setParameter('email', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }
}