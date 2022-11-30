<?php

namespace QUI\REST\Utils;

use Psr\Http\Message\ServerRequestInterface;
use QUI;
use QUI\Utils\Security\Orthos;

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
     * Get an argument (path variable) from a Request
     *
     * @param ServerRequestInterface $Request
     * @param string $arg - Argument name
     * @return string|false - (sanitized) arg or false if not set/found
     */
    public static function getArgFromRequest(ServerRequestInterface $Request, $arg)
    {
        $content = $Request->getAttribute($arg);

        if (empty($content)) {
            return false;
        }

        return Orthos::clear($content);
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

    public static function getRequestedLanguage(): ?string
    {
        if (!QUI::getRequest()->headers->has('Accept-Language')) {
            return null;
        }

        // Header should be conforming RFC-5646 Section 2.1 (e.g. 'en-US')
        // QUIQQER just uses two character language codes, therefore just the first two characters are used
        $requestedLanguage = mb_substr(QUI::getRequest()->headers->get('Accept-Language'), 0, 2);

        return $requestedLanguage ?: null;
    }
}
