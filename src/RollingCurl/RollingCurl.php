<?php

/**
 * A cURL library to fetch a large number of resources while maintaining
 * a consistent number of simultaneous connections
 *
 * @package RollingCurl
 * @version 2.0
 * @author Jeff Minard (http://jrm.cc/)
 * @author Josh Fraser (www.joshfraser.com)
 * @author Alexander Makarov (http://rmcreative.ru/)
 * @license Apache License 2.0
 * @link https://github.com/chuyskywalker/rolling-curl
 */

namespace RollingCurl;

use RollingCurl\Request;


/**
 * Class that holds a rolling queue of curl requests.
 */
class RollingCurl
{

    /**
     * @var int
     *
     * Max number of simultaneous requests.
     */
    private $simultaneousLimit = 5;

    /**
     * @var int
     *
     * Timeout is the timeout used for curl_multi_select.
     */
    private $timeout = 10;

    /**
     * @var \Closure
     *
     * Callback function to be applied to each result.
     */
    private $callback;

    /**
     * @var array
     *
     * Set your base options that you want to be used with EVERY request. (Can be overridden individually)
     */
    protected $options = array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 30,
    );

    /**
     * @var array
     */
    private $headers = array();

    /**
     * @var Request[]
     *
     * Requests queued to be processed
     */
    private $pendingRequests = array();

    /**
     * @var Request[]
     *
     * Requests currently being processed by curl
     */
    private $activeRequests = array();

    /**
     * @var Request[]
     *
     * All processed requests
     */
    private $completedRequests = array();

    /**
     * @var int
     *
     * A count of executed calls
     *
     * While you can count() on pending/active, completed may be cleared.
     */
    private $completedRequestCount = 0;


    /**
     * Add a request to the request queue
     *
     * @param Request $request
     * @return RollingCurl
     */
    public function add(Request $request)
    {
        $this->pendingRequests[] = $request;
        return $this;
    }

    /**
     * Create new Request and add it to the request queue
     *
     * @param string $url
     * @param string $method
     * @param array|string $postData
     * @param array $headers
     * @param array $options
     * @return RollingCurl
     */
    public function request($url, $method = "GET", $postData = null, $headers = null, $options = null)
    {
        $newRequest = new Request($url, $method);
        if ($postData) {
            $newRequest->setPostData($postData);
        }
        if ($headers) {
            $newRequest->setHeaders($headers);
        }
        if ($options) {
            $newRequest->setOptions($options);
        }
        return $this->add($newRequest);
    }

    /**
     * Perform GET request
     *
     * @param string $url
     * @param array $headers
     * @param array $options
     * @return RollingCurl
     */
    public function get($url, $headers = null, $options = null)
    {
        return $this->request($url, "GET", null, $headers, $options);
    }

    /**
     * Perform POST request
     *
     * @param string $url
     * @param array|string $postData
     * @param array $headers
     * @param array $options
     * @return RollingCurl
     */
    public function post($url, $postData = null, $headers = null, $options = null)
    {
        return $this->request($url, "POST", $postData, $headers, $options);
    }

    /**
     * Perform PUT request
     *
     * @param  string      $url
     * @param  null        $putData
     * @param  array       $headers
     * @param  array       $options
     *
     * @return RollingCurl
     */
    public function put($url, $putData = null, $headers = null, $options = null)
    {
        return $this->request($url, "PUT", $putData, $headers, $options);
    }


    /**
     * Perform DELETE request
     *
     * @param  string      $url
     * @param  array       $headers
     * @param  array       $options
     *
     * @return RollingCurl
     */
    public function delete($url, $headers = null, $options = null)
    {
        return $this->request($url, "DELETE", null, $headers, $options);
    }

    /**
     * Run all queued requests
     *
     * @return void
     */
    public function execute()
    {

        $master = curl_multi_init();

        // start the first batch of requests
        $firstBatch = $this->getNextPendingRequests($this->getSimultaneousLimit());

        // what a silly "error"
        if (count($firstBatch) == 0) {
            return;
        }

        foreach ($firstBatch as $request) {
            // setup the curl request, queue it up, and put it in the active array
            $ch      = curl_init();
            $options = $this->prepareRequestOptions($request);
            curl_setopt_array($ch, $options);
            curl_multi_add_handle($master, $ch);
            $this->activeRequests[(string)$ch] = $request;
        }

        do {

            while (($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);

            if ($execrun != CURLM_OK) {
                // todo: throw exception
                break;
            }

            // a request was just completed -- find out which one
            while ($transfer = curl_multi_info_read($master)) {

                // get the request object back and put the curl response into it
                $key     = (string)$transfer['handle'];
                $request = $this->activeRequests[$key];
                $request->setResponseText(curl_multi_getcontent($transfer['handle']));
                $request->setResponseErrno(curl_errno($transfer['handle']));
                $request->setResponseError(curl_error($transfer['handle']));
                $request->setResponseInfo(curl_getinfo($transfer['handle']));

                // remove the request from the list of active requests
                unset($this->activeRequests[$key]);

                // move the request to the completed set
                $this->completedRequests[] = $request;
                $this->completedRequestCount++;

                // start a new request (it's important to do this before removing the old one)
                if ($nextRequest = $this->getNextPendingRequest()) {
                    // setup the curl request, queue it up, and put it in the active array
                    $ch      = curl_init();
                    $options = $this->prepareRequestOptions($nextRequest);
                    curl_setopt_array($ch, $options);
                    curl_multi_add_handle($master, $ch);
                    $this->activeRequests[(string)$ch] = $nextRequest;
                }

                // remove the curl handle that just completed
                curl_multi_remove_handle($master, $transfer['handle']);

                // if there is a callback, run it
                if (is_callable($this->callback)) {
                    $callback = $this->callback;
                    $callback($request, $this);
                }

            }

            if ($running) {
                curl_multi_select($master, $this->timeout);
            }

            // keep the loop going as long as multi_exec says it is running
        } while ($running);

        curl_multi_close($master);
    }


    /**
     * Helper function to gather all the curl options: global, inferred, and per request
     *
     * @param Request $request
     * @return array
     */
    private function prepareRequestOptions(Request $request)
    {

        // options for this entire curl object
        $options = $this->getOptions();

        // set the request URL
        $options[CURLOPT_URL] = $request->getUrl();

        // set the request method
        $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();

        // posting data w/ this request?
        if ($request->getPostData()) {
            $options[CURLOPT_POST]       = 1;
            $options[CURLOPT_POSTFIELDS] = $request->getPostData();
        }

        // if the request has headers, use those, or if there are global headers, use those
        if ($request->getHeaders()) {
            $options[CURLOPT_HEADER]     = 0;
            $options[CURLOPT_HTTPHEADER] = $request->getHeaders();
        } elseif ($this->getHeaders()) {
            $options[CURLOPT_HEADER]     = 0;
            $options[CURLOPT_HTTPHEADER] = $this->getHeaders();
        }

        // if the request has options set, use those and have them take precedence
        if ($request->getOptions()) {
            $options = $request->getOptions() + $options;
        }

        return $options;
    }

    /**
     * Define an anonymous callback to handle the response:
     *
     *     $rc = new RollingCurl()
     *     $rc->setCallback(function($response, $info, $request, $rolling_curl) {
     *         // process
     *     });
     *
     * Function should take four parameters: $response, $info, $request, $rolling_callback.
     * $response is response body
     * $info is additional curl info
     * $request is the original request
     * $rolling_curl is the current instance of the RollingCurl (useful if you want to requeue a URL)
     *
     * @param \Closure $callback
     * @return RollingCurl
     */
    public function setCallback(\Closure $callback)
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * @return \Closure
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * @param array $headers
     * @throws \InvalidArgumentException
     * @return RollingCurl
     */
    public function setHeaders($headers)
    {
        if (!is_array($headers)) {
            throw new \InvalidArgumentException("headers must be an array");
        }
        $this->headers = $headers;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $options
     * @throws \InvalidArgumentException
     * @return RollingCurl
     */
    public function setOptions($options)
    {
        if (!is_array($options)) {
            throw new \InvalidArgumentException("options must be an array");
        }
        $this->options = $options;
        return $this;
    }

    /**
     * Override and add options
     *
     * @param array $options
     * @throws \InvalidArgumentException
     * @return RollingCurl
     */
    public function addOptions($options)
    {
        if (!is_array($options)) {
            throw new \InvalidArgumentException("options must be an array");
        }
        $this->options = $options + $this->options;
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param int $timeout
     * @throws \InvalidArgumentException
     * @return RollingCurl
     */
    public function setTimeout($timeout)
    {
        if (!is_int($timeout) || $timeout < 0) {
            throw new \InvalidArgumentException("Timeout must be an int >= 0");
        }
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * @return float
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set the limit for how many cURL requests will be execute simultaneously.
     *
     * Please be mindful that if you set this too high, requests are likely to fail
     * more frequently or automated software may perceive you as a DOS attack and
     * automatically block further requests.
     *
     * @param int $count
     * @throws \InvalidArgumentException
     * @return RollingCurl
     */
    public function setSimultaneousLimit($count)
    {
        if (!is_int($count) || $count < 2) {
            throw new \InvalidArgumentException("setSimultaneousLimit count must be an int >= 2");
        }
        $this->simultaneousLimit = $count;
        return $this;
    }

    /**
     * @return int
     */
    public function getSimultaneousLimit()
    {
        return $this->simultaneousLimit;
    }

    /**
     * Return the next $limit pending requests (may return nothing)
     *
     * @param int $limit
     * @return Request[]
     */
    public function getNextPendingRequests($limit = 1)
    {
        return array_splice($this->pendingRequests, 0, $limit);
    }

    /**
     * Return the next pending requests (may return nothing)
     *
     * @return Request|null
     */
    public function getNextPendingRequest()
    {
        $next = $this->getNextPendingRequests();
        if (count($next)) {
            return $next[0];
        }
        return null;
    }

    /**
     * @return Request[]
     */
    public function getCompletedRequests()
    {
        return $this->completedRequests;
    }

    /**
     * @param bool $useArray count the completedRequests array is true. Otherwise use the global counter.
     * @return int
     */
    public function countCompleted($useArray=false)
    {
        return $useArray ? count($this->completedRequests) : $this->completedRequestCount;
    }

    /**
     * @return int
     */
    public function countPending()
    {
        return count($this->pendingRequests);
    }

    /**
     * @return int
     */
    public function countActive()
    {
        return count($this->activeRequests);
    }

    /**
     * Clear out all completed requests
     *
     * If you are running a very large number of requests, it's a good
     * idea to call this every few completed requests so you don't run
     * out of memory.
     *
     * @return RollingCurl
     */
    public function clearCompleted()
    {
        $this->completedRequests = array();
        gc_collect_cycles();
        return $this;
    }

}
