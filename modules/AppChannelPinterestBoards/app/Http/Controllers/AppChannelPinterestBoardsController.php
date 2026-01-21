<?php

namespace Modules\AppChannelPinterestBoards\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\AppChannelPinterestBoards\Classes\PinterestAPI;

class AppChannelPinterestBoardsController extends Controller
{
    public function __construct()
    {
        \Access::check('appchannels.'.module('key'));

        $clientId = get_option('pinterest_client_id', '');
        $clientSecret = get_option('pinterest_client_secret', '');
        $scopes = get_option('pinterest_scopes', 'user_accounts:read,pins:read,pins:read_secret,pins:write,pins:write_secret,boards:read,boards:read_secret,boards:write');

        if (! $clientId || ! $clientSecret || ! $scopes) {
            \Access::deny(__('To use Pinterest, you must first configure the Client ID, Client Secret, and Scope.'));
        }

        $this->callback_url = module_url();
        $this->pinterest = new PinterestAPI($clientId, $clientSecret, $this->callback_url);
        $this->scopes = $scopes;
    }

    public function index(Request $request)
    {
        if (get_option('pinterest_mode', 0) == 0) {
            if (session('Pinterest_Mode') == 1) {
                $this->pinterest->setMode(1);
            } else {
                $this->pinterest->setMode(0);
            }
        }

        $result = [];
        try {
            if (! session('Pinterest_AccessToken')) {
                if (! $request->code) {
                    return redirect(module_url('oauth'));
                }

                $response = $this->pinterest->getAccessTokenFromCode($request->code);

                if (isset($response['access_token'])) {
                    session(['Pinterest_AccessToken' => $response]);
                }

                return redirect($this->callback_url);
            } else {
                $accessToken = session('Pinterest_AccessToken');
            }

            $profile = $this->pinterest->get('/user_account', [], $accessToken['access_token']);

            if (get_option('pinterest_mode', 0) == 0) {
                if (! session()->has('Pinterest_Mode')) {
                    return redirect(module_url('oauth'));
                }

                if (session('Pinterest_Mode') == 1) {
                    $this->pinterest->setMode(1);
                    $response = $this->pinterest->get('/boards', [], $accessToken['access_token']);
                    session(['Pinterest_Boards' => $response]);

                    return redirect(module_url('oauth'));
                } elseif (session()->has('Pinterest_Boards')) {
                    $response = session('Pinterest_Boards');
                } else {
                    $request->session()->forget('Pinterest_Mode');
                    $request->session()->forget('Pinterest_Boards');

                    return redirect(module_url('oauth'));
                }

            } else {
                $response = $this->pinterest->get('/boards', [], $accessToken['access_token']);
            }

            if (isset($response['items']) && count($response['items']) > 0) {
                foreach ($response['items'] as $key => $value) {
                    $avatar = text2img($value['name'], 'rand');

                    $result[] = [
                        'id' => $value['id'],
                        'name' => $value['name'].' ('.$profile['username'].')',
                        'avatar' => $avatar,
                        'desc' => __('Board'),
                        'link' => 'https://www.pinterest.com/'.$profile['username'],
                        'oauth' => $accessToken,
                        'module' => $request->module['module_name'],
                        'reconnect_url' => $request->module['uri'].'/oauth',
                        'social_network' => 'pinterest',
                        'category' => 'board',
                        'login_type' => 1,
                        'can_post' => 1,
                        'data' => '',
                        'proxy' => 0,
                    ];
                }

                $channels = [
                    'status' => 1,
                    'message' => __('Succeeded'),
                ];
            } else {
                if (! empty($response) && isset($response['message'])) {
                    $channels = [
                        'status' => 0,
                        'message' => $response['message'],
                    ];
                } else {
                    $channels = [
                        'status' => 0,
                        'message' => __('Empty Broads'),
                    ];
                }
            }
        } catch (\Exception $e) {
            $channels = [
                'status' => 0,
                'message' => $e->getMessage(),
            ];
        }

        $request->session()->forget('Pinterest_Mode');
        $request->session()->forget('Pinterest_Boards');

        $channels = array_merge($channels, [
            'channels' => $result,
            'module' => $request->module,
            'save_url' => url_app('channels/save'),
            'reconnect_url' => module_url('oauth'),
            'oauth' => session('Pinterest_AccessToken'),
        ]);

        session(['channels' => $channels]);

        return redirect(url_app('channels/add'));
    }

    public function oauth(Request $request)
    {
        if (get_option('pinterest_mode', 0) == 0) {
            if (session()->has('Pinterest_Mode')) {
                session(['Pinterest_Mode' => 0]);
                $this->pinterest->setMode(0);
            } else {
                session(['Pinterest_Mode' => 1]);
                $this->pinterest->setMode(1);
            }
        }

        $request->session()->forget('Pinterest_AccessToken');
        $login_url = $this->pinterest->getAuthorizationUrl($this->scopes);

        return redirect($login_url);
    }

    public function settings()
    {
        return view('appchannelpinterestboards::settings');
    }
}
