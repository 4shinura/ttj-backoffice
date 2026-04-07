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

class ValidationController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiBaseUrl = "http://localhost:5555/api",
    ) {}

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