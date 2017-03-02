# Php proto generator

Proto file example
------------------

```
syntax = "proto3";

package beget.hello;

service Greeter {
  rpc SayHello (HelloRequest) returns (HelloReply) {}
}

message HelloRequest {
  string name = 1;
}

message HelloReply {
  string message = 1;
}

```

Usage
-----

```
protoc-gen-php -Dmultifile -i ../protos/ -o . ../protos/hello.proto
```

```
ini_set('xdebug.max_nesting_level', 3000);

use LTDBeget\util\PhpGrpcClientGenerator\PhpGenerator;

(new PhpGenerator())
    ->setInputPath(__DIR__ . '/proto')
    ->setOutputPath(__DIR__)
    ->run();

```

```
require __DIR__ . '/vendor/autoload.php';

$client = new \beget\hello\GreeterClientSimple(
    new \beget\hello\GreeterClient(
        'localhost:50051',
        [
            'credentials' => Grpc\ChannelCredentials::createInsecure(),
        ]
    )
);

$request = new \beget\hello\HelloRequest();
$request->setName(time());

$reply = $client->SayHello($request);

echo $reply->getMessage(), PHP_EOL;
```
