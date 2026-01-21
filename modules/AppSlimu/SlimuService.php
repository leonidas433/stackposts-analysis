<?php

namespace Modules\AppSlimu;

use GuzzleHttp\Client;

class SlimuService
{
    protected $client;

    protected $apiKey;

    public function __construct()
    {
        $this->client = new Client;
        $this->apiKey = get_option('slimu_api_key', '');
    }

    public function shorten($url, $options = [])
    {
        if (get_option('slimu_status', 0)) {
            $payload = array_merge(['url' => $url], $options);

            $response = $this->client->post('https://slimu.in/api/url/add', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody(), true);

            return $data['data']['short_url'] ?? null;
        } else {
            return null;
        }
    }
}
