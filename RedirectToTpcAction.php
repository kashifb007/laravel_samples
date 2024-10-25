<?php

namespace App\Actions;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response as StatusResponse;

class RedirectToTpcAction
{
    public function execute($redirect, $email, $subdomain, $userId): PromiseInterface|Response
    {
        return $this->response(
            type: 'login',
            email: $email,
            redirect: $redirect,
            subdomain: $subdomain,
            userId: $userId
        );
    }

    private function getUserId(string $email): string
    {
        return $this->response(type: 'eligible', email: $email)['user_id'];
    }

    protected function userExists(string $email): PromiseInterface|Response
    {
        return $this->response(type: 'eligible', email: $email);
    }

    /**
     * Check if the user exists on the Training Platform
     * @param string $email
     * @return array
     */
    public function userLogin(string $email): array
    {
        $response = $this->userExists(email: $email);
        $credentials = [];

        if ($response->getStatusCode() === StatusResponse::HTTP_OK) {
            $data = json_decode($response->body())->data;
            if ($data->exists) {
                $credentials['tpc_user_id'] = $data->user_id;
                $credentials['tpc_token'] = $data->token;
            }
        }
        return $credentials;
    }

    private function response(string $type, string $email = null, string $redirect = null, string $subdomain = 'app', string $userId = null): PromiseInterface|Response
    {
        return Http::withToken(config(key: 'services.test.token'))
            ->get(url: "https://app.test.com/test-link", query: [
                'email' => $email,
                'redirect' => $redirect,
                'subdomain' => $subdomain,
                'type' => $type,
                'userid' => $userId,
            ]);
    }
}
