<?php
/*
 * 
 * (c)2012 Rafal Lindemann / Stamina (http://www.stamina.pl/)
 *
 * Check LICENSE file for full license information
 */

namespace Stamina\PhpQuery;

/**
 * Extracts links from html using phpQuery
 *
 * @author Rafal Lindemann
 */
class LinksExtractor {

    /** phpQuery selectors to extract the links. Check phpQuery or jQuery documentation */
    public $selectors;

    /** regexp to match the url against (url is as is, not absolute) */
    public $regexp;

    /** Every result will be a copy of this array */
    public $resultData;

    /** callback to call on every link
     * $data = callback(LinksExtractor $le, \phpQueryObject $a, $url, $data)
     *  */
    public $callback;
    
    /** Allow duplicate URLs */
    public $allowDuplicates = false;
    
    /** Attribute holding an URL */
    public $urlAttribute = 'href';

    /** Subkey in result to put phpQueryObject with found element */
    public $elementKey = false;
    
    public $baseUrl;
    public $basePath;
    public $baseProtocol;
    public $baseHost;

    public function __construct( $selectors, $regexp = '/^(?!javascript|#).+/', $resultData = array(),
            $baseUrl = false, $callback = null, $allowDuplicates = false ) {
        $this->selectors = $selectors;
        $this->regexp = $regexp;
        $this->resultData = $resultData;
        $this->callback = $callback;
        $this->allowDuplicates = $allowDuplicates;
        if ($baseUrl) $this->setBaseUrl($baseUrl);
    }

    public function setBaseUrl( $baseUrl ) {
        $this->baseUrl = $baseUrl;
        if (!$baseUrl) {
            $this->baseProtocol = $this->baseHost = $this->basePath = false;
            return;
        }
        $urlParts = [];
        if ($baseUrl && preg_match('#^(?:(https?:)?//([^/]+))?(/(?:[^?\#]+/)?)?#', $baseUrl, $urlParts)) {
            $this->baseProtocol = empty($urlParts[1]) ? '' : $urlParts[1];
            $this->baseHost = empty($urlParts[2]) ? '' : '//' . $urlParts[2];
            $this->basePath = empty($urlParts[3]) ? '/' : $urlParts[3];
        }
    }

    public function getAbsoluteUrl( $url ) {
        if (!$url) return $this->baseUrl;
        if (!preg_match('#^[-a-z0-9]+:#i', $url)) {
            if ($url{0} == '/') {
                if (strlen($url) > 1 && $url{1} == '/') return $this->baseProtocol . $url;
                else return $this->baseProtocol . $this->baseHost . $url;
            }
            return $this->baseProtocol . $this->baseHost . $this->basePath . $url;
        }
        return $url;
    }

    /**
     * Returns a list of links as an array of $resultData arrays with 'url' set to the url
     *  
     *  */
    public function findUrls( \phpQueryObject $doc ) {
        $list = $doc->find($this->selectors);
        $result = array();
        foreach ($list as $a) {
            $a = \phpQuery::pq($a);
            $url = $a->attr($this->urlAttribute);
            if ($this->regexp && !preg_match($this->regexp, $url)) continue;
            $data = $this->resultData;
            $data['url'] = $this->getAbsoluteUrl($url);
            if ($this->elementKey) $data[$this->elementKey] = $a;
            if ($this->callback) $data = call_user_func($this->callback, $this, $a, $url, $data);
            if ($data && !empty($data['url'])) {
                if ($this->allowDuplicates) {
                    $result[] = $data;
                } else {
                    $result[$data['url']] = $data;
                }
            }
        }
        return $result;
    }

}

