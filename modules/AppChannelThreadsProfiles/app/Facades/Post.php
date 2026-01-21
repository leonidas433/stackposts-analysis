<?php

namespace Modules\AppChannelThreadsProfiles\Facades;

use Illuminate\Support\Facades\Facade;
use Media;
use Modules\AppChannelThreadsProfiles\Classes\Threads;

class Post extends Facade
{
    protected static $threadsAPI;

    protected static function getFacadeAccessor()
    {
        return ex_str(__NAMESPACE__);
    }

    /**
     * Initialize and retrieve the Threads API instance.
     *
     * @return Threads
     */
    protected static function initThreads()
    {
        if (! self::$threadsAPI) {
            self::$threadsAPI = new Threads(
                get_option('threads_app_id', ''),
                get_option('threads_app_secret', ''),
                get_option('threads_callback_url', ''),
                get_option('threads_graph_version', 'v1.0')
            );
        }

        return self::$threadsAPI;
    }

    /**
     * Validate post data.
     *
     * @param  object  $post
     * @return array Array of errors if any.
     */
    protected static function validator($post)
    {
        $errors = [];
        $data = json_decode($post->data, true);
        $medias = $data['medias'] ?? [];

        // For Threads, we currently support only "media" and "link" types.
        if (! in_array($post->type, ['media', 'link'])) {
            // $errors[] = __("Threads API currently supports only 'media' and 'link' types.");
        }

        return $errors;
    }

    /**
     * Main method to publish a post via Threads API.
     *
     * @param  object  $post
     * @return array Result of the post operation.
     */
    protected static function post($post)
    {
        // Decode the JSON data from the post.
        $data = json_decode($post->data, false);
        $medias = $data->medias ?? [];
        $caption = spintax($data->caption);
        $threadsUserId = $post->account->pid;   // Use the account's pid as the Threads user ID.
        $accessToken = $post->account->token;
        $link = isset($data->link) ? $data->link : '';

        try {
            if ($post->type === 'text') {
                // For link posts or text-only posts.
                return self::handleTextThreadPost($threadsUserId, $caption, $post, $accessToken);
            } elseif ($post->type === 'link' || empty($medias)) {
                // For link posts or text-only posts.
                $caption .= ' '.$link;

                return self::handleTextThreadPost($threadsUserId, $caption, $post, $accessToken);
            } elseif (count($medias) === 1) {
                // Single media post: determine whether it's an IMAGE or VIDEO using Media::isImg and Media::isVideo.
                $mediaUrl = $medias[0];
                if (Media::isImg($mediaUrl)) {
                    $mediaType = 'IMAGE';
                } elseif (Media::isVideo($mediaUrl)) {
                    $mediaType = 'VIDEO';
                } else {
                    throw new \Exception('Invalid media. Please verify the file format.');
                }

                return self::handleSingleMediaThreadPost($threadsUserId, $mediaType, $caption, $mediaUrl, $post, $accessToken);
            } else {
                // For multiple media items, ensure all are images (carousel post).
                foreach ($medias as $m) {
                    if (! Media::isImg($m)) {
                        // throw new \Exception("Threads carousel supports images only.");
                    }
                }

                return self::handleCarouselThreadPost($threadsUserId, $medias, $caption, $post, $accessToken);
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => __($e->getMessage()),
                'type' => $post->type,
            ];
        }
    }

    /**
     * Handle text-only (or link) posts on Threads.
     *
     * @param  string  $threadsUserId
     * @param  string  $caption
     * @param  string  $accessToken
     * @return array
     *
     * @throws \Exception
     */
    protected static function handleTextThreadPost($threadsUserId, $caption, $post, $accessToken)
    {
        $threadsApi = self::initThreads();

        // Step 1: Create a media container with media_type = TEXT.
        $createResponse = $threadsApi->post("/{$threadsUserId}/threads", [
            'media_type' => 'TEXT',
            'text' => $caption,
        ], $accessToken);

        if (! isset($createResponse['id'])) {
            throw new \Exception('Failed to create text post container: '.json_encode($createResponse));
        }

        // Step 2: Publish the media container.
        $publishResponse = $threadsApi->post("/{$threadsUserId}/threads_publish", [
            'creation_id' => $createResponse['id'],
        ], $accessToken);

        return [
            'status' => 1,
            'message' => __('Succesed'),
            'id' => $publishResponse['id'],
            'url' => 'https://www.threads.net/@'.$post->account->name,
            'type' => $post->type,
        ];
    }

