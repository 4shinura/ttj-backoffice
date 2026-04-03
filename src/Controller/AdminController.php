<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Exception;
class AdminController extends AbstractController
{
    private HttpClientInterface $httpClient;
    private string $apiBaseUrl;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiBaseUrl = 'http://172.16.4.25:5555/api'; // Adjust if needed
    }

    private function isLoggedIn(SessionInterface $session): bool
    {
        return $session->has('admin_token');
    }

    private function getApiHeaders(SessionInterface $session): array
    {
        return [
            'Cookie' => 'access_token=' . $session->get('admin_token'),
        ];
    }

    #[Route('/', name: 'redirect_to_login', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_login');
    }

    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request, SessionInterface $session): Response
    {
        if ($this->isLoggedIn($session)) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            try {
                $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/auth/login', [
                    'json' => [
                        'email' => $email,
                        'password' => $password,
                    ],
                ]);

                $status = $response->getStatusCode();
                // throw new Exception($status);
                $data = $response->toArray(false);

                if ($status === 200 && isset($data['user'])) {
                    if ($data['user']['type'] === 'administrateur') {
                        $session->set('admin_token', $data['user']['id']);
                        return $this->redirectToRoute('admin_dashboard');
                    }
                    $error = 'Accès refusé : compte non administrateur';
                } elseif ($status === 401) {
                    $error = 'Email ou mot de passe incorrect';
                } else {
                    $error = $data['error'] ?? 'Erreur de connexion au serveur';
                }
            } catch (HttpExceptionInterface $e) {
                if ($e->getCode() === 401) {
                    $error = 'Email ou mot de passe incorrect';
                } else {
                    $error = 'Erreur de connexion au serveur';
                }
            } catch (TransportExceptionInterface $e) {
                $error = 'Erreur réseau : impossible de contacter l’API';
            }
        }

        return $this->render('admin/login.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(SessionInterface $session): Response
    {
        $session->remove('admin_token');
        return $this->redirectToRoute('admin_login');
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(SessionInterface $session): Response
    {
        if (!$this->isLoggedIn($session)) {
            return $this->redirectToRoute('admin_login');
        }

        $pendingUsers = [];
        $pendingOffers = [];

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/registers/', [
                'headers' => $this->getApiHeaders($session),
            ]);
            $pendingUsers = $response->toArray(false);
        } catch (\Exception $e) {
            $pendingUsers = [];
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/offres', [
                'headers' => $this->getApiHeaders($session),
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
    public function users(SessionInterface $session): Response
    {
        if (!$this->isLoggedIn($session)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/utilisateurs', [
                'headers' => $this->getApiHeaders($session),
            ]);

            $users = $response->toArray();
        } catch (\Exception $e) {
            $users = [];
        }

        return $this->render('admin/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/pending-registrations', name: 'admin_pending_registrations')]
    public function pendingRegistrations(SessionInterface $session): Response
    {
        if (!$this->isLoggedIn($session)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/registers/', [
                'headers' => $this->getApiHeaders($session),
            ]);

            $pendingUsers = $response->toArray();
        } catch (\Exception $e) {
            $pendingUsers = [];
        }

        return $this->render('admin/pending_registrations.html.twig', [
            'pendingUsers' => $pendingUsers,
        ]);
    }

    #[Route('/admin/validate-registration/{id}', name: 'admin_validate_registration', methods: ['POST'])]
    public function validateRegistration(int $id, SessionInterface $session): Response
    {
        if (!$this->isLoggedIn($session)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $this->httpClient->request('PUT', $this->apiBaseUrl . '/admin/registers/valider', [
                'headers' => array_merge($this->getApiHeaders($session), [
                    'Content-Type' => 'application/json',
                ]),
                'body' => json_encode(['id' => $id]),
            ]);
        } catch (\Exception $e) {
            // Handle error
        }

        return $this->redirectToRoute('admin_pending_registrations');
    }

    #[Route('/admin/pending-offers', name: 'admin_pending_offers')]
    public function pendingOffers(SessionInterface $session): Response
    {
        if (!$this->isLoggedIn($session)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/offres', [
                'headers' => $this->getApiHeaders($session),
            ]);

            $pendingOffers = $response->toArray();
        } catch (\Exception $e) {
            $pendingOffers = [];
        }

        return $this->render('admin/pending_offers.html.twig', [
            'pendingOffers' => $pendingOffers,
        ]);
    }

    #[Route('/admin/publish-offer/{id}', name: 'admin_publish_offer', methods: ['POST'])]
    public function publishOffer(int $id, SessionInterface $session): Response
    {
        if (!$this->isLoggedIn($session)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $this->httpClient->request('PUT', $this->apiBaseUrl . '/admin/offres/' . $id . '/publish', [
                'headers' => $this->getApiHeaders($session),
            ]);
        } catch (\Exception $e) {
            // Handle error
        }

        return $this->redirectToRoute('admin_pending_offers');
    }
}