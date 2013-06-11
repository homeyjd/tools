<?php
/*
 * Authored by Josh Fraser (www.joshfraser.com)
 * Released under Apache License 2.0
 * Maintained by Alexander Makarov, http://rmcreative.ru/
$Id$
 *
 * Heavily edited by Jesse Decker <jesse.decker@am.sony.com>
 * Including changes from multiple GitHub repos.
 */

/**
 * Class that represents a single curl request
 */
class RollingCurlRequest
{
    public $url = false;
    public $method = 'GET';
    public $post_data = null;
    public $headers = null;
    public $options = null;
    public $callback = null;

    /**
     * @param string $url
     * @param string $method
     * @param  $post_data
     * @param  $headers
     * @param  $options
     * @return void
     */
    function __construct($url, $method = "GET", array $post_data = null, array $headers = null, array $options = null)
    {
        $this->url = $url;
        $this->method = strtoupper($method);
        $this->post_data = $post_data;
        $this->headers = $headers;
        $this->options = $options;

        if ($this->method !== 'GET' && $this->method !== 'POST') {
            $this->method = empty($post_data) ? 'GET' : 'POST';
        }
    }

    /**
   * Explicitly unsets to clear up some memory.
     * @return void
     */
    public function __destruct()
    {
        unset($this->url, $this->method, $this->post_data, $this->headers, $this->options);
    }
}

/**
 * RollingCurl custom exception
 */
class RollingCurlException extends Exception
{
}

/**
 * Class that holds a rolling queue of curl requests.
 *
 * @throws RollingCurlException
 */
class RollingCurl
{
    /**
     * @var int
     *
     * Window size is the max number of simultaneous connections allowed.
     *
     * REMEMBER TO RESPECT THE SERVERS:
     * Sending too many requests at one time can easily be perceived
     * as a DOS attack. Increase this window_size if you are making requests
     * to multiple servers or have permission from the receving server admins.
     */
    public $window_size = 5;

    /**
     * @var float
     *
     * Timeout is the timeout used for curl_multi_select.
     */
    public $multi_timeout = 1;

    /**
     * @var string|array
     *
     * Callback function to be applied to each result.
     */
    public $callback = false;

    /**
     * @var array
     *
     * Set your base options that you want to be used with EVERY request.
     */
    public $options = array(CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_RETURNTRANSFER => true,);

    /**
     * @var array
     */
    public $headers = array();

    /**
     * @var Request[]
     *
     * The request queue
     */
    public $requests = array();

    /**
     * @param  $callback
     * Callback function to be applied to each result.
     *
     * Can be specified as 'my_callback_function'
     * or array($object, 'my_callback_method').
     *
     * Function should take three parameters: $response, $info, $request.
     * $response is response body, $info is additional curl info.
     * $request is the original request
     *
     * @return void
     */
    function __construct($callback = null)
    {
        $this->callback = $callback;
    }

    /**
     * @param string $name
     * @return mixed
     *
    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        } else {
            trigger_error(__CLASS__.': Undefined property "'.$name.'"', E_USER_NOTICE);
            return null;
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return mixed
     *
    public function __set($name, $value)
    {
        // append the base options & headers
        if ($name == "options" || $name == "headers") {
            $this->{$name} = $value + $this->{$name};
        } elseif (!isset($this->{$name})) {
            trigger_error(__CLASS__.': Undefined property "'.$name.'"', E_USER_NOTICE);
            return null;
        } else {
            $this->{$name} = $value;
        }
        return $this->{$name};
    }

    /**
     * Add a request to the request queue
     *
     * @param Request $request
     * @return bool
     */
    public function add(RollingCurlRequest $request)
    {
        $this->requests[] = $request;
        return true;
    }

    /**
     * Create new Request and add it to the request queue
     *
     * @param string $url
     * @param string $method
     * @param  $post_data
     * @param  $headers
     * @param  $options
     * @return bool
     */
    public function request($url, $method = "GET", array $post_data = null, array $headers = null, array $options = null)
    {
        $this->requests[] = new RollingCurlRequest($url, $method, $post_data, $headers, $options);
        return true;
    }

    /**
     * Perform GET request
     *
     * @param string $url
     * @param  $headers
     * @param  $options
     * @return bool
     */
    public function get($url, $headers = null, $options = null) {
        return $this->request($url, "GET", null, $headers, $options);
    }

    /**
     * Perform POST request
     *
     * @param string $url
     * @param  $post_data
     * @param  $headers
     * @param  $options
     * @return bool
     */
    public function post($url, $post_data = null, $headers = null, $options = null) {
        return $this->request($url, "POST", $post_data, $headers, $options);
    }

    /**
     * Execute all requests in the queue.
     *
     * @param int $window_size Max number of simultaneous connections
     * @return string|bool
     */
    public function execute($window_size = null)
    {
        // rolling curl window must always be greater than 1
        if (count($this->requests) == 1) {
            return $this->single_curl();
        } else {
            // start the rolling curl. window_size is the max number of simultaneous connections
            return $this->rolling_curl($window_size);
        }
    }

