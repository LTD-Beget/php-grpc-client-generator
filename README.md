# Php proto generator

Usage
-----

```
ini_set('xdebug.max_nesting_level', 3000);

(new Generator())
    ->setInputPath(__DIR__ . '/proto')
    ->setOutputPath(__DIR__)
    ->setParentClass('\\some\\parent\\Klass')
    ->run();
```
