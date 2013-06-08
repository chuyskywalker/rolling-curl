<?php

/**
 * A cURL library to fetch a large number of resources while only using
 * a limited number of simultaneous connections
 *
 * @package RollingCurl
 * @version 1.0
 * @author Jeff Minard (http://jrm.cc/)
 * @author Josh Fraser (www.joshfraser.com)
 * @author Alexander Makarov (http://rmcreative.ru/)
 * @license Apache License 2.0
 * @link https://github.com/chuyskywalker/rolling-curl
 */

namespace RollingCurl;

/**
 * Class that represent a single curl request
 */
class Request
{
    /**
     * @var string
     */
    private $url;
    /**
     * @var string
     */
    private $method;
    /**
     * @var string
     */
    private $postData;
    /**
     * @var array
     */
    private $headers;
    /**
     * @var array
     */
    private $options;
    /**
     * @var mixed
     */
    private $extraInfo;
    /**
     * @var string
     */
    private $responseText;
    /**
     * @var object
     */
    private $responseInfo;
    /**
     * @var string
     */
    private $responseError;
    /**
     * @var int
     */
    private $responseErrno;

    /**
     * @param string $url
     * @param string $method
     * @return \RollingCurl\Request
     */
    function __construct($url, $method="GET")
    {
        $this->setUrl($url);
        $this->setMethod($method);
    }

    /**
     * You may wish to store some "extra" info with this request, you can put any of that here.
     *
     * @param mixed $extraInfo
     * @return \RollingCurl\Request
     */
    public function setExtraInfo($extraInfo)
    {
        $this->extraInfo = $extraInfo;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExtraInfo()
    {
        return $this->extraInfo;
    }

    /**
     * @param array $headers
     * @return \RollingCurl\Request
     */
    public function setHeaders($headers)
    {
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
     * @param string $method
     * @return \RollingCurl\Request
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param array $options
     * @throws \InvalidArgumentException
     * @return \RollingCurl\Request
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
     * @param array $options
     * @throws \InvalidArgumentException
     * @return \RollingCurl\Request
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
     * @param string $postData
     * @return \RollingCurl\Request
     */
    public function setPostData($postData)
    {
        $this->postData = $postData;
        return $this;
    }

    /**
     * @return string
     */
    public function getPostData()
    {
        return $this->postData;
    }

    /**
     * @param int $responseErrno
     * @return \RollingCurl\Request
     */
    public function setResponseErrno($responseErrno)
    {
        $this->responseErrno = $responseErrno;
        return $this;
    }

    /**
     * @return int
     */
    public function getResponseErrno()
    {
        return $this->responseErrno;
    }

    /**
     * @param string $responseError
     * @return \RollingCurl\Request
     */
    public function setResponseError($responseError)
    {
        $this->responseError = $responseError;
        return $this;
    }

    /**
     * @return string
     */
    public function getResponseError()
    {
        return $this->responseError;
    }

    /**
     * @param object $responseInfo
     * @return \RollingCurl\Request
     */
    public function setResponseInfo($responseInfo)
    {
        $this->responseInfo = (object)$responseInfo;
        return $this;
    }

    /**
     * @return object
     */
    public function getResponseInfo()
    {
        return $this->responseInfo;
    }

    /**
     * @param string $responseText
     * @return \RollingCurl\Request
     */
    public function setResponseText($responseText)
    {
        $this->responseText = $responseText;
        return $this;
    }

    /**
     * @return string
     */
    public function getResponseText()
    {
        return $this->responseText;
    }

    /**
     * @param string $url
     * @return \RollingCurl\Request
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }



}
