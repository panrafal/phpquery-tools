Sanitizer
==============

Sanitizes HTML documents using phpQueryObject.

It works by queueing operations and invoking the queue on the phpQueryObject document. It is also
possible to call some of the functions directly on phpQuery, or DOMElement objects.

It's main function is not to remove all HTML tags, but rather to normalize the document using 
a defined preset. 

### Sample

```code php

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

-  Regular expression matching allowed values (```'/^(href|rel)$/i'```)
-  Array with allowed values (```['href', 'rel']```)
-  Hashmap with "allow" or "deny" filters (```['deny' => '/^font-/i']```)
