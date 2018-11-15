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

        $requestBody = $Request->getBody();

        if (!empty($requestBody[$key])) {
            return $requestBody[$key];
        }

        return false;
    }
}
