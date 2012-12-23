Sanitizer
==============

Sanitizes HTML documents using phpQueryObject.

It works by queueing operations and invoking the queue on the phpQueryObject document. It is also
possible to call some of the functions directly on phpQuery, or DOMElement objects.

It's main function is not to remove all HTML tags, but rather to normalize the document using 
a defined preset. 

### Sample

This will essentialy convert the HTML into an article (no HTML, or BODY tags) and strip most of the tags and styling.

In order:

- strip out script, style, object, named anchors, tags with `hidden` class
- unwrap html and body
- unwrap contents of any tags other than `/^(a|b|i|u|p|h\d|ul|ol|li|br)$/i`
- remove all attributes except 'href', 'class', 'id', 'style', 'rel'
- remove all classes except 'important', 'link'
- remove all styles except 'list-style'

```php
$doc = phpQuery::newDocumentFileXHTML($html);
$s = new Sanitizer();
$s->addRemoveTags('head')
  ->addRemoveTags('a[name]:not([href]),.hidden,script,style,object')
  ->addUnwrapTags('html,body')
  ->addUnwrapTags([true, ['deny' => '/^(a|b|i|u|p|h\d|ul|ol|li|br)$/i']])
  ->addFilterAttributes(true, ['href', 'class', 'id', 'style', 'rel'], ['important', 'link'], ['list-style'])
  ->sanitize($doc)
  ;
```


## Selectors

Selector can be:

-  **TRUE** to select every element
-  **jQuery selector** string with jQuery-like selector (```'a,div.some-class'```)
-  **callable($queryObject)** returning phpQueryObject with elements
-  **array(SELECTOR, FILTER)** to first find, and then filter by tagname the nodes (eg. array('*', ['script','meta']))
-  **array(SELECTOR, callback($index, $node))** to first find, and then filter the nodes. Callback should return TRUE to include the node.


## Filters

Filters can be:

-  String with allowed value (case insensitive)
-  Regular expression matching allowed values (using '/' delimiter) (```'/^(href|rel)$/i'```)
-  Array with allowed values (```['href', 'rel']```)
-  Hashmap with "allow" or "deny" filters (```['deny' => '/^font-/i']```)
-  Hashmap with "replace" array specifying [FILTER, replacement], or FILTER => replacement pairs 
   (``` ['replace' => [['/^old$/', 'new'], ['/^old2$/', function() {return $new;}], 'old' => 'new']] ```)