    /**
     * Handle single media (IMAGE or VIDEO) posts on Threads.
     *
     * @param  string  $threadsUserId
     * @param  string  $mediaType
     * @param  string  $caption
     * @param  string  $mediaUrl
     * @param  string  $accessToken
     * @return array
     *
     * @throws \Exception
     */
    protected static function handleSingleMediaThreadPost($threadsUserId, $mediaType, $caption, $mediaUrl, $post, $accessToken)
    {
        $threadsApi = self::initThreads();

        $params = [
            'media_type' => $mediaType,
            'text' => $caption,
        ];

        if ($mediaType === 'IMAGE') {
            $params['image_url'] = $mediaUrl;
        } elseif ($mediaType === 'VIDEO') {
            $params['video_url'] = $mediaUrl;
        }

        // Step 1: Create the media container.
        $createResponse = $threadsApi->post("/{$threadsUserId}/threads", $params, $accessToken);
        if (! isset($createResponse['id'])) {
            throw new \Exception('Failed to create media post container: '.json_encode($createResponse));
        }

        // Step 2: Publish the media container.
        if (Media::isVideo($mediaUrl)) {
            $totalWait = 0;
            $maxWait = 30; // 2 minutes in seconds.
            $interval = 5;  // Sleep interval in seconds.

            $publishResponse = [];
            while ($totalWait < $maxWait) {
                $publishResponse = $threadsApi->post("/{$threadsUserId}/threads_publish", [
                    'creation_id' => $createResponse['id'],
                ], $accessToken);
                // Nếu phản hồi có key "id", nghĩa là container đã sẵn sàng, dừng chờ.
                if (isset($publishResponse['id'])) {
                    break;
                }

                sleep($interval);
                $totalWait += $interval;
            }
        } else {
            $publishResponse = $threadsApi->post("/{$threadsUserId}/threads_publish", [
                'creation_id' => $createResponse['id'],
            ], $accessToken);
        }

        if (! isset($publishResponse['id'])) {
            throw new \Exception('Failed to create media post container: '.$createResponse['id']);
        }

        return [
            'status' => 1,
            'message' => __('Succesed'),
            'id' => $createResponse['id'],
            'url' => 'https://www.threads.net/@'.$post->account->name,
            'type' => $post->type,
        ];
    }

    /**
     * Handle carousel posts on Threads (multiple images).
     *
     * @param  string  $threadsUserId
     * @param  array  $medias
     * @param  string  $caption
     * @param  string  $accessToken
     * @return array
     *
     * @throws \Exception
     */
    protected static function handleCarouselThreadPost($threadsUserId, $medias, $caption, $post, $accessToken)
    {
        $threadsApi = self::initThreads();

        // Create a media container for each carousel item (is_carousel_item = true).
        $children = [];
        foreach ($medias as $mediaUrl) {
            $params = [
                'text' => '',
                'is_carousel_item' => true,
            ];

            if (Media::isImg($mediaUrl)) {
                $params['media_type'] = 'IMAGE';
                $params['image_url'] = $mediaUrl;
            } else {
                $params['media_type'] = 'VIDEO';
                $params['video_url'] = $mediaUrl;
            }

            $childResponse = $threadsApi->post("/{$threadsUserId}/threads", $params, $accessToken);

            if (! isset($childResponse['id'])) {
                throw new \Exception('Failed to create carousel item container: '.json_encode($childResponse));
            }

            $children[] = $childResponse['id'];
        }

        // Create the main container with the generated children.
        $createResponse = $threadsApi->post("/{$threadsUserId}/threads", [
            'media_type' => 'CAROUSEL',
            'text' => $caption,
            'children' => implode(',', $children),  // Assuming the Threads API supports a 'children' parameter similar to Instagram.
        ], $accessToken);

        if (! isset($createResponse['id'])) {
            throw new \Exception('Failed to create carousel post container: '.json_encode($createResponse));
        }

        // Publish the carousel container.
        $publishResponse = $threadsApi->post("/{$threadsUserId}/threads_publish", [
            'creation_id' => $createResponse['id'],
        ], $accessToken);

        return [
            'status' => 1,
            'message' => __('Succesed'),
            'id' => $createResponse['id'],
            'url' => 'https://www.threads.net/@'.$post->account->name,
            'type' => $post->type,
        ];
    }
}
