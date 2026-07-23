<?php

/**
 * This file contains QUI\REST\Settings
 */

namespace QUI\REST;

use QUI;

/**
 * REST package settings
 */
class Settings extends QUI\Utils\Singleton
{
    /**
     * Return the required REST package configuration.
     *
     * @throws QUI\Exception
     */
    public static function getConfig(): QUI\Config
    {
        $Config = QUI::getPackage('quiqqer/rest')->getConfig();

        if ($Config === null) {
            throw new QUI\Exception('REST configuration is not available.');
        }

        return $Config;
    }
}
