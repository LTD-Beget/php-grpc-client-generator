# Php proto generator

Usage
-----

```
protoc-gen-php -Dmultifile -i ../protos/ -o . ../protos/hello.proto
```

```
ini_set('xdebug.max_nesting_level', 3000);

use LTDBeget\util\PhpProtoGenerator\PhpGenerator;

(new PhpGenerator())
    ->setInputPath(__DIR__ . '/proto')
    ->setOutputPath(__DIR__)
    ->run();
```
