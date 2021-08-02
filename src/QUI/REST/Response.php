<?php

namespace QUI\REST;

/**
 * This class is here for legacy purposes, because some Route handlers
 * from the Slim 3 era still use $Response->write() to write the response body.
 */
class Response extends \GuzzleHttp\Psr7\Response
{
    /**
     * Write content to body.
     *
     * @param string $body
     * @return Response
     */
    public function write(string $body): Response
    {
        $this->getBody()->write($body);

        return $this;
    }
}
