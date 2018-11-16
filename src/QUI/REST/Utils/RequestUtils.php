<?php

namespace QUI\REST\Utils;

use Psr\Http\Message\ServerRequestInterface;

class RequestUtils
{
    /**
     * Get a specific data field from a Request object
     *
     * Checks for both POST and GET params
     *
     * @param ServerRequestInterface $Request
     * @param string $key
     * @return false|string - Field data if found, FALSE if not found/set
     */
    public static function getFieldFromRequest(ServerRequestInterface $Request, $key)
    {
        $getParams = $Request->getQueryParams();

        if (!empty($getParams[$key])) {
            return $getParams[$key];
        }

        $postParams = $Request->getParsedBody();

        if (!empty($postParams[$key])) {
            return $postParams[$key];
        }

        $RequestBody = $Request->getBody();
        $RequestBody->rewind();

        $requestBody = $RequestBody->getContents();

        if (!self::isJson($requestBody)) {
            return false;
        }

        $requestBody = json_decode($requestBody, true);

        if (!empty($requestBody[$key])) {
            return $requestBody[$key];
        }

        return false;
    }

    /**
     * Check if a string is in JSON format
     *
     * @param string $str
     * @return bool
     */
    public static function isJson($str)
    {
        $str = json_decode($str, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($str);
    }
}
