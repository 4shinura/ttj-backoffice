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

class UserController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiBaseUrl = "http://localhost:5555/api",
    ) {}

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

    #[Route('/admin/users/{id}', name: 'admin_user_show')]
    public function showUser(int $id, Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        $user = $this->getUserById($id, $request);
        if ($user === null) {
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_show.html.twig', ['user' => $user]);
    }

    #[Route('/admin/users/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        $user = [
            'nom' => '',
            'prenom' => '',
            'email' => '',
            'type' => 'utilisateur',
            'statut' => 'actif',
            'password' => '',
        ];
        $error = null;

        if ($request->isMethod('POST')) {
            $payload = $this->buildUserPayload($request, false);

            try {
                $this->httpClient->request('POST', $this->apiBaseUrl . '/admin/utilisateurs', [
                    'headers' => $this->bearerHeaders($request),
                    'json' => $payload,
                ]);

                return $this->redirectToRoute('admin_users');
            } catch (HttpExceptionInterface $e) {
                $error = $e->getMessage();
            } catch (TransportExceptionInterface) {
                $error = 'Erreur réseau : impossible de contacter l\'API';
            } catch (Exception) {
                $error = 'Impossible de créer cet utilisateur';
            }

            $user = array_merge($user, $payload);
        }

        return $this->render('admin/user_form.html.twig', [
            'formTitle' => 'Ajouter un utilisateur',
            'actionPath' => 'admin_user_new',
            'user' => $user,
            'error' => $error,
            'isEdit' => false,
        ]);
    }

    #[Route('/admin/users/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(int $id, Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        $error = null;
        $user = $this->getUserById($id, $request);

        if ($user === null) {
            return $this->redirectToRoute('admin_users');
        }

        if ($request->isMethod('POST')) {
            $payload = $this->buildUserPayload($request, true);

            try {
                $this->httpClient->request('PUT', $this->apiBaseUrl . '/admin/utilisateurs/' . $id, [
                    'headers' => $this->bearerHeaders($request),
                    'json' => $payload,
                ]);

                return $this->redirectToRoute('admin_users');
            } catch (HttpExceptionInterface $e) {
                $error = $e->getMessage();
            } catch (TransportExceptionInterface) {
                $error = 'Erreur réseau : impossible de contacter l\'API';
            } catch (Exception) {
                $error = 'Impossible de modifier cet utilisateur';
            }

            $user = array_merge($user, $payload);
        }

        return $this->render('admin/user_form.html.twig', [
            'formTitle' => 'Modifier un utilisateur',
            'actionPath' => 'admin_user_edit',
            'user' => $user,
            'error' => $error,
            'isEdit' => true,
        ]);
    }

    #[Route('/admin/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(int $id, Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $this->httpClient->request('DELETE', $this->apiBaseUrl . '/admin/utilisateurs/' . $id, [
                'headers' => $this->bearerHeaders($request),
            ]);
        } catch (Exception) {
            // ignore et redirige vers la liste
        }

        return $this->redirectToRoute('admin_users');
    }

    private function getUserById(int $id, Request $request): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/admin/utilisateurs/' . $id, [
                'headers' => $this->bearerHeaders($request),
            ]);
            return $response->toArray();
        } catch (Exception) {
            return null;
        }
    }

    private function buildUserPayload(Request $request, bool $isEdit): array
    {
        $payload = [
            'nom' => $request->request->get('nom', ''),
            'prenom' => $request->request->get('prenom', ''),
            'email' => $request->request->get('email', ''),
            'type' => $request->request->get('type', 'utilisateur'),
            'statut' => $request->request->get('statut', 'actif'),
        ];

        $password = $request->request->get('password', '');
        if (!$isEdit || $password !== '') {
            $payload['password'] = $password;
        }

        return $payload;
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
}