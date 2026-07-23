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
     * @return mixed - Field data if found, null if not found
     */
    public static function getFieldFromRequest(ServerRequestInterface $Request, string $key): mixed
    {
        $getParams = $Request->getQueryParams();

        if (array_key_exists($key, $getParams)) {
            return $getParams[$key];
        }

        $postParams = $Request->getParsedBody();

        if (is_array($postParams) && array_key_exists($key, $postParams)) {
            return $postParams[$key];
        }

        if (is_object($postParams) && property_exists($postParams, $key)) {
            return $postParams->{$key};
        }

        $RequestBody = $Request->getBody();
        $RequestBody->rewind();

        $requestBody = json_decode($RequestBody->getContents(), true);

        if (is_array($requestBody) && array_key_exists($key, $requestBody)) {
            return $requestBody[$key];
        }

        return null;
    }

    /**
     * Get an argument (path variable) from a Request
     *
     * @param ServerRequestInterface $Request
     * @param string $arg - Argument name
     * @return string|false - (sanitized) arg or false if not set/found
     */
    public static function getArgFromRequest(ServerRequestInterface $Request, string $arg): bool|string
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
    public static function isJson(string $str): bool
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
