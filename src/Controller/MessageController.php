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

class MessageController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiBaseUrl = "http://localhost:5555/api",
    ) {}

    #[Route('/admin/messages', name: 'message_index')]
    public function index(Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/users/messages/correspondants', [
                'headers' => $this->bearerHeaders($request),
            ]);
            $correspondents = $response->toArray();
        } catch (\Exception) {
            $correspondents = [];
        }

        return $this->render('message/index.html.twig', ['correspondents' => $correspondents]);
    }

    #[Route('/admin/messages/new', name: 'message_new', methods: ['GET'])]
    public function newMessage(Request $request): Response
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

        $preselectedId = $request->query->get('destinataireId');

        return $this->render('message/new.html.twig', [
            'users' => $users,
            'preselectedId' => $preselectedId,
            'error' => null,
        ]);
    }

    #[Route('/admin/messages/send/{destinataireId}', name: 'message_send_form', methods: ['GET'])]
    public function sendForm(int $destinataireId, Request $request): Response
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

        return $this->render('message/new.html.twig', [
            'users' => $users,
            'preselectedId' => $destinataireId,
            'error' => null,
        ]);
    }

    #[Route('/admin/messages/send/{destinataireId}', name: 'message_send', methods: ['POST'])]
    public function sendMessage(int $destinataireId, Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        $contenu = $request->request->get('contenu', '');
        if (empty(trim($contenu))) {
            // Redirect back with error, but since it's POST, perhaps redirect to new with error
            return $this->redirectToRoute('message_new', ['destinataireId' => $destinataireId]);
        }

        try {
            $this->httpClient->request('POST', $this->apiBaseUrl . '/users/messages/send/' . $destinataireId, [
                'headers' => $this->bearerHeaders($request),
                'json' => ['contenu' => $contenu],
            ]);

            return $this->redirectToRoute('message_conversation', ['correspondantId' => $destinataireId]);
        } catch (HttpExceptionInterface $e) {
            // Handle error
            return $this->redirectToRoute('message_new', ['destinataireId' => $destinataireId]);
        } catch (TransportExceptionInterface) {
            // Handle error
            return $this->redirectToRoute('message_new', ['destinataireId' => $destinataireId]);
        } catch (Exception) {
            // Handle error
            return $this->redirectToRoute('message_new', ['destinataireId' => $destinataireId]);
        }
    }

    #[Route('/admin/messages/conversation/{correspondantId}', name: 'message_conversation')]
    public function conversation(int $correspondantId, Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/users/messages/' . $correspondantId, [
                'headers' => $this->bearerHeaders($request),
            ]);
            $data = $response->toArray();
            $correspondant = $data['correspondant'];
            $messages = $data['messages'];
        } catch (\Exception) {
            $correspondant = null;
            $messages = [];
        }

        if ($correspondant === null) {
            return $this->redirectToRoute('message_index');
        }

        return $this->render('message/conversation.html.twig', [
            'correspondant' => $correspondant,
            'messages' => $messages,
            'correspondantId' => $correspondantId,
        ]);
    }

    #[Route('/admin/messages/sent/{id}', name: 'message_sent_show')]
    public function showSentMessage(int $id, Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/users/messages/sent/' . $id, [
                'headers' => $this->bearerHeaders($request),
            ]);
            $message = $response->toArray();
            $direction = 'sent';
        } catch (\Exception) {
            return $this->redirectToRoute('message_index');
        }

        return $this->render('message/show.html.twig', [
            'message' => $message,
            'direction' => $direction,
        ]);
    }

    #[Route('/admin/messages/received/{id}', name: 'message_received_show')]
    public function showReceivedMessage(int $id, Request $request): Response
    {
        if (!$this->isLoggedIn($request)) {
            return $this->redirectToRoute('admin_login');
        }

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/users/messages/received/' . $id, [
                'headers' => $this->bearerHeaders($request),
            ]);
            $message = $response->toArray();
            $direction = 'received';
        } catch (\Exception) {
            return $this->redirectToRoute('message_index');
        }

        return $this->render('message/show.html.twig', [
            'message' => $message,
            'direction' => $direction,
        ]);
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