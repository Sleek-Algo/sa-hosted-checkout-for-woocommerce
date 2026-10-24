<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\Treasury;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class TransactionEntryService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Retrieves a list of TransactionEntry objects.
     *
     * @param null|array{created?: array|int, effective_at?: array|int, ending_before?: string, expand?: string[], financial_account: string, limit?: int, order_by?: string, starting_after?: string, transaction?: string} $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @return \SAHCFWC\Libraries\Stripe\Collection<\SAHCFWC\Libraries\Stripe\Treasury\TransactionEntry>
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/treasury/transaction_entries', $params, $opts);
    }

    /**
     * Retrieves a TransactionEntry object.
     *
     * @param string $id
     * @param null|array{expand?: string[]} $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @return \SAHCFWC\Libraries\Stripe\Treasury\TransactionEntry
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/treasury/transaction_entries/%s', $id), $params, $opts);
    }
}
