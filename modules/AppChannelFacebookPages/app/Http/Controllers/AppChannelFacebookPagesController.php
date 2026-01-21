<?php

namespace Modules\AppChannelFacebookPages\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JanuSoftware\Facebook\Facebook;

class AppChannelFacebookPagesController extends Controller
{
    public $fb;

    public function __construct()
    {
        \Access::check('appchannels.'.module('key'));

        $appId = get_option('facebook_app_id', '');
        $appSecret = get_option('facebook_app_secret', '');
        $appVersion = get_option('facebook_graph_version', 'v22.0');
        $appPermissions = get_option('facebook_page_permissions', 'pages_read_engagement,pages_manage_posts,pages_show_list,business_management');
        if (! $appId || ! $appSecret || ! $appVersion || ! $appPermissions) {
            \Access::deny(__('To use Facebook Pages, you must first configure the app ID, app secret, app permissions and app version.'));
        }

        try {
            $this->fb = new Facebook([
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'default_graph_version' => $appVersion,
            ]);
        } catch (\Exception $e) {
        }

        $this->scopes = $appPermissions;
    }

    public function index(Request $request)
    {
        $result = [];

        try {
            if (! session('FB_AccessToken')) {
                if (! $request->code) {
                    return redirect(module_url('oauth'));
                }

                $callback_url = module_url();
                $helper = $this->fb->getRedirectLoginHelper();
                if ($request->state) {
                    $helper->getPersistentDataHandler()->set('state', $request->state);
                }
                $accessToken = $helper->getAccessToken($callback_url);
                $accessToken = $accessToken->getValue();
                session(['FB_AccessToken' => $accessToken]);

                return redirect($callback_url);
            } else {
                $accessToken = session('FB_AccessToken');
            }

            $response = $this->fb->get('/me/accounts?fields=id,name,username,fan_count,link,is_verified,picture,access_token,category&limit=10000', $accessToken)->getDecodedBody();
            if (is_string($response)) {
                $response = $this->fb->get('/me/accounts?fields=id,name,username,fan_count,link,is_verified,picture,access_token,category&limit=3', $accessToken)->getDecodedBody();
            }

            if (! is_string($response)) {
                if (! empty($response)) {
                    if (isset($response['data']) && ! empty($response['data'])) {
                        foreach ($response['data'] as $value) {
                            $result[] = [
                                'id' => $value['id'],
                                'name' => $value['name'],
                                'avatar' => $value['picture']['data']['url'] ?? text2img($response['name'], 'rand'),
                                'desc' => $value['category'],
                                'link' => $value['link'],
                                'oauth' => $value['access_token'],
                                'module' => $request->module['module_name'],
                                'reconnect_url' => $request->module['uri'].'/oauth',
                                'social_network' => 'facebook',
                                'category' => 'page',
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
                        $channels = [
                            'status' => 0,
                            'message' => __('No profile to add'),
                        ];
                    }
                }
            } else {
                $channels = [
                    'status' => 0,
                    'message' => $response,
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
        $helper = $this->fb->getRedirectLoginHelper();
        $permissions = [$this->scopes];
        $login_url = $helper->getLoginUrl(module_url(), $permissions);

        return redirect($login_url);
    }

    public function settings()
    {
        return view('appchannelfacebookpages::settings');
    }
}
