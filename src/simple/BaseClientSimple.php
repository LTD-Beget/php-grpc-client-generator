<?php

namespace LTDBeget\util\PhpProtoGenerator\simple;

use LTDBeget\util\PhpProtoGenerator\simple\exceptions\AbortedException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\AlreadyExistsException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\CanceledException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\ClientSimpleException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\DataLossException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\DeadlineExceededException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\FailedPreconditionException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\UnavailableException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\InternalException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\InvalidArgumentException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\NotFoundException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\OutOfRangeException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\PermissionDeniedException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\ResourceExhaustedException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\UnauthenticatedException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\UnimplementedException;
use LTDBeget\util\PhpProtoGenerator\simple\exceptions\UnknownException;

/**
 * Class BaseClientSimple
 *
 * @package LTDBeget\util\PhpProtoGenerator\simple
 */
class BaseClientSimple
{
    /**
     * https://github.com/grpc/grpc-go/blob/master/codes/codes.go
     *
     * @param \StdClass $status
     *
     * @throws AbortedException
     * @throws AlreadyExistsException
     * @throws CanceledException
     * @throws ClientSimpleException
     * @throws DataLossException
     * @throws DeadlineExceededException
     * @throws FailedPreconditionException
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
                throw new ClientSimpleException("Unknown status {$status->code}: {$status->details}", $status->code);
        }
    }
}
