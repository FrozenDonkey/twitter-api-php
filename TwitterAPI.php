<?php

/**
 * Fork of James Mallison <me@j7mbo.co.uk> Twitter-API-PHP
 * by Christian Figge <christian@frozendonkey.com>
 *
 * Refactored some parts, added the possibility to tweet
 * images/videos and a more easy way to tweet:
 *
 * Example:
 *
 * $twitter = new TwitterAPI(
 *     "YOUR_OAUTH_ACCESS_TOKEN",
 *     "YOUR_OAUTH_ACCESS_TOKEN_SECRET",
 *     "YOUR_CONSUMER_KEY",
 *     "YOUR_CONSUMER_SECRET"
 * );
 *
 * echo $twitter->tweet("THIS IS YOUR TWEET");
 * echo $twitter->tweetImage('THIS IS A TWEET WITH AN IMAGE', 'path/to/image.jpg');
 *
 * @package  Twitter-API-PHP
 * @author   James Mallison <me@j7mbo.co.uk>
 * @author   Christian Figge <christian@frozendonkey.com>
 * @license  MIT License
 * @version  1.0.4
 * @link     https://github.com/FrozenDonkey/twitter-api-php
 * @link     https://github.com/j7mbo/twitter-api-php
 */
class TwitterAPI {
    private $_methods = ['post', 'get'];
    private $_urls = [
        'tweet' => [
            'url' => 'https://api.twitter.com/1.1/statuses/update.json',
            'method' => 'POST'
        ],
        'upload' => [
            'url' => 'https://upload.twitter.com/1.1/media/upload.json',
            'method' => 'POST'
        ]
    ];

    private $_oAuthAccessToken;
    private $_oAuthAccessTokenSecret;
    private $_consumerKey;
    private $_consumerSecret;

    private $_httpStatusCode;
    private $_oAuth;
    private $_method;
    private $_url;
    private $_posts;
    private $_gets;

    /**
     * TwitterAPI constructor.
     * Requires cURL extension
     *
     * @throws \InvalidArgumentException When incomplete settings parameters are provided
     *
     * @param $oAuthAccessToken
     * @param $oAuthAccessTokenSecret
     * @param $consumerKey
     * @param $consumerSecret
     */
    public function __construct(
        $oAuthAccessToken,
        $oAuthAccessTokenSecret,
        $consumerKey,
        $consumerSecret
    ) {
        $this->_checkForCurl();

        if(strlen($oAuthAccessToken) < 1) {
            throw new InvalidArgumentException('You must provide a valid access token.');
        }

        if(strlen($oAuthAccessTokenSecret) < 1) {
            throw new InvalidArgumentException('You must provide a valid access token secret.');
        }

        if(strlen($consumerKey) < 1) {
            throw new InvalidArgumentException('You must provide a valid consumer key.');
        }

        if(strlen($consumerSecret) < 1) {
            throw new InvalidArgumentException('You must provide a valid consumer secret.');
        }

        $this->_oAuthAccessToken = $oAuthAccessToken;
        $this->_oAuthAccessTokenSecret = $oAuthAccessTokenSecret;
        $this->_consumerKey = $consumerKey;
        $this->_consumerSecret = $consumerSecret;
    }

