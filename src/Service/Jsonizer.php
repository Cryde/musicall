<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Jsonizer
{
    /**
     * @return array|mixed
     */
    public function decodeRequest(Request $request, bool $isArray = true)
    {
        return $this->decode($request->getContent(), $isArray);
    }

    /**
     *
     * @return array|mixed
     */
    public function decode(string $json, bool $isArray = true)
    {
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
