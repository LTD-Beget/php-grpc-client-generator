# Php proto generator

Usage
-----

```
protoc-gen-php -Dmultifile -i ../protos/ -o . ../protos/hello.proto
```

```
ini_set('xdebug.max_nesting_level', 3000);

use LTDBeget\PhpProtoGenerator\PhpGenerator;

(new PhpGenerator())
    ->setInputPath(__DIR__ . '/proto')
    ->setOutputPath(__DIR__)
    ->setParentClass('\\LTDBeget\\PhpProtoGenerator\\simple\BaseClientSimple')
    ->run();
```
