<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    // ─── User Login (form-based) ────────────────────────────────────────────
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_events_index');
        }
        $error        = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Handled by Symfony firewall
    }

    // ─── User Registration ───────────────────────────────────────────────────
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_events_index');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $username        = trim($request->request->get('username', ''));
            $password        = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');

            if (empty($username) || empty($password)) {
                $error = 'Username and password are required.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Passwords do not match.';
            } elseif ($userRepo->findOneBy(['username' => $username])) {
                $error = 'Username already taken.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                $user = new User();
                $user->setUsername($username);
                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Account created! You can now login.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', ['error' => $error]);
    }

    // ─── JWT Login API endpoint ──────────────────────────────────────────────
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function apiLogin(
        Request $request,
        UserRepository $userRepo,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwt
    ): JsonResponse {
        $data     = json_decode($request->getContent(), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $user = $userRepo->findOneBy(['username' => $username]);

        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $jwt->create($user);

        return $this->json(['token' => $token, 'username' => $user->getUserIdentifier()]);
    }

    // ─── Passkey Session Bridge ──────────────────────────────────────────────
    // After passkey registration/login via the stateless JWT API, JS calls this endpoint
    // with the JWT token. We decode it, look up the user, and log them into the Symfony session.
    #[Route('/auth/passkey-session', name: 'app_passkey_session', methods: ['POST'])]
    public function passkeySession(
        Request $request,
        UserRepository $userRepo,
        JWTEncoderInterface $jwtEncoder,
        \Symfony\Component\HttpFoundation\RequestStack $requestStack,
        \Symfony\Bundle\SecurityBundle\Security $security
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            return $this->json(['error' => 'Token required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload  = $jwtEncoder->decode($token);
            $username = $payload['username'] ?? null;

            if (!$username) {
                return $this->json(['error' => 'Invalid token payload'], Response::HTTP_UNAUTHORIZED);
            }

            $user = $userRepo->findOneBy(['username' => $username]);
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            // Manually log the user in on the 'main' firewall using the form_login authenticator
            $security->login($user, 'security.authenticator.form_login.main', 'main');

            // Explicitly migrate and save the session so the session cookie is generated and sent back
            $session = $requestStack->getSession();
            $session->migrate();
            $session->save();

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}