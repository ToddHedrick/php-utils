<?php
require_once 'Logger.php';
// Normally this will be done in another file. Just putting this here to ensure we have it set for this class
$GLOBALS['log'] = (isset($GLOBALS['log']) and $GLOBALS['log'] instanceof Logger) ? $GLOBALS['log'] : new Logger();

/**
 * @author Todd Hedrick
 *
 * Class CurlClient
 */
class CurlClient {
    const CURL_METHOD_PATCH = 'PATCH';
    const CURL_METHOD_POST = 'POST';
    const CURL_METHOD_GET = 'GET';
    const CURL_METHOD_HEAD = 'HEAD';
    const CURL_METHOD_OPTIONS = 'OPTIONS';
    const CURL_METHOD_PUT = 'PUT';
    const CURL_METHOD_DELETE = 'DELETE';

    protected $config = array();

    protected $defaultHeaders = array();

    /** @var string $lasterror_num The last error number. This will be a HTTP >= 400 */
    protected $lasterror_num;

    /** @var string $lasterror_msg The json_encoded return array. This will contain the HTTP code, headers, info, and body, */
    protected $lasterror_msg;

    /**
     * CurlClient constructor.
     * @param array {
     * @param array $config
     * @property bool $config['curl_debug']
     * @property bool $config['use_ssl']
     * @property bool $config['curl_follow_location']
     * @property int $config['timeout']
     * @property int $config['http_version']
     * @property string $config['curl_user_agent']
     */
    public function __construct($config = array()) {
        $this->config = $config;
    }

    public function getLastErrorNum() {
        return $this->lasterror_num;
    }

    public function setLastErrorNum($lasterror_num) {
        $this->lasterror_num = $lasterror_num;
    }

    public function getLastErrorMsg() {
        return $this->lasterror_msg;
    }

    public function setLastErrorMsg($lasterror_msg) {
        $this->lasterror_msg = $lasterror_msg;
    }

    public function clearLastError() {
        $this->lasterror_num = null;
        $this->lasterror_msg = null;
    }

    /**
     * @param string $url
     * @return bool - True if the URL is accessible, false if there is any issue connecting to the resource
     */
    public static function checkResourceAvailability($url) {
        if (!empty($url)) {
            $check_url = $url;

            $parsed_url = parse_url($url);
            if (isset($parsed_url['scheme']) && isset($parsed_url['host'])) {
                $check_url = "{$parsed_url['scheme']}://{$parsed_url['host']}";
                if (isset($parsed_url['port'])) {
                    $check_url .= ":{$parsed_url['port']}";
                }
            }

            if ($curl = curl_init($check_url)) {
                //don't fetch the actual page, you only want to check the connection is ok
                curl_setopt($curl, CURLOPT_NOBODY, true);
                $status = false;
                if ($result = curl_exec($curl)) {
                    //if request was ok, check response code
                    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    if ($http_code >= 200 && $http_code <= 299) {
                        $status = true;
                    }
                }
                curl_close($curl);
                return $status;
            }
        }
        return false;
    }

    /**
     * Use this function to add new default headers that will get included on every HTTP Request.
     * Example: Set the default content-type, accept type, or OAuth Token headers.
     * If a new value is provided to the sendRequest function it will override the default
     *
     * @param string $headerName
     * @param string $headerValue
     * @throws InvalidArgumentException - IF the headerName is not a string then this Exception is thrown
     * @return $this
     */
    public function addDefaultHeader($headerName, $headerValue) {
        if (!is_string($headerName)) {
            throw new \InvalidArgumentException('Header name must be a string.');
        }

        $this->defaultHeaders[$headerName] = $headerValue;
        return $this;
    }

    /**
     * Returns the current array of default HTTP headers
     *
     * @return array
     */
    public function getDefaultHeaders() {
        return $this->defaultHeaders;
    }

