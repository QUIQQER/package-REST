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
    public function register(Server $Server): void;

    /**
     * Get file containing OpenApi definition for this API.
     *
     * @return string|false - Absolute file path or false if no definition exists
     */
    public function getOpenApiDefinitionFile(): bool|string;

    /**
     * Get title of this API.
     *
     * @param QUI\Locale|null $Locale (optional)
     * @return string
     */
    public function getTitle(QUI\Locale $Locale = null): string;

    /**
     * Get unique internal API name.
     *
     * This is required for requesting specific data about an API (i.e. OpenApi definition).
     *
     * @return string - Only letters; no other characters!
     */
    public function getName(): string;
}
