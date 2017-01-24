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
interface ProviderInterface
{
    /**
     * Registered some REST Api Calls
     *
     * @param Server $Server
     */
    public function register(Server $Server);
}
