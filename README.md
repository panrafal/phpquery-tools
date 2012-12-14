PhpQuery Tools
==============

Is a small set of helper tools to manipulate HTML documents.

It is using [phpQuery][1] library, which in turn is a PHP port of [jQuery][2].

Currently there are two of them:

-  [LinksExtractor](doc/LinksExtractor.md) - for extracting links
-  [Sanitizer](doc/Sanitizer.md) - for sanitizing/linting HTML

## Usage

Check the tool's docs for general info, and the source code annotations for the API.

## Installing

The best way is to use composer. Add the ```stamina/phpquery-tools``` package to your composer.json:

You will also need a phpQuery library. You can use mine, but it's not hosted on packagist:

```json
{
    "require": {
        "stamina/phpquery": "1.*",
        "stamina/phpquery-tools": "1.*"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/panrafal/phpquery.git"
        }
    ]
}
```

## Testing

```phpunit --bootstrap Tests/bootstrap.php Tests```


[1]: http://code.google.com/p/phpquery/
[2]: http://jquery.com/


