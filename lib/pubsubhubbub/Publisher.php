<?php
/**
 * a PHP client library for pubsubhubbub.
 *
 * @link    https://github.com/pubsubhubbub/
 *
 * @author  Josh Fraser | joshfraser.com | josh@eventvue.com
 * @license Apache License 2.0
 */
namespace pubsubhubbub\publisher;

use InvalidArgumentException;

class Publisher
{
    /**
     * @var string
     */
    protected $hub_url;

    /**
     * @var string
     */
    protected $last_response;

    /**
     * Create a new Publisher.
     *
     * @param string $hub_url
     */
    public function __construct($hub_url)
    {
        if (! isset($hub_url)) {
            throw new InvalidArgumentException('Please specify a hub url');
        }

        if (! preg_match('|^https?://|i', $hub_url)) {
            throw new InvalidArgumentException('The specified hub url does not appear to be valid: ' . $hub_url);
        }

        $this->hub_url = $hub_url;
    }

    /**
     * Accepts either a single url or an array of urls.
     *
     * @param string|array $topic_urls
     * @param callable     $http_function
     *
     * @return mixed
     */
    public function publish_update($topic_urls, $http_function = false)
    {
        if (! isset($topic_urls)) {
            throw new InvalidArgumentException('Please specify a topic url');
        }

        // check that we're working with an array
        if (! is_array($topic_urls)) {
            $topic_urls = [$topic_urls];
        }

        // set the mode to publish
        $post_string = 'hub.mode=publish';
        // loop through each topic url
        foreach ($topic_urls as $topic_url) {

            // lightweight check that we're actually working w/ a valid url
            if (! preg_match('|^https?://|i', $topic_url)) {
                throw new InvalidArgumentException('The specified topic url does not appear to be valid: ' . $topic_url);
            }

            // append the topic url parameters
            $post_string .= '&hub.url=' . urlencode($topic_url);
        }

        // make the http post request and return true/false
        // easy to over-write to use your own http function
        if ($http_function) {
            return $http_function($this->hub_url, $post_string);
        }

        return $this->http_post($this->hub_url, $post_string);
    }

    /**
     * Returns any error message from the latest request.
     *
     * @return string
     */
    public function last_response()
    {
        return $this->last_response;
    }

    /**
     * Default http function that uses curl to post to the hub endpoint.
     *
     * @param string $url
     * @param string $post_string
     *
     * @return bool
     */
    private function http_post($url, $post_string)
    {
        // add any additional curl options here
        $options = [
            CURLOPT_URL        => $url,
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => $post_string,
            CURLOPT_USERAGENT  => 'PubSubHubbub-Publisher-PHP/1.0',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $this->last_response = $response;
        $info = curl_getinfo($ch);

        curl_close($ch);

        return $info['http_code'] == 204;
    }
}
