<?php

namespace App\Repository;

use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\Bundle\Repository\PublicKeyCredentialSourceRepositoryInterface;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 */
class WebauthnCredentialRepository extends ServiceEntityRepository implements PublicKeyCredentialSourceRepositoryInterface
{
    private SerializerInterface $serializer;

    public function __construct(ManagerRegistry $registry, WebauthnSerializerFactory $serializerFactory)
    {
        parent::__construct($registry, WebauthnCredential::class);
        $this->serializer = $serializerFactory->create();
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $credential = $this->find(base64_encode($publicKeyCredentialId));

        if (!$credential instanceof WebauthnCredential) {
            return null;
        }

        return $this->serializer->deserialize($credential->getCredentialData(), PublicKeyCredentialSource::class , 'json');
    }

    public function findAllForUserEntity(\Webauthn\PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $userHandle = $publicKeyCredentialUserEntity->getId();
        // Assuming we're looking up user by username (which is email here based on standard symfony behavior)

        $credentials = $this->createQueryBuilder('c')
            ->join('c.user', 'u')
            ->andWhere('u.username = :username')
            ->setParameter('username', $publicKeyCredentialUserEntity->getName())
            ->getQuery()
            ->getResult();

        return array_map(function (WebauthnCredential $credential) {
            return $this->serializer->deserialize($credential->getCredentialData(), PublicKeyCredentialSource::class , 'json');
        }, $credentials);
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $id = base64_encode($publicKeyCredentialSource->publicKeyCredentialId);

        $credential = $this->find($id);
        if (!$credential) {
            $credential = new WebauthnCredential();
            $credential->setId($id);
        // The user must be set separately during registration, since PublicKeyCredentialSource doesn't have it explicitly bound to our User entity object here easily without lookup
        // This method is called by the bundle during login to update usage counter, we just update it
        }

        $credential->setCredentialData($this->serializer->serialize($publicKeyCredentialSource, 'json'));

        $this->getEntityManager()->persist($credential);
        $this->getEntityManager()->flush();
    }

    public function saveCredential(User $user, PublicKeyCredentialSource $source): void
    {
        $credential = new WebauthnCredential();
        $credential->setId(base64_encode($source->publicKeyCredentialId));
        $credential->setUser($user);
        $credential->setCredentialData($this->serializer->serialize($source, 'json'));
        $credential->setName('Passkey'); // Default name

        $this->getEntityManager()->persist($credential);
        $this->getEntityManager()->flush();
    }

    public function findByCredentialId(string $publicKeyCredentialId): ?WebauthnCredential
    {
        return $this->find(base64_encode($publicKeyCredentialId));
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}