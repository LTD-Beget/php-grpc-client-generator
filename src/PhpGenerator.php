<?php

namespace LTDBeget\util\PhpGrpcClientGenerator;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;

/**
 * Class PhpGenerator
 *
 * @package LTDBeget\util\PhpGrpcClientGenerator
 */
class PhpGenerator
{
    const STUB_PARENT_CLASS_NS = '\\Grpc\\BaseStub';

    /**
     * @var string
     */
    protected $inputPath;

    /**
     * @var string
     */
    protected $outputPath;

    /**
     * @var int
     */
    protected $versionCompatible = ParserFactory::PREFER_PHP5;

    /**
     * @var array
     */
    protected $storage = [];

    /**
     * @var string
     */
    protected $parentClass = '\\LTDBeget\\util\\PhpGrpcClientGenerator\\simple\\BaseClientSimple';

    /**
     * @throws \Exception
     */
    public function run()
    {
        foreach ($this->getPhpFilesRecursive() as $phpFile) {
            $this->parseAndCreate($phpFile);
        }
    }

    /**
     * @param string $phpFile
     *
     * @throws PhpGeneratorException
     */
    private function parseAndCreate($phpFile)
    {
        if (!is_file($phpFile)) {
            throw new PhpGeneratorException("File {$phpFile} does bot exist");
        }

        $this->clearStorage();
        $this->parse($phpFile);
        $this->create();
    }

    private function clearStorage()
    {
        $this->storage = [];
    }

    /**
     * @return array
     */
    private function getPhpFilesRecursive()
    {
        $result = [];

        $directory = new \RecursiveDirectoryIterator($this->inputPath);
        $iterator = new \RecursiveIteratorIterator($directory);
        $regex = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $file) {
            if (!is_array($file)) {
                continue;
            }

            $result[] = $file[0];
        }

