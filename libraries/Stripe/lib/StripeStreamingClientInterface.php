<?php

namespace SAHCFWC\Libraries\Stripe;

/**
 * Interface for a SAHCFWC\Libraries\Stripe client.
 */
interface StripeStreamingClientInterface extends BaseStripeClientInterface
{
    public function requestStream($method, $path, $readBodyChunkCallable, $params, $opts);
}
