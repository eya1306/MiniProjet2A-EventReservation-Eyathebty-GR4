<?php
namespace App\Controller;

use App\Entity\User;
use App\Service\PasskeyAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenManagerInterface $refreshManager,
        private RefreshTokenGeneratorInterface $refreshGenerator,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/register/options', methods: ['POST'])]
    public function registerOptions(
        Request $request,
        PasskeyAuthService $passkeyService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;

        if (!$username) {
            return $this->json(['error' => 'Username/Email requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $username]);
        
        try {
            if (!$user) {
                 $user = new User();
                 $user->setUsername($username);
                 $user->setRoles(['ROLE_USER']);
                 // Password is null for passkey-only users implicitly created here
                 $this->entityManager->persist($user);
                 $this->entityManager->flush(); // Need an ID/Handle for passkeys
            }
            $options = $passkeyService->getRegistrationOptions($user);
            return $this->json($options);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/register/verify', methods: ['POST'])]
    public function registerVerify(
        Request $request,
        PasskeyAuthService $passkeyService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;
        $credential = $data['credential'] ?? null;

        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if (!$user || !$credential) {
            return $this->json(['error' => 'Données invalides'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Re-encode JSON string expected by WebAuthn lib
            $passkeyService->verifyRegistration(json_encode($credential), $user);

            $jwt = $this->jwtManager->create($user);
            $refresh = $this->refreshGenerator->createForUserWithTtl($user, 2592000);
            $this->refreshManager->save($refresh);

            return $this->json([
                'success' => true,
                'token' => $jwt,
                'refresh_token' => $refresh->getRefreshToken(),
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername()
                ]
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login/options', methods: ['POST'])]
    public function loginOptions(PasskeyAuthService $passkeyService): JsonResponse
    {
        try {
            return $this->json($passkeyService->getLoginOptions());
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login/verify', methods: ['POST'])]
    public function loginVerify(
        Request $request,
        PasskeyAuthService $passkeyService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $credential = $data['credential'] ?? null;

        if (!$credential) {
            return $this->json(['error' => 'Credential requis'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Re-encode JSON string expected by WebAuthn lib
            $user = $passkeyService->verifyLogin(json_encode($credential));

            $jwt = $this->jwtManager->create($user);
            $refresh = $this->refreshGenerator->createForUserWithTtl($user, 2592000);
            $this->refreshManager->save($refresh);

            return $this->json([
                'success' => true,
                'token' => $jwt,
                'refresh_token' => $refresh->getRefreshToken(),
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername()
                ]
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles()
        ]);
    }
}