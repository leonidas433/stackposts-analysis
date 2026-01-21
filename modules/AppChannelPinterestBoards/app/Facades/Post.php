<?php

namespace Modules\AppChannelPinterestBoards\Facades;

use Illuminate\Support\Facades\Facade;
use Media;
use Modules\AppChannelPinterestBoards\Classes\PinterestAPI;
use Modules\AppChannels\Models\Accounts;

class Post extends Facade
{
    private static $pinterest;

    /**
     * Initializes the PinterestAPI object and optionally loads the token.
     *
     * @param  string|null  $token  The Pinterest access token.
     */
    public static function initPinterest()
    {
        if (! self::$pinterest) {
            // Initialize PinterestAPI with app settings (replace get_option() with your config mechanism)
            self::$pinterest = new PinterestAPI(
                get_option('pinterest_client_id', ''),
                get_option('pinterest_client_secret', ''),
                '',
            );
        }
    }

    protected static function getFacadeAccessor()
    {
        return ex_str(__NAMESPACE__);
    }

    /**
     * Validates the post object for Pinterest posting.
     *
     * Pinterest requires at least one image.
     *
     * @param  object  $post  The post data object.
     * @return array A list of error messages.
     */
    protected static function validator($post)
    {
        $errors = [];
        $data = json_decode($post->data, false);
        $medias = $data->medias ?? [];
        $options = $data->options;

        if (empty($medias)) {
            $errors[] = __('At least one media or video is required to create a pin.');
        } else {
            // Ensure that the provided media is an image.
            $media = Media::url($medias[0]);
            if (! Media::isImg($media) && ! Media::isVideo($media)) {
                $errors[] = __('The provided media must be an image or video for Pinterest pins.');
            }
        }

        if (isset($options->pinterest_link) && $options->pinterest_link != '') {
            $url = $options->pinterest_link;
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = __('Pinterest: Link is not a valid URL');
            }
        }

        return $errors;
    }

    /**
     * Shares a pin to Pinterest using the PinterestAPI.
     *
     * Supported types:
     * - media: pin with an image.
     * - link: pin with an image and a link attached.
     *
     * The board ID is assumed to be stored in $post->account->pid and the access
     * token in $post->account->token.
     *
     * @param  object  $post  The post object.
     * @return array The standardized response from the Pinterest posting.
     */
    protected static function post($post)
    {
        self::initPinterest();
        $accessToken = json_decode($post->account->token);
        $renewToken = self::$pinterest->getRefreshTokenAccessToken($accessToken->refresh_token, str_replace(' ', ',', $accessToken->scope));
        if (isset($renewToken['message'])) {
            Accounts::where('id', $post->account->id)->update(['status' => 0]);

            return self::errorResponse(__('Access token expired'), $post->type);
        }

        self::$pinterest->setAccessToken($renewToken['access_token']);
        $accessToken = $renewToken['access_token'];
        $pinterest = self::$pinterest;

        // Here, we assume that the board ID (to which the pin will be posted) is stored in $post->account->pid.
        $boardId = $post->account->pid;

        $data = json_decode($post->data, false);
        $medias = $data->medias ?? [];
        $title = spintax($data->options->pinterest_title ?? '');
        $pinterest_link = $data->options->pinterest_link ?? '';
        $caption = spintax($data->caption);
        // For link posts, we get an optional link to attach
        $link = $data->link ?? '';

        if ($pinterest_link != '') {
            $link = $pinterest_link;
        }

        // Call the sharePin method.
        $response = $pinterest->sharePin($accessToken, $boardId, $title, $caption, $link, $medias);

        // The Pinterest API should return a result. If an error is detected, process it.
        if (isset($response['message'])) {
            return self::errorResponse(__($response['message']), $post->type);
        }
        if (! isset($response['id'])) {
            return self::errorResponse(__('Unknown error occurred while sharing pin.'), $post->type);
        }

        return [
            'status' => 1,
            'message' => __('Succeeded'),
            'id' => $response['id'],
            // Pinterest pin URL pattern – adjust if different.
            'url' => 'https://www.pinterest.com/pin/'.$response['id'],
            'type' => $post->type,
        ];
    }

    /**
     * Returns a standardized error response.
     *
     * @param  string  $message  The error message.
     * @param  string  $type  The post type.
     * @return array The error response.
     */
    protected static function errorResponse($message, $type)
    {
        return [
            'status' => 0,
            'message' => __($message),
            'type' => $type,
        ];
    }
}