    /**
     * Send a new tweet
     *
     * @throws \InvalidArgumentException When no text to tweet is provided
     *
     * @param $text
     * @param string $media
     */
    public function tweet($text, $media = '') {
        if(strlen($text) < 1) {
            throw new InvalidArgumentException('No text to tweet.');
        }
        $this->_flush();
        $fields = [
            'status' => $text
        ];

        if(strlen($media) > 0) {
            $fields['media_ids'] = $media;
        }

        $this->buildOAuth(
            $this->_urls['tweet']['url'],
            $this->_urls['tweet']['method']
        );

        $this->setPosts($fields);
        $response = $this->performRequest();
        if(strlen($response) > 0) {
            $response = json_decode($response, true);
            if(isset($response['created_at'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tweet an image
     *
     * @throws \InvalidArgumentException When no text to tweet is provided
     * @throws \InvalidArgumentException When no image is provided
     *
     * @param $text
     * @param $imagePath
     * @return bool
     */
    public function tweetImage($text, $imagePath) {
        if(strlen($text) < 1) {
            throw new InvalidArgumentException('No text to tweet.');
        }
        if(!is_file($imagePath) || !is_readable($imagePath)) {
            throw new InvalidArgumentException('Image could not be found.');
        }

        $this->_flush();

        $fields = [
            'media' => [
                'file' => $imagePath
            ]
        ];

        $this->buildOAuth(
            $this->_urls['upload']['url'],
            $this->_urls['upload']['method']
        );

        $this->setPosts($fields);
        $response = $this->performRequest();
        if (strlen($response) > 0) {
            $data = json_decode($response, true);
            if (isset($data['media_id'])) {
                return $this->tweet($text, $data['media_id']);
            }
        }
        return false;
    }

    /**
     * Set posts array, example: array('screen_name' => 'J7mbo')
     *
     * @param array $array Array of parameters to send to API
     * @throws \Exception When you are trying to set both get and post fields
     *
     * @return TwitterAPIExchange Instance of self for method chaining
     */
    public function setPosts(array $array) {
        $this->_posts = [];
        if (!is_null($this->_getGets())) {
            throw new Exception('You can only choose get OR post fields.');
        }

        if (isset($array['status']) && substr($array['status'], 0, 1) === '@') {
            $array['status'] = sprintf("\0%s", $array['status']);
        }

        foreach ($array as $key => &$value) {
            if (is_bool($value)) {
                $value = ($value === true) ? 'true' : 'false';
            }
        }

        $this->_posts = $array;

        // rebuild oAuth
        if (isset($this->_oAuth['oauth_signature'])) {
            $this->buildOauth($this->_url, $this->_method);
        }

        return $this;
    }

    /**
     * Set getfield string, example: '?screen_name=J7mbo'
     *
     * @param string $string Get key and value pairs as string
     * @throws \Exception
     *
     * @return \TwitterAPIExchange Instance of self for method chaining
     */
    public function setGets($string) {
        $this->_gets = [];
        if (!is_null($this->_getPosts())) {
            throw new Exception('You can only choose get OR post fields.');
        }

        $gets = preg_replace('/^\?/', '', explode('&', $string));
        $params = array();

        foreach ($gets as $field) {
            if ($field !== '') {
                list($key, $value) = explode('=', $field);
                $params[$key] = $value;
            }
        }

        $this->_gets = '?' . http_build_query($params);

        return $this;
    }

    public function buildOauth($url, $method) {
        if (!in_array(strtolower($method), $this->_methods)) {
            throw new Exception('Request method must be ' . implode(' or ', $this->_methods));
        }

        $oAuth = array(
            'oauth_consumer_key' => $this->_consumerKey,
            'oauth_token' => $this->_oAuthAccessToken,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_nonce' => time(),
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0'
        );

        $getfield = $this->_getGets();

        if (!is_null($getfield)) {
            $gets = str_replace('?', '', explode('&', $getfield));

            foreach ($gets as $g) {
                $split = explode('=', $g);

                // In case a null is passed through
                if (isset($split[1])) {
                    $oAuth[$split[0]] = urldecode($split[1]);
                }
            }
        }

        $posts = $this->_getPosts();

        if (!is_null($posts)) {
            foreach ($posts as $key => $value) {
                $oAuth[$key] = $value;
            }
        }

        $base_info = $this->_buildBaseString($url, $method, $oAuth);
        $composite_key = rawurlencode($this->_consumerSecret) . '&' . rawurlencode($this->_oAuthAccessTokenSecret);
        $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
        $oAuth['oauth_signature'] = $oauth_signature;
        $this->_oAuth = $oAuth;
        $this->_method = $method;
        $this->_url = $url;
        return $oAuth;
    }

    /**
     * Perform the actual data retrieval from the API
     *
     * @param boolean $return      If true, returns data. This is left in for backward compatibility reasons
     * @param array   $curlOptions Additional Curl options for this request
     * @throws \Exception
     *
     * @return string json If $return param is true, returns json data.
     */
    public function performRequest($return = true, $curlOptions = array()) {
        if (!is_bool($return)) {
            throw new Exception('performRequest parameter must be true or false');
        }

        $header =  array($this->_buildAuthorizationHeader($this->_oAuth), 'Expect:');

        $getfield = $this->_getGets();
        $posts = $this->_getPosts();

        $options = array(
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_HEADER => false,
                CURLOPT_URL => $this->_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ) + $curlOptions;


        if (!is_null($posts) && !isset($posts['media'])) {
            $options[CURLOPT_POSTFIELDS] = http_build_query($posts);
        } else {
            if ($getfield !== '') {
                $options[CURLOPT_URL] .= $getfield;
            }
        }

        if(isset($posts['media'])) {
            $mediaCurlOptions = $this->_curlSetMediaHeaders(
                $posts['media']['file']
            );
            $options = $options + $mediaCurlOptions;
        }

        $feed = curl_init();
        curl_setopt_array($feed, $options);
        $json = curl_exec($feed);

        $this->_httpStatusCode = curl_getinfo($feed, CURLINFO_HTTP_CODE);


        if (($error = curl_error($feed)) !== '') {
            curl_close($feed);
            throw new \Exception($error);
        }

        curl_close($feed);

        return $json;
    }

    /**
     * Helper method to perform our request
     *
     * @param string $url
     * @param string $method
     * @param string $data
     * @param array  $curlOptions
     * @throws \Exception
     *
     * @return string The json response from the server
     */
    public function request($url, $method = 'get', $data = null, $curlOptions = array()) {
        if (strtolower($method) === 'get') {
            $this->setGets($data);
        } else {
            $this->setPosts($data);
        }

        return $this->buildOauth($url, $method)->performRequest(true, $curlOptions);
    }

    /**
     * Get the HTTP status code for the previous request
     *
     * @return integer
     */
    public function getHttpStatusCode() {
        return $this->_httpStatusCode;
    }

    private function _getGets() {
        return $this->_gets;
    }

    private function _getPosts() {
        return $this->_posts;
    }

    /**
     * Private method to generate the base string used by cURL
     *
     * @param string $baseURI
     * @param string $method
     * @param array  $params
     *
     * @return string Built base string
     */
    private function _buildBaseString($baseURI, $method, $params) {
        $return = array();
        ksort($params);

        foreach($params as $key => $value) {
            if (is_array($value)) continue;
            $return[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $return));
    }

    /**
     * Private method to generate authorization header used by cURL
     *
     * @param array $oauth Array of oauth data generated by buildOauth()
     *
     * @return string $return Header used by cURL for request
     */
    private function _buildAuthorizationHeader(array $oauth) {
        $return = 'Authorization: OAuth ';
        $values = array();

        foreach($oauth as $key => $value) {
            if (in_array($key, array('oauth_consumer_key', 'oauth_nonce', 'oauth_signature',
                'oauth_signature_method', 'oauth_timestamp', 'oauth_token', 'oauth_version'))) {
                $values[] = "$key=\"" . rawurlencode($value) . "\"";
            }
        }

        $return .= implode(', ', $values);
        return $return;
    }

    /**
     * Build HTTP header data for uploading an image
     * @param $filename
     * @return array
     */
    private function _curlSetMediaHeaders($filename) {
        return array(
            CURLOPT_POSTFIELDS => ['media' => file_get_contents($filename)],
            CURLOPT_HTTPHEADER => array(
                "Content-Type: multipart/form-data;",
            )
        );
    }

    /**
     * Flush all data from previous tweets
     */
    private function _flush() {
        $this->_httpStatusCode  = null;
        $this->_oAuth           = null;
        $this->_method          = null;
        $this->_url             = null;
        $this->_posts           = null;
        $this->_gets            = null;
    }

    /**
     * Checks if cURL is installed
     *
     * @throws \RuntimeException When cURL was not found
     */
    private function _checkForCurl() {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('TwitterAPIExchange requires cURL extension to be loaded, see: http://curl.haxx.se/docs/install.html');
        }
    }
}