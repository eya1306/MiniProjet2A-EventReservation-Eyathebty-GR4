<?php
namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\Bundle\Service\PublicKeyCredentialCreationOptionsFactory;
use Webauthn\Bundle\Service\PublicKeyCredentialRequestOptionsFactory;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

class PasskeyAuthService
{
    private SerializerInterface $serializer;

    public function __construct(
        private PublicKeyCredentialCreationOptionsFactory $creationFactory,
        private PublicKeyCredentialRequestOptionsFactory $requestFactory,
        private AuthenticatorAttestationResponseValidator $attestationValidator,
        private AuthenticatorAssertionResponseValidator $assertionValidator,
        WebauthnSerializerFactory $serializerFactory,
        private RequestStack $requestStack,
        private WebauthnCredentialRepository $credRepo
        )
    {
        $this->serializer = $serializerFactory->create();
    }

    public function getRegistrationOptions(User $user): array
    {
        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getUsername(),
            $user->getUserHandle()->toBinary(),
            $user->getUsername()
            );

        $options = $this->creationFactory->create('default', $userEntity, $this->getExcludedCredentials($user));

        $optionsJson = $this->serializer->serialize($options, 'json');
        $this->requestStack->getSession()->set('webauthn_registration', $optionsJson);

        return json_decode($optionsJson, true);
    }

    public function verifyRegistration(string $responseJson, User $user): void
    {
        $optionsJson = $this->requestStack->getSession()->get('webauthn_registration');
        if (!$optionsJson)
            throw new \Exception('No registration options in session');

        /** @var PublicKeyCredentialCreationOptions $options */
        $options = $this->serializer->deserialize($optionsJson, PublicKeyCredentialCreationOptions::class , 'json');

        /** @var PublicKeyCredential $pkc */
        $pkc = $this->serializer->deserialize($responseJson, PublicKeyCredential::class , 'json');

        $response = $pkc->response;
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \Exception('Invalid response type');
        }

        $host = $this->requestStack->getCurrentRequest()->getHost();

        $credentialSource = $this->attestationValidator->check($response, $options, $host);

        $this->credRepo->saveCredential($user, $credentialSource);
        $this->requestStack->getSession()->remove('webauthn_registration');
    }

    public function getLoginOptions(): array
    {
        $options = $this->requestFactory->create('default', []);

        $optionsJson = $this->serializer->serialize($options, 'json');
        $this->requestStack->getSession()->set('webauthn_login', $optionsJson);

        return json_decode($optionsJson, true);
    }

    public function verifyLogin(string $responseJson): User
    {
        $optionsJson = $this->requestStack->getSession()->get('webauthn_login');
        if (!$optionsJson)
            throw new \Exception('No login options in session');

        /** @var PublicKeyCredentialRequestOptions $options */
        $options = $this->serializer->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class , 'json');

        /** @var PublicKeyCredential $pkc */
        $pkc = $this->serializer->deserialize($responseJson, PublicKeyCredential::class , 'json');

        $response = $pkc->response;
        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \Exception('Invalid response type');
        }

        $host = $this->requestStack->getCurrentRequest()->getHost();

        $entity = $this->credRepo->findByCredentialId($pkc->rawId);
        if (!$entity) {
            throw new \Exception("Credential not found");
        }

        $credentialSource = $this->assertionValidator->check(
            $this->serializer->deserialize($entity->getCredentialData(), PublicKeyCredentialSource::class , 'json'),
            $response,
            $options,
            $host,
            $entity->getUser()->getUserHandle()->toBinary()
        );

        $entity->touch();
        $this->credRepo->flush();
        $this->requestStack->getSession()->remove('webauthn_login');

        return $entity->getUser();
    }

    private function getExcludedCredentials(User $user): array
    {
        $credentials = $this->credRepo->findBy(['user' => $user]);
        $excluded = [];
        foreach ($credentials as $cred) {
            // To ensure a user doesn't register the same authenticator twice
            $source = $this->serializer->deserialize($cred->getCredentialData(), PublicKeyCredentialSource::class , 'json');
            $excluded[] = $source->getPublicKeyCredentialDescriptor();
        }
        return $excluded;
    }
}