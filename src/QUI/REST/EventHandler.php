<?php

/**
 * This file contains \QUI\REST\EventHandler
 */

namespace QUI\REST;

use QUI;
use QUI\Exception;

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
        $Package = QUI::getPackage('quiqqer/rest');
        $Config = $Package->getConfig();

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

        $uri = $uri . '/';

        if (!empty($basePath) && mb_strpos($uri, $basePath) === false) {
            return;
        }

        $requestedLanguage = QUI\REST\Utils\RequestUtils::getRequestedLanguage();
        if ($requestedLanguage) {
            QUI::getUserBySession()->getLocale()->setCurrent($requestedLanguage);
            QUI::getLocale()->setCurrent($requestedLanguage);
        }

        $Server = Server::getCurrentInstance();
        $Server->run();
        exit;
    }
}
