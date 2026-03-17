<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Required by PublicKeyCredentialUserEntityRepositoryInterface
     */
    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $user = $this->findOneBy(['username' => $username]);
        if (!$user) {
            return null;
        }
        return new PublicKeyCredentialUserEntity(
            $user->getUsername(),
            $user->getUserHandle()->toBinary(),
            $user->getUsername()
            );
    }

    /**
     * Required by PublicKeyCredentialUserEntityRepositoryInterface
     */
    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.userHandle = :handle')
            ->setParameter('handle', $userHandle)
            ->setMaxResults(1);

        $user = $qb->getQuery()->getOneOrNullResult();
        if (!$user) {
            return null;
        }
        return new PublicKeyCredentialUserEntity(
            $user->getUsername(),
            $user->getUserHandle()->toBinary(),
            $user->getUsername()
            );
    }
}