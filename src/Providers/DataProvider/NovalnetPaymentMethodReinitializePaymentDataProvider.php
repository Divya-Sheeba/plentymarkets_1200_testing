<?php
/**
 * This file is used for customer reinitialize payment process
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * @license      https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
namespace Novalnet\Providers\DataProvider;

use Plenty\Plugin\Templates\Twig;
use Novalnet\Services\PaymentService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Novalnet\Helper\PaymentHelper;

/**
 * Class NovalnetPaymentMethodReinitializePaymentDataProvider
 *
 * @package Novalnet\Providers\DataProvider
 */
class NovalnetPaymentMethodReinitializePaymentDataProvider
{
    /**
     * Display the reinitiate payment button
     *
     * @param Twig $twig
     * @param Arguments $arg
     *
     * @return string
     */
    public function call(Twig $twig, $arg)
    {
        $order = $arg[0];
        $paymentService     = pluginApp(PaymentService::class);
        $basketRepository   = pluginApp(BasketRepositoryContract::class);
        $sessionStorage     = pluginApp(FrontendSessionStorageFactoryContract::class);
        $paymentHelper      = pluginApp(PaymentHelper::class);

        // Get the Novalnet payment method Id
        foreach($order['properties'] as $orderProperty) {
            if($orderProperty['typeId'] == 3)
            {
                $mopId = $orderProperty['value'];
            }
        }
        // Get the Novalnet payment key and MOP Id
        $transactionDetails = $paymentService->getDetailsFromPaymentProperty($order['id']);

        // Build the payment request paramters
        if(!empty($basketRepository->load())) {
            // Assign the billing and shipping address Id
            $basketRepository->load()->customerInvoiceAddressId = !empty($basketRepository->load()->customerInvoiceAddressId) ? $basketRepository->load()->customerInvoiceAddressId : $order['billingAddress']['id'];
            $basketRepository->load()->customerShippingAddressId = !empty($basketRepository->load()->customerShippingAddressId) ? $basketRepository->load()->customerShippingAddressId : $order['deliveryAddress']['id'];

            // Get the proper order amount even the system currency and payment currency are differ
            if(count($order['amounts']) > 1) {
                foreach($order['amounts'] as $orderAmount) {
                    if($basketRepository->load()->currency == $orderAmount['currency']) {
                        $invoiceAmount = $paymentHelper->convertAmountToSmallerUnit($orderAmount['invoiceTotal']);
                    }
                }
            } else {
                $invoiceAmount = $paymentHelper->convertAmountToSmallerUnit($order['amounts'][0]['invoiceTotal']);
            }

            // Set the required values into session
            $sessionStorage->getPlugin()->setValue('nnOrderNo', $order['id']);

            // Build the payment request parameters
            $paymentRequestData = $paymentService->generatePaymentParams($basketRepository->load(), strtoupper($transactionDetails['paymentName']), $invoiceAmount);

            // Set the payment request parameters into session
            $sessionStorage->getPlugin()->setValue('nnPaymentData', $paymentRequestData);

            // Get the Credit card form loading parameters
            if ($transactionDetails['paymentName'] == 'novalnet_cc') {
                 $ccFormDetails = $paymentService->getCreditCardAuthenticationCallData($basketRepository->load(), $transactionDetails['paymentName'], $invoiceAmount);
                 $ccCustomFields = $paymentService->getCcFormFields();
            }

            // Check if the birthday field needs to show for guaranteed payments
            $showBirthday = ((!isset($paymentRequestData['paymentRequestData']['customer']['billing']['company']) && !isset($paymentRequestData['paymentRequestData']['customer']['birth_date'])) ||  (isset($paymentRequestData['paymentRequestData']['customer']['birth_date']) && time() < strtotime('+18 years', strtotime($paymentRequestData['paymentRequestData']['customer']['birth_date'])))) ? true : false;
        }

        // If the Novalnet payments are rejected do the reinitialize payment
        if(strpos($transactionDetails['paymentName'], 'novalnet') !== false && ((!empty($transactionDetails['tx_status']) && !in_array($transactionDetails['tx_status'], ['PENDING', 'ON_HOLD', 'CONFIRMED', 'DEACTIVATED'])) || empty($transactionDetails['tx_status']))) {
            return $twig->render('Novalnet::NovalnetPaymentMethodReinitializePaymentDataProvider',
                                        [
                                            'order' => $order,
                                            'paymentMethodId' => $mopId,
                                            'paymentMopKey' => strtoupper($transactionDetails['paymentName']),
                                            'reinitializePayment' => 1,
                                            'nnPaymentProcessUrl' => $paymentService->getProcessPaymentUrl(),
                                            'paymentName' => $paymentHelper->getCustomizedTranslatedText('template_' . $transactionDetails['paymentName']),
                                            'ccFormDetails' => !empty($ccFormDetails) ? $ccFormDetails : '',
                                            'ccCustomFields' => !empty($ccCustomFields) ? $ccCustomFields : '',
                                            'showBirthday' => $showBirthday,
                                            'orderAmount' => $invoiceAmount
										]);
        } else {
            return '';
        }
    }
}
