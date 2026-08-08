<?php

/**
 * This file contains \QUI\REST\EventHandler
 */

namespace QUI\REST;

use QUI;
use QUI\Exception;

use function in_array;

/**
 * QUIQQER Event Handling
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class EventHandler
{
    /**
     * @param QUI\Rewrite $Rewrite
     * @param string $url
     * @throws Exception
     * @throws \Exception
     */
    public static function onRequest(QUI\Rewrite $Rewrite, string $url): void
    {
        $Request = QUI::getRequest();
        $Config = Settings::getConfig();

        $basePath = $Config->getValue('general', 'basePath');
        $baseHost = $Config->getValue('general', 'baseHost');

        if (!is_string($basePath)) {
            $basePath = '';
        }

        if (!is_string($baseHost)) {
            $baseHost = '';
        } else {
            $baseHost = str_replace(['http://', 'https://'], '', $baseHost);
            $baseHost = rtrim($baseHost, '/');
        }

        $uri = $Request->getRequestUri();
        $host = $Request->getHost();

        if (!empty($baseHost) && $host != $baseHost) {
            return;
        }

        if (!self::isRestRequest($uri, $basePath)) {
            return;
        }

        $requestedLanguage = QUI\REST\Utils\RequestUtils::getRequestedLanguage();
        $availableLanguages = QUI::availableLanguages();

        if ($requestedLanguage && in_array($requestedLanguage, $availableLanguages)) {
            QUI::getUserBySession()->getLocale()->setCurrent($requestedLanguage);
            QUI::getLocale()->setCurrent($requestedLanguage);
        } else {
            // set default system language
            $language = QUI::conf('globals', 'standardLanguage');
            QUI::getUserBySession()->getLocale()->setCurrent($language);
            QUI::getLocale()->setCurrent($language);
        }

        $Server = Server::getCurrentInstance();
        QUI::getEvents()->fireEvent('restInit', [$Server, $Request]);
        $Server->run();
        exit;
    }

    public static function isRestRequest(string $requestUri, string $basePath): bool
    {
        if (trim($basePath, '/') === '') {
            return true;
        }

        $requestPath = parse_url($requestUri, PHP_URL_PATH);

        if (!is_string($requestPath)) {
            return false;
        }

        $requestPath = '/' . trim($requestPath, '/');
        $basePath = '/' . trim($basePath, '/');

        return $requestPath === $basePath
            || str_starts_with($requestPath, $basePath . '/');
    }
}
