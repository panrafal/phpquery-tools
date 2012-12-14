<?php
/*
 * 
 * (c)2012 Rafal Lindemann / Stamina (http://www.stamina.pl/)
 *
 * Check LICENSE file for full license information
 */

namespace Stamina\PhpQuery;

use DOMNode;
use phpQueryObject;

/**
 * Sanitizes HTML documents using phpQueryObject.
 * 
 * @see doc/Sanitizer.md
 * 
 * @author Rafal Lindemann
 */
class Sanitizer {
    
    protected $queue;
    
    /** Sanitizes document using current operations queue 
     * 
     * @param phpQueryObject $pq phpQueryObject with document to sanitize
     * 
     * @return self
     */
    public function sanitize($pq) {
        foreach($this->queue as $item) {
            $this->invokeSelector($pq, $item[0], $item[1]);
        }
        return $this;
    }
    
    /** Returns the queue as [[selector, callback]] */
    public function getQueue() {
        return $this->queue;
    }
    
    public function setQueue($queue) {
        $this->queue = $queue;
    }
    
    /** Resets current queue 
     * @return self
     */
    public function reset() {
        $this->queue = array();
        return $this;
    }
    
    /** Queues callback 
     * @return self
     */
    public function add($selector, callable $callback) {
        $this->queue[] = [$selector, $callback];
        return $this;
    }
    
    /**
     * Calls $callback($queryObject) on selector
     * 
     * @param phpQueryObject $pq phpQuery object
     * @param $selector Selector to use
     * @param $callback
     * @param $opts Additional options to pass to the callback
     */
    public function invokeSelector($pq, $selector, callable $callback, $opts = null) {
        if ($selector === true) $selector = '*';
        
        if (is_string($selector)) {
            $callback($pq->find($selector), $opts);
        } elseif (is_callable($selector)) {
            $callback($selector($pq), $opts);
        } elseif (is_array($selector) && isset($selector[1])) {
            if (is_callable($selector[1])) {
                $callback($pq->find($selector[0])->filterCallback($selector[1]), $opts);
            } else {
                $callback($pq->find($selector[0])->filterCallback(function($i, $node) use ($selector) {
                    return $this->checkFilter($node->tagName, $selector[1]);
                }), $opts);
            }
        }
    }
    
    /** Returns TRUE if value matches the filter 
     * 
     */
    public function checkFilter($value, $filter) {
        if (is_bool($filter)) return $filter;
        if (is_string($filter)) {
            return preg_match($filter, $value);
        } else if (is_array($filter)) {
            if (isset($filter['allow'])) {
                return $this->checkFilter($value, $filter['allow']);
            }
            if (isset($filter['deny'])) {
                return $this->checkFilter($value, $filter['deny']) == false;
            }
            return in_array($value, $filter);
        }
        return true;
    }    
    
    /** Removes tags and their contents 
     * @return self
     */
    public function addRemoveTags($selector) {
        return $this->add($selector, function(phpQueryObject $found) {
            $found->remove();
        });
    }
    
    /** Unwraps tags - remove the tag, move the contents up one level 
     * @return self
     */
    public function addUnwrapTags($selector) {
        return $this->add($selector, function(phpQueryObject $found) {
            $found->contentsUnwrap();
        });
    }

    /**
     * @return self
     */
    public function addFilterAttributes($selector, $attributes, $classes = null, $styles = null, $ids = null) {
        return $this->add($selector, function($found) use ($attributes, $classes, $styles, $ids) {
            $this->filterAttributes($found, $attributes, $classes, $styles, $ids);
        });
    }    
    
    /** 
     * Filters attributes on a list of elements
     * 
     * @param $elements phpQueryObject, array/iterator of DOMElement, or DOMElement
     * @param $attributes Filters attributes
     * @param $classes Filters classes
     * @param $styles Filters styles
     * @param $ids Filters ids
     * 
     * @see checkFilter()
     * 
     * @return self
     *  */
    public function filterAttributes($elements, $attributes, $classes = null, $styles = null, $ids = null) {
        if ($elements instanceof DOMNode) $elements = (array) $elements;
        foreach($elements as $element) {
            /* @var $element DOMElement */

            if ($attributes !== null) {
                foreach(iterator_to_array($element->attributes) as $attr) {
                    /* @var $attr DOMAttr */
                    if ($this->checkFilter($attr->name, $attributes) == false) $element->removeAttributeNode($attr);
                }
            }
            if ($classes !== null && $element->hasAttribute('class')) {
                $newClass = $this->filterClasses($element->getAttribute('class'), $classes);
                if ($newClass) $element->setAttribute('class', $newClass);
                else $element->removeAttribute('class');
            }
            if ($styles !== null && $element->hasAttribute('style')) {
                $newStyle = $this->filterStyles($element->getAttribute('style'), $styles);
                if ($newStyle) $element->setAttribute('style', $newStyle);
                else $element->removeAttribute('style');
            }
            if ($ids !== null && $element->hasAttribute('id')) {
                if ($this->checkFilter($element->getAttribute('id'), $ids) == false) $element->removeAttribute('id');
            }
        }
        return $this;
    }    
    
    /** Filters classes */
    public function filterClasses($classes, $filter) {
        $newClass = '';
        foreach(preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY) as $className) {
            if ($this->checkFilter($className, $filter)) $newClass .= ' '.$className;
        }
        return substr($newClass, 1);
    }
    
    /** Filters styles */
    public function filterStyles($style, $filter) {
        // remove comments if any...
        if (strpos($style, '/*') !== false) $style = preg_replace('/\/\*.+?\*\//s', '', $style);
        if (strpos($style, '{') !== false) {
            // cascading style sheet
            return preg_replace_callback('/\{\s*(.+?)\s*\}/s', function($match) use($filter) {
                return '{'. $this->filterSimpleStyles($match[1], $filter) . '}';
            }, $style);
        } else {
            return $this->filterSimpleStyles($style, $filter);
        }
    }

    public function filterSimpleStyles($style, $filter) {
        $newStyle = '';
        $style = trim($style);
        foreach(preg_split('/\s*;\s*/', $style, -1, PREG_SPLIT_NO_EMPTY) as $styleItem) {
            $styleName = strstr($styleItem, ':', true);
            if ($this->checkFilter($styleName, $filter)) $newStyle .= $styleItem . '; ';
        }
        return substr($newStyle, 0, -2);
    }
    

   
    
}