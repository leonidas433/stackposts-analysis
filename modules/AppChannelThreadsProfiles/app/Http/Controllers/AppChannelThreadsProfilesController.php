<?php

namespace Modules\AppChannelThreadsProfiles\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\AppChannelThreadsProfiles\Classes\Threads;

class AppChannelThreadsProfilesController extends Controller
{
    public function __construct()
    {
        \Access::check('appchannels.'.module('key'));

        $appId = get_option('threads_app_id', '');
        $appSecret = get_option('threads_app_secret', '');
        $this->callback_url = module_url();
        $this->scopes = get_option('threads_permissions', 'threads_basic,threads_content_publish,threads_manage_insights');

        if (! $appId || ! $appSecret) {
            \Access::deny(__('To use Threads, you must first configure the app ID and app secret.'));
        }

        try {
            $this->threads = new Threads($appId, $appSecret, $this->callback_url);
        } catch (\Exception $e) {
            \Log::error('Threads SDK init error', ['error' => $e->getMessage()]);
            \Access::deny(__('Could not connect to Threads API: ').$e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $result = [];
        try {
            if (! session('FB_AccessToken')) {
                if (! $request->code) {
                    return redirect(module_url('oauth'));
                }

                $response = $this->threads->getAccessTokenFromCode($request->code);

                if (isset($response['access_token'])) {
                    $response = $this->threads->getLongLivedAccessToken($response['access_token']);

                    if (isset($response['access_token'])) {
                        session(['FB_AccessToken' => $response['access_token']]);
                    } else {
                        $channels = [
                            'status' => 0,
                            'message' => $response['error']['message'],
                        ];
                    }
                } else {
                    $channels = [
                        'status' => 0,
                        'message' => $response['error']['message'],
                    ];

                }

                return redirect($this->callback_url);
            } else {
                $accessToken = session('FB_AccessToken');
            }

            $response = $this->threads->get('/me', [
                'fields' => 'id,username,name,threads_profile_picture_url,threads_biography',
            ], $accessToken);

            if (isset($response['id'])) {

                if (isset($response['profile_picture_url'])) {
                    $avatar = $response['profile_picture_url'];
                } else {
                    $avatar = text2img($response['username']);
                }

                $result[] = [
                    'id' => $response['id'],
                    'name' => $response['username'],
                    'avatar' => $avatar,
                    'desc' => __('Profile'),
                    'link' => 'https://www.instagram.com/'.$response['username'],
                    'oauth' => $accessToken,
                    'module' => $request->module['module_name'],
                    'reconnect_url' => $request->module['uri'].'/oauth',
                    'social_network' => 'threads',
                    'category' => 'profile',
                    'login_type' => 1,
                    'can_post' => 1,
                    'data' => '',
                    'proxy' => 0,
                ];

                $channels = [
                    'status' => 1,
                    'message' => __('Succeeded'),
                ];
            } else {
                $channels = [
                    'status' => 0,
                    'message' => $response['error']['message'],
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
            'oauth' => session('FB_AccessToken'),
        ]);

        session(['channels' => $channels]);

        return redirect(url_app('channels/add'));
    }

    public function oauth(Request $request)
    {
        $request->session()->forget('FB_AccessToken');
        $login_url = $this->threads->getAuthorizationUrl($this->scopes);

        return redirect($login_url);
    }

    public function settings()
    {
        return view('appchannelthreadsprofiles::settings');
    }
}
