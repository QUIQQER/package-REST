<?php

namespace QUI\REST;

use Psr\Http\Message\ResponseInterface;

/**
 * Creates special Response objects used for legay purposes (Slim 3)
 */
class ResponseFactory extends \Http\Factory\Guzzle\ResponseFactory
{
    /**
     * Create a new response.
     *
     * @param int $code HTTP status code; defaults to 200
     * @param string $reasonPhrase Reason phrase to associate with status code
     *     in generated response; if none is provided implementations MAY use
     *     the defaults as suggested in the HTTP specification.
     *
     * @return ResponseInterface
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        $Response = parent::createResponse($code, $reasonPhrase);

        return new Response(
            $Response->getStatusCode(),
            $Response->getHeaders(),
            $Response->getBody(),
            $Response->getProtocolVersion(),
            $Response->getReasonPhrase()
        );
    }
}
