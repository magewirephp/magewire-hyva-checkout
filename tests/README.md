# Compatibility regression tests

PHP tests run against an installed Magewire v3 and Magento framework. They generate real Magento interceptors for the fixture components and exercise the plugin chain without the proposed core changes in magewirephp/magewire#337 and #341.

```sh
MAGEWIRE_AUTOLOAD=/path/to/magento/vendor/autoload.php phpunit --bootstrap tests/bootstrap.php --do-not-cache-result tests/Unit
node --test tests/JavaScript/*.test.js
```

When dependencies are installed in this repository, `MAGEWIRE_AUTOLOAD` can be omitted. The tests use PHPUnit 11.5 and Node's built-in test runner.
