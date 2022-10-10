<?php
/**
 * This file is used for handling the payment cancel event procedure
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * @license 	 https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
namespace Novalnet\Procedures;

use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\Order;
use Novalnet\Services\PaymentService;
use Novalnet\Constants\NovalnetConstants;

/**
 * Class VoidEventProcedure
 *
 * @package Novalnet\Procedures
 */
class VoidEventProcedure
{
     /**
     *
     * @var PaymentService
     */
    private $paymentService;

    /**
     * Constructor.
     *
     * @param PaymentService $paymentService
     */

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @param EventProceduresTriggered $eventTriggered
     *
     */
    public function run(EventProceduresTriggered $eventTriggered)
    {
        /* @var $order Order */
        $order = $eventTriggered->getOrder();
        // Get necessary information for the capture process
        $transactionDetails = $this->paymentService->getDetailsFromPaymentProperty($order->id);
        // Call the Void process for the On-Hold payments
        if($transactionDetails['tx_status'] == 'ON_HOLD') {
            $this->paymentService->doCaptureVoid($transactionDetails, NovalnetConstants::PAYMENT_VOID_URL);
        }
    }
}
