<?php

namespace Modules\AppChannelGBPLocations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AppChannelGBPLocationsController extends Controller
{
    public $gbp_management;

    public $gbp_information;

    protected $client;

    protected $client_id;

    protected $client_secret;

    protected $api_key;

    protected $callback_url;

    protected function extractPlaceIdFromUrl($url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            $qs = [];
            parse_str($parts['query'], $qs);
            foreach (['place_id', 'query_place_id', 'placeId', 'placeid'] as $key) {
                $candidate = trim((string) ($qs[$key] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        if (preg_match('/(?:\?|&)(?:place_id|query_place_id)=([^&]+)/i', $url, $m)) {
            return trim(urldecode((string) ($m[1] ?? '')));
        }

        return '';
    }

    protected function extractPlaceIdFromLocation($location, $mapsUri): string
    {
        $mapsUri = trim((string) $mapsUri);

        $meta = null;
        if (is_object($location) && method_exists($location, 'getMetaData')) {
            $meta = $location->getMetaData();
        } elseif (is_object($location) && method_exists($location, 'getMetadata')) {
            $meta = $location->getMetadata();
        }

        $candidates = [];

        if (is_object($meta) && method_exists($meta, 'getPlaceId')) {
            $candidates[] = $meta->getPlaceId();
        }

        if (is_object($location) && method_exists($location, 'getLocationKey')) {
            $locationKey = $location->getLocationKey();
            if (is_object($locationKey) && method_exists($locationKey, 'getPlaceId')) {
                $candidates[] = $locationKey->getPlaceId();
            }
        }

        if (is_object($meta) && method_exists($meta, 'getMapsUri')) {
            $candidates[] = $this->extractPlaceIdFromUrl($meta->getMapsUri());
        }

        if ($mapsUri !== '') {
            $candidates[] = $this->extractPlaceIdFromUrl($mapsUri);
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    public function __construct()
    {
        \Access::check('appchannels.'.module('key'));

        $this->client_id = get_option('gbp_client_id', '');
        $this->client_secret = get_option('gbp_client_secret', '');
        $this->api_key = get_option('gbp_api_key', '');
        $this->callback_url = module_url();

        if (! $this->client_id || ! $this->client_secret || ! $this->api_key) {
            \Access::deny(__('To use Google Business Profile, you must first configure the client ID, client secret, and API key.'));
        }

        try {
            $this->client = new \Google_Client;
            $this->client->setApprovalPrompt('force');
            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');
            $this->client->setApplicationName('GBP');
            $this->client->setClientId($this->client_id);
            $this->client->setClientSecret($this->client_secret);
            $this->client->setRedirectUri($this->callback_url);
            $this->client->setDeveloperKey($this->api_key);
            $this->client->setScopes([
                'https://www.googleapis.com/auth/business.manage',
                'https://www.googleapis.com/auth/userinfo.email',
            ]);

            $this->client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

            $this->gbp_management = new \Google_Service_MyBusinessAccountManagement($this->client);
            $this->gbp_information = new \Google_Service_MyBusinessBusinessInformation($this->client);

        } catch (\Exception $e) {
            \Log::error('Google My Business SDK init error', ['error' => $e->getMessage()]);
            \Access::deny(__('Could not connect to Google Business API: ').$e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $result = [];

        try {
            if (! session('GBP_AccessToken')) {
                if (! $request->code) {
                    return redirect(module_url('oauth'));
                }

                $accessToken = $this->client->fetchAccessTokenWithAuthCode($request->code);
                if (is_array($accessToken) && ! isset($accessToken['scope'])) {
                    $scopes = $this->client->getScopes();
                    if (is_array($scopes) && ! empty($scopes)) {
                        $accessToken['scope'] = implode(' ', array_values($scopes));
                    }
                }
                session(['GBP_AccessToken' => $accessToken]);

                return redirect($this->callback_url);
            } else {
                $accessToken = session('GBP_AccessToken');
            }

            $this->client->setAccessToken($accessToken);

            $accountsList = $this->gbp_management->accounts->listAccounts()->getAccounts();
            if (! empty($accountsList)) {
                $response = [];
                $optional_params = [];
                $optional_params['pageSize'] = 100;
                $optional_params['readMask'] = ['name', 'title', 'storefrontAddress', 'latlng', 'phoneNumbers', 'Metadata'];
                $response = $this->gbp_information->accounts_locations->listAccountsLocations($accountsList[0]->name, $optional_params)->getLocations();

                if (! empty($response)) {
                    foreach ($response as $value) {
                        $avatar = text2img($value->getTitle(), 'rand');
                        $mapsUri = '';
                        if (method_exists($value, 'getMetaData') && $value->getMetaData() && method_exists($value->getMetaData(), 'getMapsUri')) {
                            $mapsUri = (string) $value->getMetaData()->getMapsUri();
                        }
                        $placeId = $this->extractPlaceIdFromLocation($value, $mapsUri);

                        $result[] = [
                            'id' => $accountsList[0]->name.'/'.$value->getName(),
                            'name' => $value->getTitle(),
                            'avatar' => $avatar,
                            'desc' => __('Location'),
                            'link' => $mapsUri,
                            'oauth' => $accessToken,
                            'module' => $request->module['module_name'],
                            'reconnect_url' => $request->module['uri'].'/oauth',
                            'social_network' => 'google_business_profile',
                            'category' => 'location',
                            'login_type' => 1,
                            'can_post' => 1,
                            'data' => $placeId !== '' ? ['place_id' => $placeId] : [],
                            'proxy' => 0,
                        ];
                    }

                    $channels = [
                        'status' => 1,
                        'message' => __('Succeeded'),
                    ];
                } else {
                    $channels = [
                        'status' => 0,
                        'message' => __('No profile to add'),
                    ];
                }
            } else {
                $channels = [
                    'status' => 0,
                    'message' => __('No profile to add'),
                ];
            }
        } catch (\Exception $e) {
            $channels = [
                'status' => 0,
                'message' => $e->getMessage(),
            ];
        }

        $channels = array_merge($channels, [
            'channels' => $result,
            'module' => $request->module,
            'save_url' => url_app('channels/save'),
            'reconnect_url' => module_url('oauth'),
            'oauth' => session('GBP_AccessToken'),
        ]);

        session(['channels' => $channels]);

        return redirect(url_app('channels/add'));
    }

    public function oauth(Request $request)
    {
        $request->session()->forget('GBP_AccessToken');
        $login_url = $this->client->createAuthUrl();

        return redirect($login_url);
    }

    public function settings()
    {
        return view('appchannelgbplocations::settings');
    }
}
