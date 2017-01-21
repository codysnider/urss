<?php
/**
 * A PHP client library for pubsubhubbub.
 *
 * @link    http://code.google.com/p/pubsubhubbub/
 *
 * @author  Josh Fraser | joshfraser.com | josh@eventvue.com
 * @license Apache License 2.0
 */
namespace Pubsubhubbub\Subscriber;

use InvalidArgumentException;

class Subscriber
{
    /**
     * Put your google key here.
     * Required if you want to use the google feed API to lookup RSS feeds.
     *
     * @var string
     */
    protected $google_key = '';

    /**
     * @var string
     */
    protected $hub_url;

    /**
     * @var string
     */
    protected $callback_url;

    /**
     * @var string
     */
    protected $credentials;

    /**
     * @var string accepted values are "async" and "sync"
     */
    protected $verify = 'async';

    /**
     * @var string
     */
    protected $verify_token;

    /**
     * @var string
     */
    protected $lease_seconds;

    /**
     * Create a new Subscriber (credentials added for SuperFeedr support).
     *
     * @param string $hub_url
     * @param string $callback_url
     * @param string $credentials
     */
    public function __construct($hub_url, $callback_url, $credentials = false)
    {
        if (! isset($hub_url)) {
            throw new InvalidArgumentException('Please specify a hub url');
        }

        if (! preg_match('|^https?://|i', $hub_url)) {
            throw new InvalidArgumentException('The specified hub url does not appear to be valid: ' . $hub_url);
        }

        if (! isset($callback_url)) {
            throw new InvalidArgumentException('Please specify a callback');
        }

        $this->hub_url = $hub_url;
        $this->callback_url = $callback_url;
        $this->credentials = $credentials;
    }

    /**
     * $use_regexp lets you choose whether to use google AJAX feed api (faster, but cached) or a regexp to read from site.
     *
     * @param string   $url
     * @param callable $http_function
     *
     * @return string
     */
    public function find_feed($url, $http_function = false)
    {
        // using google feed API
        $url = "http://ajax.googleapis.com/ajax/services/feed/lookup?key={$this->google_key}&v=1.0&q=" . urlencode($url);
        // fetch the content
        if ($http_function) {
            $response = $http_function($url);
        } else {
            $response = $this->http($url);
        }

        $result = json_decode($response, true);
        $rss_url = $result['responseData']['url'];

        return $rss_url;
    }

    /**
     * Subscribe to a topic.
     *
     * @param string   $topic_url
     * @param callable $http_function
     *
     * @return mixed
     */
    public function subscribe($topic_url, $http_function = false)
    {
        return $this->change_subscription('subscribe', $topic_url, $http_function);
    }

    /**
     * Unsubscribe from a topic.
     *
     * @param string   $topic_url
     * @param callable $http_function
     *
     * @return mixed
     */
    public function unsubscribe($topic_url, $http_function = false)
    {
        return $this->change_subscription('unsubscribe', $topic_url, $http_function);
    }

    /**
     * Helper function since sub/unsub are handled the same way.
     *
     * @param string   $mode
     * @param string   $topic_url
     * @param callable $http_function
     *
     * @return mixed
     */
    private function change_subscription($mode, $topic_url, $http_function = false)
    {
        if (! isset($topic_url)) {
            throw new InvalidArgumentException('Please specify a topic url');
        }

        // lightweight check that we're actually working w/ a valid url
        if (! preg_match('|^https?://|i', $topic_url)) {
            throw new InvalidArgumentException('The specified topic url does not appear to be valid: ' . $topic_url);
        }

        // set the mode subscribe/unsubscribe
        $post_string = 'hub.mode=' . $mode;
        $post_string .= '&hub.callback=' . urlencode($this->callback_url);
        $post_string .= '&hub.verify=' . $this->verify;
        $post_string .= '&hub.verify_token=' . $this->verify_token;
        $post_string .= '&hub.lease_seconds=' . $this->lease_seconds;

        // append the topic url parameters
        $post_string .= '&hub.topic=' . urlencode($topic_url);

        // make the http post request and return true/false
        // easy to over-write to use your own http function
        if ($http_function) {
            return call_user_func_array($http_function, [$this->hub_url, $post_string]);
        }

        return $this->http($this->hub_url, $post_string);
    }

    /**
     * Default http function that uses curl to post to the hub endpoint.
     *
     * @param string $url
     * @param string $post_string
     *
     * @return mixed
     */
    private function http($url, $post_string)
    {

        // add any additional curl options here
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_USERAGENT      => 'PubSubHubbub-Subscriber-PHP/1.0',
            CURLOPT_RETURNTRANSFER => true,
        ];

        if ($post_string) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $post_string;
        }

        if ($this->credentials) {
            $options[CURLOPT_USERPWD] = $this->credentials;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        // all good -- anything in the 200 range
        if (substr($info['http_code'], 0, 1) == '2') {
            return $response;
        }

        return false;
    }
}
