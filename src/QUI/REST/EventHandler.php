<?php

/**
 * This file contains \QUI\REST\EventHandler
 */

namespace QUI\Api;

use QUI\REST\Server;

/**
 * QUIQQER Event Handling
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class EventHandler
{
    public static function onRequest(\QUI\Rewrite $Rewrite, $url)
    {
        echo $url;
        exit;
    }
}
