<?php

/**
 * This file contains \QUI\REST\EventHandler
 */

namespace QUI\REST;

use QUI;
use Slim\App;

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
     */
    public static function onRequest(QUI\Rewrite $Rewrite, $url)
    {
        $Request = QUI::getRequest();
        $Package = QUI::getPackage('quiqqer/rest');
        $Config  = $Package->getConfig();

        $basePath = $Config->getValue('general', 'basePath');
        $baseHost = $Config->getValue('general', 'baseHost');

        if (!is_string($basePath)) {
            $basePath = '';
        }

        if (!is_string($baseHost)) {
            $baseHost = '';
        }

        $uri  = $Request->getRequestUri();
        $host = $Request->getHost();

        if (!empty($baseHost) && $host != $baseHost) {
            return;
        }

        $uri = $uri.'/';

        if (!empty($basePath) && strpos($uri, $basePath) === false) {
            return;
        }

        $Server = Server::getCurrentInstance();
        $Server->run();
        exit;
    }
}
