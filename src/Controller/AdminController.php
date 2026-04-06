<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Exception;

class AdminController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiBaseUrl = "http://localhost:5555/api",
    ) {}

    #[Route('/', name: 'admin_redirect')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_login');
    }

    // ----------------------------------------------------------------
    // Login — récupère le token, le décode, le stocke en cookie
    // ----------------------------------------------------------------

    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            try {
                $apiResponse = $this->httpClient->request('POST', $this->apiBaseUrl . '/auth/login', [
                    'json' => [
                        'email'    => $request->request->get('email'),
                        'password' => $request->request->get('password'),
                    ],
                ]);

                $status = $apiResponse->getStatusCode();
                $data   = $apiResponse->toArray(false);

                if ($status === 200 && isset($data['token'])) {
                    $payload = $this->jwt_decode($data['token']);

                    if (!$payload) {
                        $error = 'Token invalide reçu de l\'API';
                    } elseif ($payload['user']['type'] !== 'administrateur') {
                        $error = 'Accès refusé : compte non administrateur';
                    } else {
                        $response = $this->redirectToRoute('admin_dashboard');
                        $response->headers->setCookie($this->buildCookie($data['token']));
                        return $response;
                    }
                } elseif ($status === 401) {
                    $error = 'Email ou mot de passe incorrect';
                } else {
                    $error = $data['error'] ?? 'Erreur de connexion au serveur';
                }
            } catch (HttpExceptionInterface $e) {
                $error = $e->getCode() === 401
                    ? 'Email ou mot de passe incorrect'
                    : 'Erreur de connexion au serveur';
            } catch (TransportExceptionInterface) {
                $error = 'Erreur réseau : impossible de contacter l\'API';
            }
        }

        return $this->render('admin/login.html.twig', ['error' => $error]);
    }

    // ----------------------------------------------------------------
    // Exemple de route protégée — envoie le token en Bearer à l'API
    // ----------------------------------------------------------------

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function dashboard(Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        $pendingUsers = [];
        $pendingOffers = [];

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/registers/', [
                'headers' => $this->bearerHeaders($request),
            ]);
            $pendingUsers = $response->toArray(false);
        } catch (\Exception $e) {
            $pendingUsers = [];
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/offres', [
                'headers' => $this->bearerHeaders($request),
            ]);
            $pendingOffers = $response->toArray(false);
        } catch (\Exception $e) {
            $pendingOffers = [];
        }

        return $this->render('admin/dashboard.html.twig', [
            'pendingUsers' => $pendingUsers,
            'pendingOffers' => $pendingOffers,
        ]);
    }

   #[Route('/admin/users', name: 'admin_users')]
    public function users(Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/utilisateurs', [
                'headers' => $this->bearerHeaders($request),
            ]);
            $users = $response->toArray();
        } catch (\Exception) {
            $users = [];
        }

        return $this->render('admin/users.html.twig', ['users' => $users]);
    }

    #[Route('/admin/pending-registrations', name: 'admin_pending_registrations')]
    public function pendingRegistrations(Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/registers', [
                'headers' => $this->bearerHeaders($request),
            ]);
            $pendingUsers = $response->toArray();
        } catch (\Exception) {
            $pendingUsers = [];
        }

        return $this->render('admin/pending_registrations.html.twig', ['pendingUsers' => $pendingUsers]);
    }

    #[Route('/admin/validate-registration/{id}', name: 'admin_validate_registration', methods: ['POST'])]
    public function validateRegistration(int $id, Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $this->httpClient->request('PUT', $this->apiBaseUrl . '/admin/registers/valider', [
                'headers' => $this->bearerHeaders($request),
                'json'    => ['id' => $id],
            ]);
        } catch (\Exception) {}

        return $this->redirectToRoute('admin_pending_registrations');
    }

    #[Route('/admin/pending-offers', name: 'admin_pending_offers')]
    public function pendingOffers(Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/offres', [
                'headers' => $this->bearerHeaders($request),
            ]);
            $pendingOffers = $response->toArray();
        } catch (\Exception) {
            $pendingOffers = [];
        }

        return $this->render('admin/pending_offers.html.twig', ['pendingOffers' => $pendingOffers]);
    }

    #[Route('/admin/publish-offer/{id}', name: 'admin_publish_offer', methods: ['POST'])]
    public function publishOffer(int $id, Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $this->httpClient->request('PUT', $this->apiBaseUrl . '/admin/offres/' . $id . '/publish', [
                'headers' => $this->bearerHeaders($request),
            ]);
        } catch (\Exception) {}

        return $this->redirectToRoute('admin_pending_offers');
    }

    // ----------------------------------------------------------------
    // Logout — supprime le cookie
    // ----------------------------------------------------------------

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): Response
    {
        $response = $this->redirectToRoute('admin_login');
        $response->headers->clearCookie('access_token', '/');
        return $response;
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /** Vérifie qu'un token est présent dans les cookies */
    private function isLoggedIn(Request $request): bool
    {
        return $request->cookies->has('access_token');
    }

    /** Construit le header Authorization: Bearer pour l'API */
    private function bearerHeaders(Request $request): array
    {
        return [
            'Authorization' => 'Bearer ' . $request->cookies->get('access_token'),
        ];
    }

    /** Stocke le token JWT dans un cookie HttpOnly */
    private function buildCookie(string $token): Cookie
    {
        return Cookie::create('access_token')
            ->withValue($token)
            ->withExpires(time() + 3600)
            ->withPath('/')
            ->withSecure(false)         // true en production (HTTPS)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    /** Décode le payload du JWT (sans vérifier la signature côté client) */
    private function jwt_decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode(base64_decode($parts[1]), true);
        return is_array($payload) ? $payload : null;
    }
}