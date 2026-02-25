<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_root', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