        return $result;
    }

    /**
     * @param string $phpFile
     *
     * @throws PhpGeneratorException
     */
    private function parse($phpFile)
    {
        $code = file_get_contents($phpFile);
        $parser = (new ParserFactory)->create($this->versionCompatible);
        $stmts = $parser->parse($code);

        if ($stmts === NULL) {
            return;
        }

        foreach ($stmts as $namespace) {
            if (!($namespace instanceof Namespace_)) {
                continue;
            }

            if ($namespace->name == NULL) {
                continue;
            }

            /* @var Namespace_ $namespace */

            $namespaceNameParts = $namespace->name->parts;
            $namespaceName = implode('\\', $namespaceNameParts);

            if ($namespace->stmts === NULL) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                if (!($class instanceof Class_)) {
                    continue;
                }

                if ($class->extends === NULL) {
                    continue;
                }

                $parentClassNs = '\\' . implode('\\', $class->extends->parts);

                if ($parentClassNs !== self::STUB_PARENT_CLASS_NS) {
                    continue;
                }

                $className = $class->name;

                foreach ($class->stmts as $method) {
                    if (!($method instanceof ClassMethod)) {
                        continue;
                    }

                    /* @var ClassMethod $method */

                    if ($method->name === "__construct") {
                        continue;
                    }

                    $this->processMethod($namespaceName, $className->name, $method);
                }
            }
        }
    }

    /**
     * Парсим метод. Достаём Reply
     *
     * эта логика может поменяться в следующих версиях protoc-gen-php
     * сейчас return $this->_simpleRequest(...)
     * 3 параметр - это нужный нам reply
     * пример '\hello\HelloReply::deserialize'
     *
     * @param string $namespaceName
     * @param string $className
     * @param ClassMethod $method
     *
     * @throws PhpGeneratorException
     */
    private function processMethod($namespaceName, $className, ClassMethod $method)
    {
        $methodName = $method->name;
        $params = $method->params;

        if (!isset($params[0])) {
            throw new PhpGeneratorException("No params found in {$namespaceName}{$className}::{$methodName}");
        }

        /* @var \PhpParser\Node\Param $firstParam */
        $firstParam = $params[0];

        if ($firstParam->type === NULL) {
            return;
        }

        if (!($firstParam->type instanceof FullyQualified)) {
            throw new PhpGeneratorException("First param type invalid in {$namespaceName}{$className}::{$methodName}");
        }

        $requestNameParts = $firstParam->type->parts;
        $requestName = implode('\\', $requestNameParts);

        if ($method->stmts === NULL) {
            throw new PhpGeneratorException("Method body invalid in {$namespaceName}{$className}::{$methodName}");
        }

        $returnEntry = NULL;

        foreach ($method->stmts as $codeEntry) {
            if (!($codeEntry instanceof Return_)) {
                continue;
            }

            $returnEntry = $codeEntry;

            break;
        }

        if ($returnEntry === NULL) {
            throw new PhpGeneratorException("Return statement not found in {$namespaceName}{$className}::{$methodName}");
        }

        if (!($returnEntry->expr instanceof MethodCall)) {
            throw new PhpGeneratorException("{$returnEntry->expr->name} // Return statement not calling _simpleRequest in {$namespaceName}{$className}::{$methodName}");
        }

        if ($returnEntry->expr->name->name !== '_simpleRequest') {
            throw new PhpGeneratorException("{$returnEntry->expr->name} // Return statement not calling _simpleRequest in {$namespaceName}{$className}::{$methodName}");
        }

        if (!isset($returnEntry->expr->args[2])) {
            throw new PhpGeneratorException("Third argument for _simpleRequest not found in {$namespaceName}{$className}::{$methodName}");
        }

        $thirdArg = $returnEntry->expr->args[2];

        if (!($thirdArg->value instanceof String_)) {
            throw new PhpGeneratorException("Third argument for _simpleRequest is not string in {$namespaceName}{$className}::{$methodName}");
        }

        $replyNameParts = explode('\\', ltrim(str_replace('::deserialize', '', $thirdArg->value->value), '\\'));
        $replyName = implode('\\', $replyNameParts);

        $this->storage[$namespaceName][$className][$methodName->name]['request'] = $requestName;
        $this->storage[$namespaceName][$className][$methodName->name]['reply'] = $replyName;
    }

    private function create()
    {
        $generatedTime = date('Y-m-d H:i:s');

        foreach ($this->storage as $namespaceName => $_item) {
            $namespaceNameParts = explode('\\', $namespaceName);
            $parentPath = $this->outputPath;

            foreach ($namespaceNameParts as $nameSpacePart) {
                $childPath = $parentPath . '/' . $nameSpacePart;

                if (!is_dir($childPath)) {
                    mkdir($childPath);
                }

                $parentPath = $childPath;
            }

            $className = array_keys($_item)[0];
            $newClassName = "{$className}Simple";
            $fileName = $parentPath . '/' . $newClassName . '.php';
            $methodsContents = [];

            foreach ($_item[$className] as $methodName => $__item) {
                $request = $__item['request'];
                $reply = $__item['reply'];

                $requestNsParts = explode('\\', $request);
                $requestClass = array_pop($requestNsParts);
                $replyNsParts = explode('\\', $reply);
                $replyClass = array_pop($replyNsParts);

                $methodsContents[] = <<<STR
    /**
     * @param {$requestClass} \$request
     * @param array \$metadata
     * @param array \$options
     *
     * @return {$replyClass}
     * @throws GrpcClientException
     */
    public function {$methodName}({$requestClass} \$request, array \$metadata = [], array \$options = [])
    {
        try {
            \$i = 0;
            grpc_call:
            
            /* @var UnaryCall \$call */
            \$call = \$this->client->{$methodName}(
                \$request,
                array_merge_recursive(\$this->metadata, \$metadata),
                array_merge_recursive(\$this->options, \$options)
            );

            list(\$reply, \$status) = \$call->wait();
            
            if(\$status->code == 14 && \$status->details == "OS Error" && ++\$i < 5) {
                goto grpc_call;            
            }

            \$this->checkStatus(\$status);

        } catch (GrpcClientException \$e) {
            throw \$e;
        } catch (\\Exception \$e) {
            throw new GrpcClientException("Unexpected exception: {\$e->getMessage()}", \$e->getCode(), \$e);
        }

        return \$reply;
    }
STR;
            }

            $baseClassNs = ltrim($this->parentClass, '\\');
            $baseClassParts = explode('\\', $baseClassNs);
            $baseClassName = end($baseClassParts);

            $classStart = <<<STR
<?php
/**
 * DO NOT EDIT! Generated automatically
 * Date: {$generatedTime}
 */

namespace {$namespaceName};

use {$baseClassNs};
use LTDBeget\\util\\PhpGrpcClientGenerator\\simple\\exceptions\\GrpcClientException;
use Grpc\\UnaryCall;

/**
 * Class {$newClassName}
 *
 * @package {$namespaceName}
 */
class {$newClassName} extends {$baseClassName}
{
    /**
     * @var {$className}
     */
    protected \$client;

    /**
     * @var array
     */
    protected \$metadata;

    /**
     * @var array
     */
    protected \$options;

    /**
     * @param {$className} \$client
     * @param array \$metadata
     * @param array \$options
     */
    public function __construct({$className} \$client, array \$metadata = [], array \$options = [])
    {
        \$this->client   = \$client;
        \$this->metadata = \$metadata;
        \$this->options  = \$options;
    }


STR;

            $classEnd = <<<STR

}

STR;

            $content = $classStart . implode("\n\n", $methodsContents) . $classEnd;

            file_put_contents($fileName, $content);
        }
    }

    /**
     * @param string $inputPath
     *
     * @return PhpGenerator
     */
    public function setInputPath($inputPath)
    {
        $this->inputPath = $inputPath;

        return $this;
    }

    /**
     * @param string $outputPath
     *
     * @return PhpGenerator
     */
    public function setOutputPath($outputPath)
    {
        $this->outputPath = $outputPath;

        return $this;
    }

    /**
     * @param int $versionCompatible
     *
     * @return PhpGenerator
     */
    public function setVersionCompatible($versionCompatible)
    {
        $this->versionCompatible = $versionCompatible;

        return $this;
    }

    /**
     * @param string $parentClass
     *
     * @return PhpGenerator
     */
    public function setParentClass($parentClass)
    {
        $this->parentClass = $parentClass;

        return $this;
    }
}
