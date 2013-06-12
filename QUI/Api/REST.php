<?php

/**
 * This file contains \QUI\Api\REST
 */

namespace \QUI\Api;

/**
 * The Rest Manager
 *
 * @author www.pcsg.de (Henning Leutz)
 * @package com.pcsg.qui.api
 */

class REST
{
    /**
     * Execute the REST Manager
     *
     * @param String $url
     */
    static function run($url)
    {
        echo $url;


        exit;

        $app = new \Slim\Slim();
    }
}
