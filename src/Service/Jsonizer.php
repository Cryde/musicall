<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\UnsupportedException;

class Jsonizer
{
    /**
     * @param Request $request
     * @param bool    $isArray
     *
     * @return array|mixed
     */
    public function decodeRequest(Request $request, $isArray = true)
    {
        return $this->decode($request->getContent(), $isArray);
    }

    /**
     * @param string|resource $json
     * @param bool   $isArray
     *
     * @return array|mixed
     */
    public function decode($json, $isArray = true)
    {
        if (is_resource($json)) {
            throw new UnsupportedException('JSON content as a ressource is not supported');
        }

        if ($isArray) {
            $data = (array)json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
        } else {
            $data = json_decode($json, false, 512, JSON_BIGINT_AS_STRING);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('invalid json body: ' . json_last_error_msg());
        }

        return $data;
    }
}
