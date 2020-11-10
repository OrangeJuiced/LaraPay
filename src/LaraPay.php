<?php

namespace LaraPay;

use Illuminate\Support\Collection;
use Paynl\Config;
use Paynl\Error\Api;
use Paynl\Error\Error;
use Paynl\Error\Required\ApiToken;
use Paynl\Error\Required\ServiceId;
use Paynl\Transaction;
use Paynl\Paymentmethods;
use Illuminate\Support\Facades\Cache;

class LaraPay
{
    /**
     * 172800 equals 2 days
     * @var int
     */
    protected $cacheTime = 172800;

    private $hookRoute, $expiryInSeconds, $returnURLPrefix, $hookURLPrefix;

    /**
     * LaraPay constructor.
     *
     * @param string $hookRoute
     * @param int $expiryInSeconds
     * @param string|null $returnURLPrefix
     * @param string|null $hookURLPrefix
     */
    public function __construct(string $hookRoute, int $expiryInSeconds, string $returnURLPrefix = null, string $hookURLPrefix = null)
    {
        Config::setApiToken(config('larapay.tokenId'));
        Config::setServiceId(config('larapay.serviceId'));

        $this->hookURLPrefix = $hookURLPrefix;
        $this->hookRoute = $hookRoute;
        $this->expiryInSeconds = $expiryInSeconds;

        empty($returnURLPrefix) ? $this->returnURLPrefix = config('app.url') : $this->returnURLPrefix = $returnURLPrefix;
        empty($hookURLPrefix) ? $this->hookURLPrefix = config('larapay.hookURLPrefix', config('app.url')) : $this->hookURLPrefix = $hookURLPrefix;
    }

    /**
     * Returns all payment methods
     *
     * @return Collection
     */
    public function methods()
    {
        return collect(Paymentmethods::getList());
    }

    /**
     * Returns the name for a method
     *
     * @param null $id
     * @return bool
     */
    public function methodName($id = null)
    {
        if(!$id) return;

        return $this->methods()->groupBy('id')->get($id)->first();
    }

    /**
     * Returns iDEAL bank lists as collection
     * Banks are cached so speed won't be an issue here.
     *
     * @return array
     */
    public function banks()
    {
        return Cache::remember('banks', $this->cacheTime, function () {
            return collect(Paymentmethods::getBanks(10))->pluck('name', 'id');
        });
    }


    /**
     * Starts a transaction
     *
     * @param string $currency
     * @param float $amount
     * @param string $returnUrl
     * @param string $description
     * @param string $language
     * @return array
     * @throws \Exception
     */
    public function startTransaction(string $currency, float $amount, string $returnUrl, string $description, string $language = 'NL')
    {
        $data = [
            // Basic transaction information
            "amount" => $amount,
            "returnUrl" => $this->returnURLPrefix .  $returnUrl,
            "currency" => $currency,
            "description" => $description,

            // Information about transaction logistics
            "exchangeUrl" => isset($this->hookURLPrefix) ? $this->hookURLPrefix . $this->hookRoute : $this->returnURLPrefix . $this->hookRoute,
            "expireDate" => new \DateTime('+' . $this->expiryInSeconds . ' seconds'),

            // Additional information
            "ipaddress" => $_SERVER["REMOTE_ADDR"],
            "testmode" => env('larapay.testmode'),

            "enduser"   => [
                'language'  => $language
            ],
        ];

        $transaction = Transaction::start($data);

        return [
            "transactionId" => $transaction->getTransactionId(),
            "redirectUrl" => $transaction->getRedirectUrl(),
            "paymentreference" => $transaction->getPaymentReference(),
        ];
    }

    /**
     * Returns a transaction status.
     *
     * @param $id
     * @return array
     * @throws Api
     * @throws Error
     * @throws ApiToken
     * @throws ServiceId
     */
    public function getTransaction($id)
    {
        return Transaction::get($id)->getData();
    }

    /**
     * Return transaction data for exchange.
     *
     * @return array
     * @throws Api
     * @throws Error
     */
    public function getForExchange()
    {
        $transaction = Transaction::getForExchange();

        return $transaction->getData();
    }
}