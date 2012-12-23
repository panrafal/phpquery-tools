<?php
/*
 * 
 * (c)2012 Rafal Lindemann / Stamina (http://www.stamina.pl/)
 *
 * Check LICENSE file for full license information
 */

namespace Stamina\PhpQuery;

use DOMAttr;
use DOMElement;
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
    
    protected $queue = [];
    
    /** Sanitizes document using current operations queue 
     * 
     * @param phpQueryObject $pq phpQueryObject with document to sanitize
     * 
     * @return phpQueryObject
     */
    public function sanitize($pq) {
        foreach($this->queue as $item) {
            $this->invokeSelector($pq, $item[0], $item[1]);
        }
        return $pq;
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
     * @param $callback Callback to use or NULL
     * @param $opts Additional options to pass to the callback
     * 
     * @return phpQueryObject
     */
    public function invokeSelector($pq, $selector, callable $callback = null, $opts = null) {
        $found = null;
        if ($selector === true) {
            $found = $pq->find('*');
        } elseif (is_string($selector)) {
            $found = $pq->find($selector);
        } elseif (is_callable($selector)) {
            $found = $selector($pq);
        } elseif (is_array($selector) && isset($selector[1])) {
            $found = $this->invokeSelector($pq, $selector[0]);
            if (is_callable($selector[1])) {
                $found = $found->filterCallback($selector[1]);
            } else {
                $found = $found->filterCallback(function($i, $node) use ($selector) {
                    return $this->applyFilter($node->tagName, $selector[1]);
                });
            }
        }
        if ($found && $callback) $callback($found, $opts);
        return $found;
    }
    
    /** Returns TRUE if value matches the filter 
     * 
     */
    public function applyFilter($value, $filter) {
        if (is_bool($filter)) return $filter ? $value : false;
        if (is_string($filter)) {
            return ($filter{0} === '/' ? preg_match($filter, $value) : strcasecmp($filter, $value) === 0) ? $value : false;
        } else if (is_array($filter)) {
            if (isset($filter['replace'])) {
                if (!is_array($filter['replace'])) $filter['replace'] = array(array(true, $filter['replace']));
                foreach($filter['replace'] as $k => $v) {
                    if (is_int($k)) {
                        $k = $v[0];
                        $v = $v[1];
                    }
                    if ($k === true || $this->applyFilter($value, $k)) {
                        return is_callable($v) && !is_string($v) ? call_user_func($v, $value) : $v;
                    }
                }
            }
            if (isset($filter['allow'])) {
                return $this->applyFilter($value, $filter['allow']);
            }
            if (isset($filter['deny'])) {
                return $this->applyFilter($value, $filter['deny']) == false ? $value : false;
            }
            return in_array($value, $filter) ? $value : false;
        }
        return $value;
    }    
    
    /**
     * Queues remove tags and their contents 
     * @return self
     */
    public function addRemoveTags($selector) {
        return $this->add($selector, function(phpQueryObject $found) {
            $found->remove();
        });
    }
    
    /** 
     * Queues unwrap tags - remove the tag, move the contents up one level 
     * 
     * @return self
     */
    public function addUnwrapTags($selector) {
        return $this->add($selector, function(phpQueryObject $found) {
            $found->contentsUnwrap();
        });
    }

    /**
     * Queues self::filterAttributes()
     * 
     * @return self
     */
    public function addFilterAttributes($selector, $attributes, $classes = null, $styles = null, $ids = null) {
        return $this->add($selector, function($found) use ($attributes, $classes, $styles, $ids) {
            $this->filterAttributes($found, $attributes, $classes, $styles, $ids);
        });
    }    
    
    /**
     * Queues renameTags
     * 
     */
    public function addRenameTags($selector, $filters) {
        return $this->add($selector, function($found) use ($filters) {
            $this->renameTags($found, $filters);
        });
    }    
    
    /**
     * Renames tags according to filters
     * 
     * /---code php
     * $s->replaceTags($element, [['/^old$/', 'new'], ['/^old2$/', function() {return $new;}], 'old' => 'new']);
     * \---
     * 
     * @param $filters Filter replacement. @see doc/Sanitizer.md - replace syntax
     * @return array Renamed elements
     */
    public function renameTags($elements, $filters) {
        if ($elements instanceof DOMNode) $elements = (array) $elements;
        $renamed = [];
        foreach($elements as $element) {
            /* @var $element DOMElement */
            $tagName = trim($this->applyFilter($element->tagName, array('replace' => $filters)));
            if (!$tagName || strcasecmp($tagName, $element->tagName) == 0) continue;
            $renamed[] = $this->renameTag($element, $tagName);
        }
        return $renamed;
    }
    
    public function renameTag( DOMElement $oldTag, $newTagName ) {
        $document = $oldTag->ownerDocument;

        $newTag = $document->createElement($newTagName);
        $oldTag->parentNode->replaceChild($newTag, $oldTag);
        foreach ($oldTag->attributes as $attribute) {
            $newTag->setAttribute($attribute->name, $attribute->value);
        }
        foreach (iterator_to_array($oldTag->childNodes) as $child) {
            $newTag->appendChild($oldTag->removeChild($child));
        }
        return $newTag;
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
                    $filtered = $this->applyFilter($attr->name, $attributes);
                    if ($filtered == false) $element->removeAttributeNode($attr);
                    elseif ($filtered != $attr->name) {
                        $element->setAttribute($filtered, $attr->value);
                        $element->removeAttributeNode($attr);
                    }
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
                $filtered = $this->applyFilter($element->getAttribute('id'), $ids);
                if ($filtered == false) $element->removeAttribute('id');
                elseif ($filtered != $element->getAttribute('id')) $element->setAttribute('id', $filtered);
            }
        }
        return $this;
    }    
    
    /** Filters classes */
    public function filterClasses($classes, $filter) {
        $newClass = '';
        foreach(preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY) as $className) {
            $className = $this->applyFilter($className, $filter);
            if ($className) $newClass .= ' '.$className;
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
            $styleParts = explode(':', $styleItem, 2);
            $styleParts[0] = $this->applyFilter($styleParts[0], $filter);
            if ($styleParts[0]) $newStyle .= $styleParts[0] . (empty($styleParts[1]) ? '' : ':' . $styleParts[1]) . '; ';
        }
        return substr($newStyle, 0, -2);
    }
    

   
    
}