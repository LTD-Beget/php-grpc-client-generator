<?php
/**
 * @author Kolesnikov Vladislav
 * @date   03.08.17
 */

namespace LTDBeget\util\PhpGrpcClientGenerator;

interface RpcCallHook
{
    /**
     * @param string  $method
     * @param mixed   $request
     * @param array   $metadata
     * @param array   $options
     *
     * @return mixed
     */
    public function onCall($method, $request, array $metadata = [], array $options = []);
}