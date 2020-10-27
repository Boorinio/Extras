<span style="float:right;"><a href="https://github.com/RubixML/Extras/blob/master/src/Persisters/Serializers/Gzip.php">[source]</a></span>

# Gzip
A compression format based on the DEFLATE algorithm with a header and CRC32 checksum.

## Parameters
| # | Param | Default | Type | Description |
|---|---|---|---|---|
| 1 | level | 1 | int | The compression level between 0 and 9, 0 meaning no compression. |
| 2 | serializer | Native | Serializer | The base serializer |

## Example
```php
use Rubix\ML\Persisters\Serializers\Gzip;
use Rubix\ML\Persisters\Serializers\Native;

$serializer = new Gzip(1, new Native());
```

### References
>- P. Deutsch. (1996). RFC 1951 - DEFLATE Compressed Data Format Specification version.