    /**
     * Performs a single curl request
     *
     * @access private
     * @return string
     */
    private function single_curl()
    {
        $ch = curl_init();
        $request = array_shift($this->requests);
        $options = $this->get_options($request);
        curl_setopt_array($ch, $options);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);
        $info += array('errno' => curl_errno($ch), 'error' => curl_error($ch));

        // it's not neccesary to set a callback for one-off requests
        if (is_callable($this->callback)) {
            call_user_func($this->callback, $output, $info, $request);
        }

        // send the return values to the callback function.
        if (is_callable($request->callback)) {
            call_user_func($request->callback, $output, $info, $request);
        }

        return true;
    }

    /**
     * Performs multiple curl requests
     *
     * @access private
     * @throws RollingCurlException
     * @param int $window_size Max number of simultaneous connections
     * @return bool
     */
    private function rolling_curl($window_size = null)
    {
        if (!$window_size) {
            $window_size = $this->window_size;
		}

		$num_requests = count($this->requests);

        // make sure the rolling window isn't greater than the # of urls
        if ($num_requests < $window_size)
            $window_size = $num_requests;

        $master = curl_multi_init();
        $requestMap = array();

        // start the first batch of requests
        for ($i = 0; $i < $window_size; $i++) {
            $request = array_shift($this->requests);

            $options = $this->get_options($request);
            $ch = curl_init();
            curl_setopt_array($ch, $options);
            curl_multi_add_handle($master, $ch);

            // save so can access callback later
            $key = (string) $ch; // contains unique resource ID
            $requestMap[$key] = $request;
        }

        $running = true;
        $exceptionInCallback = null;

        while ($running) {
            // start up any waiting requests
            while (($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);

            // check that curl is ok (this will rarely ever fail)
            if ($execrun !== CURLM_OK)
                break;

            // Block for data in / output; error handling is done by curl_multi_exec
            if ($running) {
                // Must not be null! Fix for cURL yelling frequently
                if ($this->multi_timeout) {
                    curl_multi_select($master, floatval($this->multi_timeout));
                } else {
                    curl_multi_select($master);
                }
            }

            // check if there is a waiting message
            while ($done = curl_multi_info_read($master)) {

                // maybe the message is a progress indicator?
                if ($done['msg'] !== CURLMSG_DONE) {
                    continue;
                }

                // get the info and content returned on the request
                $info = (array) curl_getinfo($done['handle']);
                $info += array('errno' => curl_errno($done['handle']), 'error' => curl_error($done['handle']));
                $output = curl_multi_getcontent($done['handle']);
                $key = (string) $done['handle'];
                $request = $requestMap[$key];
                unset($requestMap[$key]);

                if (!$request) {
                    trigger_error(__CLASS__.': received cURL handle for unknown resource '.$key, E_USER_WARNING);
                } else {

                    // Catch any thrown exception in callback
                    // Possible source of major memory leak
                    if (!$exceptionInCallback) {
                        try {
                            // send the return values to the callback function
                            if (is_callable($request->callback)) {
                                call_user_func($request->callback, $output, $info, $request);
                            }
                            if (is_callable($this->callback)) {
                                call_user_func($this->callback, $output, $info, $request);
                            }
                        } catch (\Exeption $e) {
                            $exceptionInCallback = $e;
                            $running = false;
                        }
                    }
                }

                // start a new request (it's important to do this before removing the old one)
                if ($running && count($this->requests)) {
                    $request = array_shift($this->requests);

                    $options = $this->get_options($request);
                    $ch = curl_init();
                    curl_setopt_array($ch, $options);
                    curl_multi_add_handle($master, $ch);

                    // save so can access callback later
                    $key = (string) $ch; // contains unique resource ID
                    $requestMap[$key] = $request;
                }

                // remove the curl handle that just completed
                curl_multi_remove_handle($master, $done['handle']);
                curl_close($done['handle']);
            }
        }

        curl_multi_close($master);

        if (isset($exceptionInCallback)) {
            throw $exceptionInCallback;
        }

        return true;
    }

    /**
     * Helper function to set up a new request by setting the appropriate options
     *
     * @access private
     * @param Request $request
     * @return array
     */
    private function get_options(RollingCurlRequest $request)
    {
        // options for this entire curl object
        $options = $this->options; // implicit array copy
        $safe_mode = ini_get('safe_mode');
        if ($safe_mode === 'Off' || !$safe_mode) {
            $options[CURLOPT_FOLLOWLOCATION] = 1;
            $options[CURLOPT_MAXREDIRS] = 5;
        }
        $headers = array_merge(array(), $this->headers);

        // append custom options for this specific request
        if ($request->options) {
            $options = $request->options + $options;
        }

        // set the request URL
        $options[CURLOPT_URL] = $request->url;

        // posting data w/ this request?
        if ($request->post_data) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $request->post_data;
        }
        if ($headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        return $options;
    }

    /**
	 * Explicitly unsets to clear memory.
     * @return void
     */
    public function __destruct() {
        unset($this->window_size, $this->callback, $this->options, $this->headers, $this->requests);
	}

} //class
