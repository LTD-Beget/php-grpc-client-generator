<?php

namespace LTDBeget\PhpProtoGenerator\simple;

use LTDBeget\PhpProtoGenerator\simple\exceptions\HostUnreachableException;

/**
 * Class BaseClientSimple
 *
 * @package LTDBeget\PhpProtoGenerator\simple
 */
class BaseClientSimple
{
    /**
     * @param \StdClass $status
     *
     * @throws HostUnreachableException
     */
    protected function checkStatus($status)
    {
        switch ($status->code) {
            case 14:
                throw new HostUnreachableException("Host unreachable: {$status->details}", $status->code);

            // TODO-jk handle all status codes
        }
    }
}
