<?php

namespace LaraPay;

use Paynl\Config;
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
    private $hook, $expiryInSeconds, $urlPrefix;

    public function __construct(string $hook, int $expiryInSeconds)
    {
        Config::setApiToken(config('larapay.tokenId'));
        Config::setServiceId(config('larapay.serviceId'));
        $this->hook = $hook;
        $this->expiryInSeconds = $expiryInSeconds;
        $this->urlPrefix = env('app.url');
    }
    /**
     * Returns all payment methods
     *
     * @return \Illuminate\Support\Collection
     */
    public function methods()
    {
        return collect($this->handleResponse(Paymentmethods::getList()));
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
     * Start a transaction
     *
     * @param array $arr
     * @return \Paynl\Result\Transaction\Start
     */
    public function startTransaction(string $currency, float $amount, string $returnurl, string $description)
    {

        $data = [

            // Basic transaction information
            "amount" =>         $amount,
            "returnUrl" =>      $this->urlPrefix .  $returnurl,
            "currency" =>       $currency,
            "description" =>    $description,

            // Information about transaction logistics
            "exchangeUrl" =>    $this->urlPrefix . $this->hook,
            "expireDate" =>     new \DateTime('+' . $this->expiryInSeconds . ' seconds'),

            // Additional information
            "ipaddress" =>      $_SERVER["REMOTE_ADDR"],
            "testmode" =>       env('larapay.testmode'),

            ];
        $transaction = Transaction::start($data);

        return [
                "transactionId" => $transaction->getTransactionId(),
                "redirectUrl" => $transaction->getRedirectUrl(),
                "paymentreference" => $transaction->getPaymentReference(),
            ];
    }

    /**
     * Returns a transaction
     *
     * @param $id
     * @return \Paynl\Result\Transaction\Transaction
     */
    public function getTransaction($id)
    {
        return Transaction::get($id);
    }
}