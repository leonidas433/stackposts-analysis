<?php

namespace Modules\AppChannelGBPLocations\Facades;

use Google\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Media;
use Modules\AppChannels\Models\Accounts;

class Post extends Facade
{
    protected static $client;

    protected static string $requiredScope = 'https://www.googleapis.com/auth/business.manage';

    protected static function getFacadeAccessor()
    {
        return ex_str(__NAMESPACE__);
    }

    protected static function scopeStringToList(?string $scope): array
    {
        $scope = trim((string) ($scope ?? ''));
        if ($scope === '') {
            return [];
        }

        $items = preg_split('/\s+/', $scope) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $items), fn ($v) => $v !== '')));
    }

    protected static function normalizeScopeValue(string $scope): string
    {
        $scope = trim($scope);
        while (str_ends_with($scope, '.')) {
            $scope = rtrim($scope, '.');
        }

        return $scope;
    }

    protected static function hasRequiredScope(array $token): bool
    {
        $raw = (string) ($token['scope'] ?? '');
        $scopes = self::scopeStringToList($raw);
        $scopes = array_map([self::class, 'normalizeScopeValue'], $scopes);

        return in_array(self::normalizeScopeValue(self::$requiredScope), $scopes, true);
    }

    protected static function markNeedsReauth(Accounts $account, string $reason): void
    {
        $data = is_array($account->data) ? $account->data : (json_decode((string) ($account->data ?? ''), true) ?: []);
        $data['needs_reauth'] = 1;
        $data['needs_reauth_reason'] = $reason;

        Accounts::where('id', $account->id)->update([
            'status' => 2,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
    }

    protected static function sanitizeErrorMessage(mixed $message): string
    {
        $msg = trim((string) ($message ?? ''));
        if ($msg === '') {
            return __('Unknown error');
        }

        $msg = preg_replace('/\s+/', ' ', $msg) ?: $msg;
        if (mb_strlen($msg) > 500) {
            $msg = mb_substr($msg, 0, 500);
        }

        return $msg;
    }

    protected static function logGbpError(string $title, Accounts $account, ?int $httpStatus, string $endpoint, ?string $errorCode, ?string $errorMessage): void
    {
        Log::warning($title, [
            'team_id' => $account->team_id ?? null,
            'account_id' => $account->id ?? null,
            'http_status' => $httpStatus,
            'endpoint' => $endpoint,
            'error_code' => $errorCode,
            'error_message' => self::sanitizeErrorMessage($errorMessage),
        ]);
    }

    protected static function normalizeTokenForRefresh(array $currentToken, array $refreshedToken): array
    {
        if (! isset($refreshedToken['refresh_token']) && isset($currentToken['refresh_token'])) {
            $refreshedToken['refresh_token'] = $currentToken['refresh_token'];
        }

        if (! isset($refreshedToken['scope']) && isset($currentToken['scope'])) {
            $refreshedToken['scope'] = $currentToken['scope'];
        }

        return $refreshedToken;
    }

    protected static function getAccessTokenForAccount(Accounts $account): array
    {
        self::initGBP();

        $currentToken = is_string($account->token) ? (json_decode($account->token, true) ?: []) : (is_array($account->token) ? $account->token : []);
        if (! self::hasRequiredScope($currentToken)) {
            self::markNeedsReauth($account, 'missing_scope');

            return [
                'status' => 0,
                'message' => __('Reauthorization required'),
                'error_code' => 'needs_reauth',
            ];
        }

        self::$client->setAccessToken($account->token);

        if (! empty($currentToken['refresh_token'])) {
            $refreshed = self::$client->fetchAccessTokenWithRefreshToken($currentToken['refresh_token']);
            if (is_array($refreshed) && isset($refreshed['error'])) {
                Accounts::where('id', $account->id)->update(['status' => 0]);

                return [
                    'status' => 0,
                    'message' => __('Access Token Expired'),
                ];
            }

            if (is_array($refreshed) && ! empty($refreshed)) {
                $normalized = self::normalizeTokenForRefresh($currentToken, $refreshed);
                Accounts::where('id', $account->id)->update(['token' => json_encode($normalized)]);
                self::$client->setAccessToken($normalized);
                $currentToken = $normalized;
            }
        }

        $accessToken = (string) (self::$client->getAccessToken()['access_token'] ?? '');
        if ($accessToken === '' && ! empty($currentToken['access_token'])) {
            $accessToken = (string) $currentToken['access_token'];
        }

        if ($accessToken === '') {
            return [
                'status' => 0,
                'message' => __('Access Token Expired'),
            ];
        }

        return [
            'status' => 1,
            'access_token' => $accessToken,
        ];
    }

    public static function listReviews(Accounts $account, ?string $pageToken = null, int $pageSize = 50): array
    {
        $auth = self::getAccessTokenForAccount($account);
        if (($auth['status'] ?? 0) !== 1) {
            return $auth;
        }

        $pageSize = max(1, min(200, (int) $pageSize));
        $query = [
            'pageSize' => $pageSize,
            'fields' => 'reviews(name,reviewer(displayName,profilePhotoUrl,profilePhotoUri),starRating,comment,createTime,updateTime,reviewReply(comment,updateTime),languageCode),nextPageToken',
        ];
        if ($pageToken !== null && trim($pageToken) !== '') {
            $query['pageToken'] = $pageToken;
        }

        $url = 'https://mybusiness.googleapis.com/v4/'.ltrim((string) $account->pid, '/').'/reviews?'.http_build_query($query);

        $httpClient = self::$client->getHttpClient();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$auth['access_token'],
        ];

        try {
            $request = new Request('GET', $url, $headers);
            $response = $httpClient->send($request);
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            $body = $e->getResponse()?->getBody()?->getContents();
            $decoded = is_string($body) ? (json_decode($body, true) ?: []) : [];
            $err = is_array($decoded['error'] ?? null) ? $decoded['error'] : null;
            $code = is_array($err) ? (($err['code'] ?? null) !== null ? (string) $err['code'] : null) : null;
            $msg = is_array($err) ? (string) ($err['message'] ?? '') : null;
            self::logGbpError('GBP listReviews error', $account, $status, $url, $code, $msg);

            return [
                'status' => 0,
                'message' => __('Unknown error'),
                'http_status' => $status,
                'endpoint' => $url,
            ];
        } catch (\Throwable $e) {
            self::logGbpError('GBP listReviews error', $account, null, $url, null, $e->getMessage());

            return [
                'status' => 0,
                'message' => __('Unknown error'),
                'endpoint' => $url,
            ];
        }

        if (isset($data['error'])) {
            $err = is_array($data['error'] ?? null) ? $data['error'] : null;
            $code = is_array($err) ? (($err['code'] ?? null) !== null ? (string) $err['code'] : null) : null;
            $msg = is_array($err) ? (string) ($err['message'] ?? '') : null;
            self::logGbpError('GBP listReviews error', $account, isset($data['error']['code']) ? (int) $data['error']['code'] : null, $url, $code, $msg);

            return [
                'status' => 0,
                'message' => $data['error']['message'] ?? __('Unknown error'),
                'error' => $data['error'],
                'http_status' => $data['error']['code'] ?? null,
                'endpoint' => $url,
            ];
        }

        return [
            'status' => 1,
            'data' => $data,
        ];
    }

    public static function updateReviewReply(Accounts $account, string $reviewName, string $comment): array
    {
        $auth = self::getAccessTokenForAccount($account);
        if (($auth['status'] ?? 0) !== 1) {
            return $auth;
        }

        $reviewName = trim($reviewName);
        if ($reviewName === '') {
            return [
                'status' => 0,
                'message' => __('Unknown error'),
            ];
        }

        $url = 'https://mybusiness.googleapis.com/v4/'.ltrim($reviewName, '/').'/reply';
        $body = json_encode(['comment' => $comment], JSON_UNESCAPED_UNICODE);

        $httpClient = self::$client->getHttpClient();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$auth['access_token'],
        ];

        try {
            $request = new Request('PUT', $url, $headers, $body);
            $response = $httpClient->send($request);
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            $body = $e->getResponse()?->getBody()?->getContents();
            $decoded = is_string($body) ? (json_decode($body, true) ?: []) : [];
            $err = is_array($decoded['error'] ?? null) ? $decoded['error'] : null;
            $code = is_array($err) ? (($err['code'] ?? null) !== null ? (string) $err['code'] : null) : null;
            $msg = is_array($err) ? (string) ($err['message'] ?? '') : null;
            self::logGbpError('GBP updateReviewReply error', $account, $status, $url, $code, $msg);

            return [
                'status' => 0,
                'message' => __('Unknown error'),
                'http_status' => $status,
                'endpoint' => $url,
            ];
        } catch (\Throwable $e) {
            self::logGbpError('GBP updateReviewReply error', $account, null, $url, null, $e->getMessage());

            return [
                'status' => 0,
                'message' => __('Unknown error'),
                'endpoint' => $url,
            ];
        }

        if (isset($data['error'])) {
            $err = is_array($data['error'] ?? null) ? $data['error'] : null;
            $code = is_array($err) ? (($err['code'] ?? null) !== null ? (string) $err['code'] : null) : null;
            $msg = is_array($err) ? (string) ($err['message'] ?? '') : null;
            self::logGbpError('GBP updateReviewReply error', $account, isset($data['error']['code']) ? (int) $data['error']['code'] : null, $url, $code, $msg);

            return [
                'status' => 0,
                'message' => $data['error']['message'] ?? __('Unknown error'),
                'error' => $data['error'],
                'http_status' => $data['error']['code'] ?? null,
                'endpoint' => $url,
            ];
        }

        return [
            'status' => 1,
            'data' => $data,
        ];
    }

    /**
     * Initialize the Google Business Profile API client.
     *
     * @return Client
     */
    protected static function initGBP()
    {
        self::$client = new Client;
        self::$client->setClientId(get_option('gbp_client_id', ''));
        self::$client->setClientSecret(get_option('gbp_client_secret', ''));
        self::$client->setDeveloperKey(get_option('gbp_api_key', ''));
        self::$client->setApplicationName('Google Business Profile');
        self::$client->setApprovalPrompt('force');
        self::$client->setAccessType('offline');
        self::$client->setScopes([
            'https://www.googleapis.com/auth/business.manage',
        ]);

        return self::$client;
    }

    /**
     * Validate post data.
     *
     * Expected data:
     *  - summary: The text content of the post.
     *  - media_path: (optional) The file path to an image.
     *
     * @param  object  $post
     * @return array An array of error messages (if any)
     */
    protected static function validator($post)
    {
        $errors = [];
        $data = json_decode($post->data, false);
        $medias = $data->medias;
        $options = $data->options;

        switch ($post->type) {
            case 'media':
                $media = Media::url($medias[0]);
                if (! Media::isImg($media)) {
                    $errors[] = __('Google Business Profile: The media file is missing, invalid, or not an image.');
                }
        }

        $actions = ['LEARN_MORE', 'BOOK', 'ORDER', 'SHOP', 'SIGN_UP'];
        if (isset($options->gbp_action) && in_array($options->gbp_action, $actions)) {
            if (! isset($options->gbp_link) || $options->gbp_link == '') {
                $errors[] = __('Google Business Profile: Action link is required for call to action');
            }
        }

        return $errors;
    }

    /**
     * Main method to create a local post on Google Business Profile.
     *
     * It refreshes the token if needed and then sends a raw HTTP POST
     * request to the v4 localPosts endpoint.
     *
     * @param  object  $post
     * @return array The API response data or an error response.
     */
    protected static function post($post)
    {
        self::initGBP();
        $tokenInfo = json_decode($post->account->token, false);
        $currentToken = is_string($post->account->token) ? (json_decode($post->account->token, true) ?: []) : [];
        self::$client->setAccessToken($post->account->token);

        // Refresh the access token if necessary:
        if (isset($tokenInfo->refresh_token) && ! empty($tokenInfo->refresh_token)) {
            $refreshed = self::$client->fetchAccessTokenWithRefreshToken($tokenInfo->refresh_token);
            if (is_array($refreshed) && isset($refreshed['error'])) {
                Accounts::where('id', $post->account->id)->update(['status' => 0]);

                return [
                    'status' => 'error',
                    'message' => __('Access Token Expired'),
                    'type' => $post->type,
                ];
            }
            if (is_array($refreshed) && ! empty($refreshed)) {
                $normalized = self::normalizeTokenForRefresh($currentToken, $refreshed);
                Accounts::where('id', $post->account->id)->update(['token' => json_encode($normalized)]);
                self::$client->setAccessToken($normalized);
            }
        }

        // Validate post data:
        $errors = self::validator($post);
        if (! empty($errors)) {
            return [
                'status' => 'error',
                'message' => $errors,
                'type' => $post->type,
            ];
        }

        // Decode post data
        $data = json_decode($post->data, false);
        $medias = $data->medias;
        $options = $data->options;
        $summary = spintax($data->caption);
        $mediaPath = '';

        if (! empty($medias)) {
            $media = Media::url($medias[0]);
            if (Media::isImg($medias[0])) {
                $mediaPath = $media;
            }
        }

        $link = $data->link ?? '';
        if ($link) {
            $summary .= ' '.$link;
        }

        $callToAction = '';
        if (isset($options->gbp_action) && isset($options->gbp_link) && $options->gbp_link != '') {
            $callToAction = ['actionType' => $options->gbp_action, 'url' => $options->gbp_link];
        }

        $params = [
            'summary' => $summary,
            'options' => $options,
            'mediaPath' => $mediaPath,
            'callToAction' => $callToAction,
            'link' => $link,
        ];

        // Ensure the parent's resource name is fully qualified,
        // e.g. "accounts/123456789/locations/987654321"
        $parent = $post->account->pid;

        try {
            return self::handleLocalPostUpload($params, $parent, $post->type);
        } catch (\Exception $e) {
            return self::errorResponse($e->getMessage(), $post->type);
        }
    }

    /**
     * Handle sending a raw HTTP request to create a local post.
     *
     * @param  string  $summary  The local post summary.
     * @param  string  $mediaPath  Optional media file path.
     * @param  string  $parent  The resource name (e.g. "accounts/123456789/locations/987654321").
     * @param  string  $postType  The post type.
     * @return array The API response.
     */
    protected static function handleLocalPostUpload($params, $parent, $postType)
    {
        // Use the v4 endpoint:
        $url = "https://mybusiness.googleapis.com/v4/{$parent}/localPosts";

        $payload = [
            'name' => $params['summary'],
            'summary' => $params['summary'],
            'topicType' => 'STANDARD',
        ];

        if ($params['callToAction']) {
            $payload['callToAction'] = $params['callToAction'];
        }

        $mediaPath = $params['mediaPath'];
        if (! empty($mediaPath)) {
            $payload['media'] = [
                [
                    'mediaFormat' => 'PHOTO',
                    'sourceUrl' => $mediaPath,
                ],
            ];
        }

        $body = json_encode($payload);
        $httpClient = self::$client->getHttpClient();
        $accessToken = self::$client->getAccessToken()['access_token'];

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$accessToken,
        ];

        $request = new Request('POST', $url, $headers, $body);
        $response = $httpClient->send($request);
        $responseData = json_decode($response->getBody()->getContents(), true);

        if (isset($responseData['error'])) {
            return [
                'status' => 0,
                'message' => $responseData['error']['details'][0]['errorDetails'][0]['message'] ?? ($responseData['error']['message'] ?? __('Unknown error')),
            ];
        }

        return [
            'status' => 1,
            'message' => __('Success'),
            'id' => $responseData['name'],
            'url' => $responseData['searchUrl'],
            'type' => $postType,
        ];
    }

    /**
     * Upload media using the v4 media upload endpoint.
     *
     * @param  string  $parent  The fully qualified resource name.
     * @param  string  $mediaPath  File path to the image.
     * @return string The URL of the uploaded media (or empty string on error).
     */
    protected static function uploadMedia($parent, $mediaPath)
    {
        $url = "https://mybusiness.googleapis.com/v4/{$parent}/media";
        $fileData = file_get_contents($mediaPath);
        $httpClient = self::$client->getHttpClient();
        $accessToken = self::$client->getAccessToken()['access_token'];

        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Authorization' => 'Bearer '.$accessToken,
        ];

        $request = new Request('POST', $url, $headers, $fileData);
        $response = $httpClient->send($request);
        $responseData = json_decode($response->getBody()->getContents(), true);

        // Adjust this part based on the actual response structure.
        return isset($responseData['mediaItemData']['url']) ? $responseData['mediaItemData']['url'] : '';
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