    /**
     * Make the HTTP call
     *
     * @param string $url
     * @param string $method - HTTP method that will be used. Use the FCurlClient::CURL_METHOD_* options. Default is CurlClient::CURL_METHOD_GET
     * @param array $queryParams - (optional) parameters to be place in query URL
     * @param array $postData - (optional) parameters to be placed in POST body
     * @param array $headerParams - (optional) parameters to be place in request header
     *
     * @throws Exception when the http_code === 0
     * @return array [
     *      'http_code' => $response_info['http_code'],
     *      'info' => $response_info,
     *      'http_header' => $http_header,
     *      'http_body' => $http_body,
     * ];
     */
    public function sendRequest($url, $method = self::CURL_METHOD_GET, $queryParams = array(), $postData = null, $headerParams = array()) {
        $method = strtoupper($method);
        if (!in_array($method, array(self::CURL_METHOD_PATCH, self::CURL_METHOD_POST, self::CURL_METHOD_GET, self::CURL_METHOD_HEAD, self::CURL_METHOD_OPTIONS, self::CURL_METHOD_PUT, self::CURL_METHOD_DELETE))) {
            throw new \Exception("INVALID HTTP METHOD [{$method}] PROVIDED. Valid options are [" . self::CURL_METHOD_GET . ", " . self::CURL_METHOD_POST . ", " . self::CURL_METHOD_PUT . ", " . self::CURL_METHOD_DELETE . ", " . self::CURL_METHOD_OPTIONS . ", " . self::CURL_METHOD_PATCH . ", " . self::CURL_METHOD_HEAD . "] ");
        }

        $headers = array();
        // construct the http header
        $headerParams = array_merge(
            (array)$this->getDefaultHeaders(),
            (array)$headerParams
        );

        foreach ($headerParams as $key => $val) {
            $headers[] = "$key: $val";
        }

        // form data
        if ($postData and in_array('Content-Type: application/x-www-form-urlencoded', $headers, true)) {
            $postData = http_build_query($postData);
        } elseif ((is_object($postData) or is_array($postData)) and !in_array('Content-Type: multipart/form-data', $headers, true)) { // json model
            $postData = json_encode($postData);
        }

        $curl = curl_init();
        // set timeout, if needed
        if (isset($this->config['timeout']) && $this->config['timeout'] !== 0) {
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($curl, CURLOPT_TIMEOUT, $this->config['timeout']);
        }

        if (isset($this->config['http_version'])) {
            if (in_array($this->config['http_version'], array(CURL_HTTP_VERSION_NONE, CURL_HTTP_VERSION_1_0, CURL_HTTP_VERSION_1_1, CURL_HTTP_VERSION_2_0))) {
                curl_setopt($curl, CURLOPT_HTTP_VERSION, $this->config['http_version']);
            }
        }
        // return the result on success, rather than just true
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // disable SSL verification, if needed
        if (isset($this->config['use_ssl']) && $this->config['use_ssl'] === false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if (!empty($queryParams)) {
            $url = ($url . '?' . http_build_query($queryParams));
        }

        switch ($method) {
            case self::CURL_METHOD_POST:
            case self::CURL_METHOD_PUT:
            case self::CURL_METHOD_DELETE:
            case self::CURL_METHOD_OPTIONS:
            case self::CURL_METHOD_PATCH:
                if ($method === self::CURL_METHOD_POST) {
                    curl_setopt($curl, CURLOPT_POST, true);
                } else {
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
                }
                if (!empty($postData)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
                }
                break;
            case self::CURL_METHOD_HEAD:
                curl_setopt($curl, CURLOPT_NOBODY, true);
                break;
            default:
                break;
        }

        curl_setopt($curl, CURLOPT_URL, $url);

        // Set user agent
        if (!empty($this->config['curl_user_agent'])) {
            curl_setopt($curl, CURLOPT_USERAGENT, $this->config['curl_user_agent']);
        }

        // debugging for curl
        if (isset($this->config['curl_debug']) && $this->config['curl_debug']) {
            $GLOBALS['log']->info(__METHOD__ . "::" . __LINE__
                . PHP_EOL . "---------------------- [INFO] URL: " . print_r($url, 1)
                . PHP_EOL . "---------------------- [INFO] HTTP Headers ~BEGIN~ ----------------------"
                . PHP_EOL . print_r($headers, 1)
                . PHP_EOL . "----------------------  [INFO] HTTP Headers ~END~  ----------------------"
                . PHP_EOL . "---------------------- [INFO] HTTP Request body ~BEGIN~ ----------------------"
                . PHP_EOL . print_r($postData, 1)
                . PHP_EOL . "----------------------  [INFO] HTTP Request body ~END~  ----------------------");
            curl_setopt($curl, CURLOPT_VERBOSE, 1);
        } else {
            curl_setopt($curl, CURLOPT_VERBOSE, 0);
        }

        // obtain the HTTP response headers
        curl_setopt($curl, CURLINFO_HEADER_OUT, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);

        if (isset($this->config['curl_follow_location']) && $this->config['curl_follow_location']) {
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        }

        // Make the request
        $response = curl_exec($curl);
        $http_header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $http_header = $this->httpParseHeaders(substr($response, 0, $http_header_size));
        $http_body = substr($response, $http_header_size);
        $response_info = curl_getinfo($curl);

        // debug HTTP response body
        if (isset($this->config['curl_debug']) && $this->config['curl_debug']) {
            $GLOBALS['log']->info(__METHOD__ . "::" . __LINE__
                . PHP_EOL . "---------------------- [INFO] URL: " . print_r($url, 1)
                . PHP_EOL . "---------------------- [INFO] HTTP Response ~BEGIN~ ----------------------"
                . PHP_EOL . print_r($response, 1)
                . PHP_EOL . "----------------------  [INFO] HTTP Response ~END~  ----------------------"
                . PHP_EOL . "---------------------- [INFO] HTTP Response Info ~BEGIN~ ----------------------"
                . PHP_EOL . print_r($response_info, 1)
                . PHP_EOL . "----------------------  [INFO] HTTP Response Info ~END~  ----------------------");
        }

        $return_array = array(
            'http_code' => $response_info['http_code'],
            'info' => $response_info,
            'http_header' => $http_header,
            'http_body' => $http_body,
        );

        // Handle the response
        if ($response_info['http_code'] === 0) {
            $curl_error_message = curl_error($curl);

            // curl_exec can sometimes fail but still return a blank message from curl_error().
            if (!empty($curl_error_message)) {
                $error_message = "cURL call to [{$url}] failed: [{$curl_error_message}]";
            } else {
                $error_message = "cURL call to [{$url}] failed, but for an unknown reason. This could happen if you are disconnected from the network.";
            }
            $this->lasterror_num = $return_array['http_code'];
            $this->lasterror_msg = $error_message;

        } elseif ($return_array['http_code'] >= 500) {
            $this->lasterror_num = $return_array['http_code'];
            $this->lasterror_msg = json_encode($return_array);
            $GLOBALS['log']->fatal(__METHOD__ . "::" . __LINE__
                . PHP_EOL . "---------------------- [FATAL] {$return_array['http_code']} URL: " . print_r($url, 1)
                . PHP_EOL . "---------------------- [FATAL] HTTP Response ~BEGIN~ ----------------------"
                . PHP_EOL . print_r($return_array, 1)
                . PHP_EOL . "----------------------  [FATAL] HTTP Response ~END~  ----------------------");
        } elseif ($return_array['http_code'] >= 400 && $return_array['http_code'] <= 499) {
            $GLOBALS['log']->error(__METHOD__ . "::" . __LINE__
                . PHP_EOL . "---------------------- [ERROR] {$return_array['http_code']} URL: " . print_r($url, 1)
                . PHP_EOL . "---------------------- [ERROR] HTTP Response ~BEGIN~ ----------------------"
                . PHP_EOL . print_r($return_array, 1)
                . PHP_EOL . "----------------------  [ERROR] HTTP Response ~END~  ----------------------");

            $this->lasterror_num = $return_array['http_code'];
            $this->lasterror_msg = json_encode($return_array);
        }

        return $return_array;
    }

    /**
     * Return an array of HTTP response headers
     *
     * @param string $raw_headers - A string of raw HTTP response headers
     * @return array - Array of HTTP response heaers
     */
    protected function httpParseHeaders($raw_headers) {
        // ref/credit: http://php.net/manual/en/function.http-parse-headers.php#112986
        $headers = array();
        $key = '';

        foreach (explode("\n", $raw_headers) as $h) {
            $h = explode(':', $h, 2);

            if (isset($h[1])) {
                if (!isset($headers[$h[0]])) {
                    $headers[$h[0]] = trim($h[1]);
                } elseif (is_array($headers[$h[0]])) {
                    $headers[$h[0]] = array_merge($headers[$h[0]], [trim($h[1])]);
                } else {
                    $headers[$h[0]] = array_merge([$headers[$h[0]]], [trim($h[1])]);
                }

                $key = $h[0];
            } else {
                if (substr($h[0], 0, 1) === "\t") {
                    $headers[$key] .= "\r\n\t" . trim($h[0]);
                } elseif (!$key) {
                    $headers[0] = trim($h[0]);
                }
                trim($h[0]);
            }
        }

        return $headers;
    }
}
