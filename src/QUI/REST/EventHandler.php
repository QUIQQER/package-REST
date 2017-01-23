<?php

/**
 * This file contains \QUI\REST\EventHandler
 */

namespace QUI\REST;

use QUI;

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
        $uri     = $Request->getRequestUri();

        // ask rest domain
        if (empty($url)) {
            return;
        }

        $uri      = $uri . '/';
        $basePath = '/api/';

        if (strpos($uri, $basePath) === false) {
            return;
        }

        $Server = new Server(array(
            'basePath' => $basePath
        ));

        $Server->run();
        exit;
    }
}
