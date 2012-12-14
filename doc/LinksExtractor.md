LinksExtractor
==============

This tool allows to extract URLs from HTML documents.

Check the [source](../LinksExtractor.php) for API annotations.

### Example

This will extract all internal wiki links from http://en.wikipedia.org/wiki/Poland

For every link found we will get:

- url => absolute url (because we've set baseUrl)
- element => DOMElement object (because we've set elementKey)
- internal => true (because we've used it as a result object)

```php

$le = new LinksExtractor('a', '/\/wiki\//', array('internal' => true), 'http://en.wikipedia.org/wiki/');
$le->elementKey = 'element';
$doc = phpQuery::newDocumentFileXHTML('http://en.wikipedia.org/wiki/Poland');
$links = $le->findUrls($doc);

```
