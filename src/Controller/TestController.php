<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    private int $counter = 0;

    #[Route(path: '/test', name: 'test', methods: [Request::METHOD_GET])]
    public function testAction(): Response
    {
        $content = '['.\date('c').'] Counter: '.(++$this->counter) . PHP_EOL;

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }
}
