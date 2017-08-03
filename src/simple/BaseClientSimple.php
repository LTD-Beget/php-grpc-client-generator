<?php

namespace LTDBeget\util\PhpGrpcClientGenerator\simple;

use LTDBeget\util\PhpGrpcClientGenerator\RpcCallHook;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\AbortedException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\AlreadyExistsException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\CanceledException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\DataLossException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\DeadlineExceededException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\FailedPreconditionException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\GrpcClientException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\GrpcClientHookException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\UnavailableException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\InternalException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\InvalidArgumentException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\NotFoundException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\OutOfRangeException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\PermissionDeniedException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\ResourceExhaustedException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\UnauthenticatedException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\UnimplementedException;
use LTDBeget\util\PhpGrpcClientGenerator\simple\exceptions\UnknownException;

/**
 * Class BaseClientSimple
 *
 * @package LTDBeget\util\PhpGrpcClientGenerator\simple
 */
class BaseClientSimple
{
    /**
     * @var RpcCallHook
     */
    protected $hook;

    /**
     * @param RpcCallHook $hook
     *
     * @return $this
     */
    public function setHook(RpcCallHook $hook)
    {
        $this->hook = $hook;

        return $this;
    }

    /**
     * https://github.com/grpc/grpc-go/blob/master/codes/codes.go
     *
     * @param \StdClass $status
     *
     * @throws AbortedException
     * @throws AlreadyExistsException
     * @throws CanceledException
     * @throws DataLossException
     * @throws DeadlineExceededException
     * @throws FailedPreconditionException
     * @throws GrpcClientException
     * @throws InternalException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws OutOfRangeException
     * @throws PermissionDeniedException
     * @throws ResourceExhaustedException
     * @throws UnauthenticatedException
     * @throws UnavailableException
     * @throws UnimplementedException
     * @throws UnknownException
     */
    protected function checkStatus($status)
    {
        switch ($status->code) {
            case 0:
                // success
                return;
            case 1:
                throw new CanceledException("Canceled: {$status->details}", $status->code);
            case 2:
                throw new UnknownException("Unknown: {$status->details}", $status->code);
            case 3:
                throw new InvalidArgumentException("Invalid argument: {$status->details}", $status->code);
            case 4:
                throw new DeadlineExceededException("Deadline exceeded: {$status->details}", $status->code);
            case 5:
                throw new NotFoundException("Not found: {$status->details}", $status->code);
            case 6:
                throw new AlreadyExistsException("Already exists: {$status->details}", $status->code);
            case 7:
                throw new PermissionDeniedException("Permission denied: {$status->details}", $status->code);
            case 8:
                throw new ResourceExhaustedException("Resource exhausted: {$status->details}", $status->code);
            case 9:
                throw new FailedPreconditionException("Failed precondition: {$status->details}", $status->code);
            case 10:
                throw new AbortedException("Aborted: {$status->details}", $status->code);
            case 11:
                throw new OutOfRangeException("Out of range: {$status->details}", $status->code);
            case 12:
                throw new UnimplementedException("Unimplemented: {$status->details}", $status->code);
            case 13:
                throw new InternalException("Internal: {$status->details}", $status->code);
            case 14:
                throw new UnavailableException("Unavailable: {$status->details}", $status->code);
            case 15:
                throw new DataLossException("Data loss: {$status->details}", $status->code);
            case 16:
                throw new UnauthenticatedException("Unauthenticated: {$status->details}", $status->code);
            default:
                throw new GrpcClientException("Unknown status {$status->code}: {$status->details}", $status->code);
        }
    }

    /**
     * @param string  $method
     * @param mixed   $request
     * @param array   $metadata
     * @param array   $options
     *
     * @throws GrpcClientHookException
     */
    protected function execHook($method, $request, array $metadata = [], array $options = [])
    {
        if (!$this->hook) {
            return;
        }

        try {
            $this->hook->onCall($method, $request, $metadata, $options);
        } catch (\Exception $e) {
            throw new GrpcClientHookException("grpc client hoot exception: {$e->getMessage()}", $e->getCode(), $e);
        }
    }
}
