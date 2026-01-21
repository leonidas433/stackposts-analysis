<?php

namespace Modules\AppChannelThreadsProfiles\Classes;

class Threads
{
    const BASE_THREADS_URL = 'https://threads.net';

    const BASE_GRAPH_URL = 'https://graph.threads.net';

    protected $app_id;

    protected $app_secret;

    protected $callback_url;

    public function __construct($app_id, $app_secret, $callback_url, $graph_version = 'v1.0')
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
        $this->callback_url = $callback_url;
        $this->graph_version = $graph_version;
    }

    public function getAuthorizationUrl($scopes, array $params = [], $separator = '&')
    {
        $params += [
            'client_id' => $this->app_id,
            'redirect_uri' => $this->callback_url,
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => rand_string(),
        ];

        return static::BASE_THREADS_URL.'/oauth/authorize?'.http_build_query($params, null, $separator);
    }

    public function getAccessTokenFromCode($code)
    {
        $endpoint = static::BASE_GRAPH_URL.'/oauth/access_token';
        $params = [
            'client_id' => $this->app_id,
            'client_secret' => $this->app_secret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->callback_url,
            'code' => $code,
        ];

        return $this->sendRequest('POST', $endpoint, $params);
    }

    public function getLongLivedAccessToken($accessToken)
    {
        $endpoint = static::BASE_GRAPH_URL.'/access_token';
        $params = [
            'grant_type' => 'th_exchange_token',
            'client_secret' => $this->app_secret,
            'access_token' => $accessToken,
        ];

        return $this->sendRequest('GET', $endpoint, $params);
    }

    public function get(
        string $endpoint,
        array $params = [],
        ?string $accessToken = null
    ) {
        $endpoint = static::BASE_GRAPH_URL.'/'.$this->graph_version.$endpoint;

        return $this->sendRequest('GET', $endpoint, $params, $accessToken);
    }

    public function post(
        string $endpoint,
        array $params = [],
        ?string $accessToken = null
    ) {
        $endpoint = static::BASE_GRAPH_URL.'/'.$this->graph_version.$endpoint;

        return $this->sendRequest('POST', $endpoint, $params, $accessToken);
    }

    protected function sendRequest($method, $endpoint, array $params, ?string $accessToken = null)
    {
        try {
            if ($accessToken != '') {
                $params += [
                    'access_token' => $accessToken,
                ];
            }

            $client = new \GuzzleHttp\Client;
            $response = $client->request($method, $endpoint, ['query' => $params]);

            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            return json_decode($e->getResponse()->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return json_decode($e->getResponse()->getBody(), true);
        }
    }
}
