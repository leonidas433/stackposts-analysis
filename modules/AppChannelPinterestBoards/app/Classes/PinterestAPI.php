<?php

namespace Modules\AppChannelPinterestBoards\Classes;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Media;

class PinterestAPI
{
    const BASE_PINTEREST_URL = 'https://www.pinterest.com';

    const BASE_PINTEREST_API_URL = 'https://api.pinterest.com/v5';

    protected $app_id;

    protected $app_secret;

    protected $callback_url;

    protected $graph_version; // hoặc apiVersion nếu bạn muốn đổi tên

    protected $accessToken;

    protected $mode;

    protected $client;

    protected $baseApiUrl = 'https://api.pinterest.com/v5';

    /**
     * Constructor.
     *
     * @param  string  $app_id  The Pinterest app ID.
     * @param  string  $app_secret  The Pinterest app secret.
     * @param  string  $callback_url  The OAuth callback URL.
     * @param  string  $graph_version  The API version (default: "v1.0").
     */
    public function __construct($app_id, $app_secret, $callback_url, $graph_version = 'v1.0')
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
        $this->callback_url = $callback_url;
        $this->graph_version = $graph_version;
        $this->client = new Client(['verify' => false]);

        if (get_option('pinterest_mode', 0) == 0) {
            $this->baseApiUrl = 'https://api-sandbox.pinterest.com/v5';
        }
    }

    /**
     * Optionally set an access token for subsequent requests.
     *
     * @param  string  $token
     */
    public function setMode($mode)
    {
        if ($mode == 1) {
            $this->baseApiUrl = 'https://api.pinterest.com/v5';
        } else {
            $this->baseApiUrl = 'https://api-sandbox.pinterest.com/v5';
        }
    }

    /**
     * Optionally set an access token for subsequent requests.
     *
     * @param  string  $token
     */
    public function setAccessToken($token)
    {
        $this->accessToken = $token;
    }

    /**
     * Generates the Pinterest authorization URL.
     *
     * @param  string  $scopes  A comma-separated list of scopes.
     * @param  array  $params  Optional additional parameters.
     * @param  string  $separator  Parameter separator (default: '&').
     * @return string
     */
    public function getAuthorizationUrl($scopes, array $params = [], $separator = '&')
    {
        $params += [
            'client_id' => $this->app_id,
            'redirect_uri' => $this->callback_url,
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => rand_string(),
        ];

        return static::BASE_PINTEREST_URL.'/oauth/?'.http_build_query($params, null, $separator);
    }

    /**
     * Retrieves an access token from code.
     *
     * @param  string  $code
     * @return array
     */
    public function getAccessTokenFromCode($code)
    {
        $endpoint = $this->baseApiUrl.'/oauth/token';
        $params = [
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->callback_url,
            'code' => $code,
        ];

        return $this->sendRequest('POST', $endpoint, $params);
    }

    /**
     * Retrieves an access token using a refresh token.
     *
     * @param  string  $refreshToken
     * @param  string  $scopes
     * @return array
     */
    public function getRefreshTokenAccessToken($refreshToken, $scopes)
    {
        $endpoint = $this->baseApiUrl.'/oauth/token';
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => $scopes,
            'refresh_on' => true,
        ];

        return $this->sendRequest('POST', $endpoint, $params);
    }

    /**
     * Wrapper for GET requests.
     *
     * @param  string  $endpoint  Endpoint (without base URL).
     * @param  array  $params  Optional parameters.
     * @param  string|null  $accessToken  Optional token.
     * @return array
     */
    public function get(string $endpoint, array $params = [], ?string $accessToken = null)
    {
        $url = $this->baseApiUrl.$endpoint;

        return $this->sendRequest('GET', $url, $params, $accessToken);
    }

    /**
     * Wrapper for POST requests.
     *
     * @param  string  $endpoint  Endpoint (without base URL).
     * @param  array  $params  Data to send.
     * @param  string|null  $accessToken  Optional token.
     * @return array
     */
    public function post(string $endpoint, array $params = [], ?string $accessToken = null)
    {
        $url = $this->baseApiUrl.'/'.$this->graph_version.$endpoint;

        return $this->sendRequest('POST', $url, $params, $accessToken);
    }

    /**
     * Shares a pin to Pinterest.
     *
     * This method creates a new Pin on a specified board using the Pinterest API v5.
     *
     * Example payload:
     * {
     *   "board_id": "1234567890",
     *   "note": "This is my pin description",
     *   "link": "https://example.com",
     *   "media_source": {
     *     "source_type": "image_url",
     *     "url": "https://example.com/img.jpg"
     *   }
     * }
     *
     * @param  string  $accessToken  The access token.
     * @param  string  $boardId  The Pinterest board ID to post the pin on.
     * @param  string  $note  The pin description.
     * @param  string  $link  (Optional) A URL to attach with the pin.
     * @param  string  $imageUrl  The URL of the image to pin.
     * @return array The API response.
     */
    public function sharePin($accessToken, $boardId, $title, $description, $link, $medias)
    {
        $endpoint = $this->baseApiUrl.'/pins';
        $countImg = 0;
        $imgItems = [];
        $checkType = '';
        $firstMedia = Media::url($medias[0]);

        if (Media::isVideo($firstMedia)) {
            return $this->sharePinVideo($accessToken, $boardId, $title, $description, $link, $firstMedia);
        } else {
            foreach ($medias as $key => $media) {
                $media = Media::url($media);
                if (Media::isImg($media)) {
                    $countImg++;

                    $imgItem = [
                        'description' => $description,
                        'url' => $media,
                    ];

                    if ($title != '') {
                        $imgItem['title'] = $title;
                    }

                    if ($link != '' && filter_var($link, FILTER_VALIDATE_URL)) {
                        $imgItem['link'] = $link;
                    }

                    $imgItems[] = $imgItem;
                }
            }

            if ($countImg > 2) {
                $params = [
                    'media_source' => [
                        'source_type' => 'multiple_image_urls',
                        'items' => $imgItems,
                    ],
                ];
            } else {
                $params = [
                    'media_source' => [
                        'source_type' => 'image_url',
                        'url' => $firstMedia,
                    ],
                ];
            }
        }

        $params['board_id'] = $boardId;
        $params['description'] = $description;
        $params['alt_text'] = $description;

        if ($title != '') {
            $params['title'] = $title;
        }

        if ($link != '' && filter_var($link, FILTER_VALIDATE_URL)) {
            $params['link'] = $link;
        }

        return $this->sendRequest('POST', $endpoint, $params, $accessToken);
    }

    /**
     * Post a video pin to Pinterest.
     *
     * This function performs three main steps:
     * 1. Register your intent to upload video media (POST /media).
     * 2. Upload the video file to the provided upload URL.
     * 3. Create the video pin by calling the /pins endpoint.
     *
     * @param  string  $accessToken  The Pinterest access token.
     * @param  string  $boardId  The board ID on which to pin the video.
     * @param  string  $title  The title for the pin.
     * @param  string  $description  The description for the pin.
     * @param  string  $link  A URL to include with the pin (optional).
     * @param  string  $videoMedia  The media identifier (or local identifier) for the video.
     * @return array API response.
     */
    public function sharePinVideo($accessToken, $boardId, $title, $description, $link, $videoMedia)
    {
        // Get the video URL from your Media helper.
        $videoUrl = Media::url($videoMedia);
        if (! $videoUrl) {
            return $this->errorResponse('No media provided for video pin.', 'media');
        }
        if (! \Media::isVideo($videoUrl)) {
            return $this->errorResponse('Provided media is not a video.', 'media');
        }

        // -----------------------------------------------------------------
        // Step 1: Register Media Upload
        // -----------------------------------------------------------------
        // This endpoint registers your intent to upload video content.
        $registerEndpoint = 'https://pinterest-media-upload.s3-accelerate.amazonaws.com/media';
        $registerParams = [
            // Include any required registration parameters.
            // "media_type" is assumed to be used by Pinterest to distinguish videos.
            'media_type' => 'video',
        ];
        $registerResponse = $this->sendRequest('POST', $registerEndpoint, $registerParams, $accessToken);

        if (! isset($registerResponse['upload_url'])) {
            return $this->errorResponse('Failed to register media upload.', 'media');
        }
        $uploadUrl = $registerResponse['upload_url'];
        $uploadParameters = isset($registerResponse['upload_parameters']) ? $registerResponse['upload_parameters'] : [];

        // -----------------------------------------------------------------
        // Step 2: Upload Video to Pinterest
        // -----------------------------------------------------------------
        // Prepare multipart form data to upload the file.
        $multipart = [];
        // Include all returned upload parameters.
        foreach ($uploadParameters as $key => $value) {
            $multipart[] = [
                'name' => $key,
                'contents' => $value,
            ];
        }
        // Add the video file (using the "file" field).
        $multipart[] = [
            'name' => 'file',
            'contents' => fopen($videoUrl, 'r'),
            'filename' => basename($videoUrl),
        ];
        $guzzleClient = new Client(['verify' => false]);
        try {
            $uploadResponse = $guzzleClient->request('POST', $uploadUrl, [
                'multipart' => $multipart,
            ]);
            $uploadResponseData = json_decode($uploadResponse->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return $this->errorResponse('Video upload failed: '.$e->getMessage(), 'media');
        }

        // Assume the upload response returns a media id for the uploaded video.
        if (! isset($uploadResponseData['id'])) {
            return $this->errorResponse('Failed to upload video media.', 'media');
        }
        $videoMediaId = $uploadResponseData['id'];

        // -----------------------------------------------------------------
        // Step 3: Create the Video Pin
        // -----------------------------------------------------------------
        $pinEndpoint = $this->baseApiUrl.'/pins';
        $pinParams = [
            'board_id' => $boardId,
            'description' => $description,
            'alt_text' => $description, // Using description as alt_text, adjust as necessary.
            'media_source' => [
                'source_type' => 'video_id', // Specifies that the media is a video.
                'media_id' => $videoMediaId,
            ],
        ];
        if ($title != '') {
            $pinParams['title'] = $title;
        }
        if ($link != '' && filter_var($link, FILTER_VALIDATE_URL)) {
            $pinParams['link'] = $link;
        }

        return $this->sendRequest('POST', $pinEndpoint, $pinParams, $accessToken);
    }

    /**
     * Sends an HTTP request using Guzzle.
     *
     * @param  string  $method  HTTP method (GET, POST, etc.).
     * @param  string  $endpoint  Fully qualified endpoint URL.
     * @param  array  $params  Request parameters.
     * @param  string|null  $accessToken  (Optional) Override token.
     * @return array
     */
    protected function sendRequest($method, $endpoint, array $params, ?string $accessToken = null)
    {
        try {
            if (! empty($accessToken) || ! empty($this->accessToken)) {
                $token = ! empty($accessToken) ? $accessToken : $this->accessToken;
                $headers = [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer $token",
                ];
                $options = [
                    'json' => $params,
                    'headers' => $headers,
                ];
            } else {
                $token_string = base64_encode($this->app_id.':'.$this->app_secret);
                $headers = [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => "Basic $token_string",
                ];
                $options = [
                    'form_params' => $params,
                    'headers' => $headers,
                ];
            }

            $response = $this->client->request($method, $endpoint, $options);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return json_decode($e->getResponse()->getBody(), true);
            }

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Returns a standardized error response.
     *
     * @param  string  $message  The error message.
     * @param  string  $type  The post type.
     * @return array Error response.
     */
    protected function errorResponse($message, $type)
    {
        return [
            'status' => 'error',
            'message' => $message,
            'type' => $type,
        ];
    }
}
