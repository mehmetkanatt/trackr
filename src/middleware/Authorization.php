<?php

namespace App\middleware;

use App\exception\CustomException;
use App\model\AuthModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

class Authorization extends Middleware
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $authModel = new AuthModel($this->container);
        $apiToken = $request->getHeader('X-Trackr-Token');

        if (!$apiToken) {
            throw CustomException::clientError(StatusCode::HTTP_UNAUTHORIZED, 'Authorization header required!');
        }

        $user = $authModel->getUserByApiToken($apiToken);

        if (!$user) {
            throw CustomException::clientError(StatusCode::HTTP_UNAUTHORIZED, 'User not found!');
        }

        $response = $next($request, $response);

        return $response;

    }
}