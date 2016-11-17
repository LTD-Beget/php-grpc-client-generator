<?php

namespace LTDBeget\PhpProtoGenerator;

/**
 * Class PhpGenerator
 *
 * @package LTDBeget\PhpProtoGenerator
 */
class PhpGenerator
{
    const STUB_PARENT_CLASS_NS = '\Grpc\BaseStub';

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
    protected $versionCompatible = \PhpParser\ParserFactory::PREFER_PHP5;

    /**
     * @var array
     */
    protected $storage = [];

    /**
     * @var string
     */
    protected $parentClass;

    /**
     * @throws \Exception
     */
    public function run()
    {
        foreach ($this->getPhpFilesRecursive() as $phpFile) {
            try {
                $this->parseAndCreate($phpFile);
            } catch (\Exception $e) {
                throw $e;
            }
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
        $iterator  = new \RecursiveIteratorIterator($directory);
        $regex     = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

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
     */
    private function parse($phpFile)
    {
        $code   = file_get_contents($phpFile);
        $parser = (new \PhpParser\ParserFactory)->create($this->versionCompatible);
        $stmts  = $parser->parse($code);

        if ($stmts === NULL) {
            return;
        }

        foreach ($stmts as $namespace) {
            if (!($namespace instanceof \PhpParser\Node\Stmt\Namespace_)) {
                continue;
            }

            /* @var \PhpParser\Node\Stmt\Namespace_ $namespace */

            $namespaceNameParts = $namespace->name->parts;
            $namespaceName      = implode('\\', $namespaceNameParts);

            if ($namespace->stmts === NULL) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                if (!($class instanceof \PhpParser\Node\Stmt\Class_)) {
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
                    if (!($method instanceof \PhpParser\Node\Stmt\ClassMethod)) {
                        continue;
                    }

                    /* @var \PhpParser\Node\Stmt\ClassMethod $method */

                    if ($method->name === "__construct") {
                        continue;
                    }

                    $this->processMethod($namespaceName, $className, $method);
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
     * @param string                           $namespaceName
     * @param string                           $className
     * @param \PhpParser\Node\Stmt\ClassMethod $method
     *
     * @throws PhpGeneratorException
     */
    private function processMethod($namespaceName, $className, \PhpParser\Node\Stmt\ClassMethod $method)
    {
        $methodName = $method->name;
        $params     = $method->params;

        if (!isset($params[0])) {
            throw new PhpGeneratorException("No params found in {$namespaceName}{$className}::{$methodName}");
        }

        /* @var \PhpParser\Node\Param $firstParam */
        $firstParam = $params[0];

        if ($firstParam->type === NULL) {
            throw new PhpGeneratorException("First param type invalid in {$namespaceName}{$className}::{$methodName}");
        }

        if (!($firstParam->type instanceof \PhpParser\Node\Name\FullyQualified)) {
            throw new PhpGeneratorException("First param type invalid in {$namespaceName}{$className}::{$methodName}");
        }

        $requestNameParts = $firstParam->type->parts;
        $requestName      = implode('\\', $requestNameParts);

        if ($method->stmts === NULL) {
            throw new PhpGeneratorException("Method body invalid in {$namespaceName}{$className}::{$methodName}");
        }

        $returnEntry = NULL;

        foreach ($method->stmts as $codeEntry) {
            if (!($codeEntry instanceof \PhpParser\Node\Stmt\Return_)) {
                continue;
            }

            $returnEntry = $codeEntry;

            break;
        }

        if ($returnEntry === NULL) {
            throw new PhpGeneratorException("Return statement not found in {$namespaceName}{$className}::{$methodName}");
        }

        if (!($returnEntry->expr instanceof \PhpParser\Node\Expr\MethodCall)) {
            throw new PhpGeneratorException("Return statement not calling _simpleRequest in {$namespaceName}{$className}::{$methodName}");
        }

        if ($returnEntry->expr->name !== '_simpleRequest') {
            throw new PhpGeneratorException("Return statement not calling _simpleRequest in {$namespaceName}{$className}::{$methodName}");
        }

        if (!isset($returnEntry->expr->args[2])) {
            throw new PhpGeneratorException("Third argument for _simpleRequest not found in {$namespaceName}{$className}::{$methodName}");
        }

        $thirdArg = $returnEntry->expr->args[2];

        if (!($thirdArg->value instanceof \PhpParser\Node\Scalar\String_)) {
            throw new PhpGeneratorException("Third argument for _simpleRequest is not string in {$namespaceName}{$className}::{$methodName}");
        }

        $replyNameParts = explode('\\', ltrim(str_replace('::deserialize', '', $thirdArg->value->value), '\\'));
        $replyName      = implode('\\', $replyNameParts);

        $this->storage[$namespaceName][$className][$methodName]['request'] = $requestName;
        $this->storage[$namespaceName][$className][$methodName]['reply']   = $replyName;
    }

    private function create()
    {
        $generatedTime = date('Y-m-d H:i:s');

        foreach ($this->storage as $namespaceName => $_item) {
            $namespaceNameParts = explode('\\', $namespaceName);
            $parentPath         = $this->outputPath;

            foreach ($namespaceNameParts as $nameSpacePart) {
                $childPath = $parentPath . '/' . $nameSpacePart;

                if (!is_dir($childPath)) {
                    mkdir($childPath);
                }

                $parentPath = $childPath;
            }

            $className       = array_keys($_item)[0];
            $newClassName    = "{$className}Simple";
            $fileName        = $parentPath . '/' . $newClassName . '.php';
            $methodsContents = [];

            foreach ($_item[$className] as $methodName => $__item) {
                $request = $__item['request'];
                $reply   = $__item['reply'];

                $methodsContents[] = <<<STR
    /**
     * @param \\$request \$request
     * @param array \$metadata
     * @param array \$options
     *
     * @return \\$reply
     * @throws \Exception
     */
    public function $methodName(\\$request \$request, \$metadata = [], \$options = [])
    {
        try {
            /* @var \\Grpc\\UnaryCall \$call */
            \$call = \$this->client->$methodName(\$request, \$metadata, \$options);
            list(\$reply, \$status) = \$call->wait();
            \$this->checkStatus(\$status);
        } catch (\\Exception \$e) {
            throw \$e;
        }

        return \$reply;
    }
STR;
            }

            $classStart = <<<STR
<?php
/**
 * DO NOT EDIT! Generated automatically
 * Date: {$generatedTime}
 */

namespace {$namespaceName};

/**
 * Class {$newClassName}
 *
 * @package {$namespaceName}
 */
class {$newClassName} extends {$this->parentClass}
{
    /**
     * @var \\{$namespaceName}\\{$className}
     */
    protected \$client;

    /**
     * @param \\{$namespaceName}\\{$className} \$client
     */
    public function __construct(\\{$namespaceName}\\{$className} \$client)
    {
        \$this->client = \$client;
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
