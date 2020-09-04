<?php

declare(strict_types=1);
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <shopware@adyen.com>
 */

namespace Adyen\Shopware\Handlers;

use Adyen\Shopware\Exception\PaymentException;
use Psr\Log\LoggerInterface;
use Adyen\Shopware\Service\PaymentResponseService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaymentResponseHandler
{

    const AUTHORISED = 'Authorised';
    const REFUSED = 'Refused';
    const REDIRECT_SHOPPER = 'RedirectShopper';
    const IDENTIFY_SHOPPER = 'IdentifyShopper';
    const CHALLENGE_SHOPPER = 'ChallengeShopper';
    const RECEIVED = 'Received';
    const PRESENT_TO_SHOPPER = 'PresentToShopper';
    const ERROR = 'Error';
    const CANCELED = 'Canceled';

    const PSP_REFERENCE = 'pspReference';
    const ORIGINAL_PSP_REFERENCE = 'originalPspReference';
    const ADDITIONAL_DATA = 'additionalData';
    const ACTION = 'action';


    // Merchant reference parameter in return GET parameters list
    const ADYEN_MERCHANT_REFERENCE = 'adyenMerchantReference';

    // Merchant reference key in API response
    const MERCHANT_REFERENCE = 'merchantReference';
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PaymentResponseService
     */
    private $paymentResponseService;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var PaymentResponseHandlerResult
     */
    private $paymentResponseHandlerResult;

    public function __construct(
        LoggerInterface $logger,
        PaymentResponseService $paymentResponseService,
        OrderTransactionStateHandler $transactionStateHandler,
        PaymentResponseHandlerResult $paymentResponseHandlerResult
    ) {
        $this->logger = $logger;
        $this->paymentResponseService = $paymentResponseService;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->paymentResponseHandlerResult = $paymentResponseHandlerResult;
    }

    /**
     * @param array $response
     * @param SalesChannelContext $salesChannelContext
     * @return
     */
    public function handlePaymentResponse(
        array $response,
        string $orderNumber,
        SalesChannelContext $salesChannelContext
    ): PaymentResponseHandlerResult {
        // Retrieve result code from response array
        $resultCode = $response['resultCode'];

        $this->paymentResponseHandlerResult->setResultCode($resultCode);

        // Retrieve PSP reference from response array if available
        if (!empty($response[self::PSP_REFERENCE])) {
            $this->paymentResponseHandlerResult->setPspReference($response[self::PSP_REFERENCE]);
        }

        // Set action in result object if available
        if (!empty($response[self::ACTION])) {
            $this->paymentResponseHandlerResult->setAction($response[self::ACTION]);
        }

        // Set additionalData in result object if available
        if (!empty($response[self::ADDITIONAL_DATA])) {
            $this->paymentResponseHandlerResult->setAdditionalData($response[self::ADDITIONAL_DATA]);
        }

        // Store response for cart until the payment is finalised
        $this->paymentResponseService->insertPaymentResponse(
            $response,
            $orderNumber,
            $salesChannelContext->getToken()
        );

        // Based on the result code start different payment flows
        switch ($resultCode) {
            case self::REFUSED:
                // Log Refused, no further steps needed
                $this->logger->error(
                    "The payment was refused, order transaction merchant reference: " .
                    $response[self::MERCHANT_REFERENCE]
                );

                break;
            case self::AUTHORISED:
            case self::REDIRECT_SHOPPER:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
            case self::RECEIVED:
            case self::PRESENT_TO_SHOPPER:
                // Do nothing here
                break;
            case self::ERROR:
                // Log error
                $this->logger->error(
                    'There was an error with the payment method. ' .
                    ' Result code "Error" in response: ' . print_r($response, true)
                );

                break;
            default:
                // Log unsupported resultCode
                $this->logger->error(
                    "There was an error with the payment method. id:  " .
                    ' Unsupported result code in response: ' . print_r($response, true)
                );
        }

        return $this->paymentResponseHandlerResult;
    }

    public function handleShopwareApis(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        PaymentResponseHandlerResult $paymentResponseHandlerResult
    ): void {
        $orderTransactionId = $transaction->getOrderTransaction()->getId();
        $context = $salesChannelContext->getContext();
        $stateTechnicalName = $transaction->getOrderTransaction()->getStateMachineState()->getTechnicalName();
        $resultCode = $paymentResponseHandlerResult->getResultCode();

        // Check if result is already handled
        if ($this->isTransactionHandled($stateTechnicalName, $resultCode)) {
            return;
        }

        // Get already stored transaction custom fileds
        $storedTransactionCustomFields = $transaction->getOrderTransaction()->getCustomFields() ?: [];

        // Store action, additionalData and originalPspReference in the transaction
        $transactionCustomFields = [];

        // Only store psp reference for the transaction if this is the first/original pspreference
        $pspReference = $this->paymentResponseHandlerResult->getPspReference();
        if (empty($storedTransactionCustomFields[self::ORIGINAL_PSP_REFERENCE]) && !empty($pspReference)) {
            $transactionCustomFields[self::ORIGINAL_PSP_REFERENCE] = $pspReference;
        }

        // Only store action for the transaction if this is the first action
        $action = $this->paymentResponseHandlerResult->getAction();
        if (empty($storedTransactionCustomFields[self::ACTION]) && !empty($action)) {
            $transactionCustomFields[self::ACTION] = $action;
        }

        // Only store additional data for the transaction if this is the first additional data
        $additionalData = $this->paymentResponseHandlerResult->getAction();
        if (empty($storedTransactionCustomFields[self::ADDITIONAL_DATA]) && !empty($additionalData)) {
            $transactionCustomFields[self::ADDITIONAL_DATA] = $additionalData;
        }

        // read custom fields before writing to it so we don't mess with other plugins
        $customFields = array_merge(
            $storedTransactionCustomFields,
            $transactionCustomFields
        );

        $transaction->getOrderTransaction()->setCustomFields($customFields);

        switch ($resultCode) {
            case self::AUTHORISED:
                // Tag order as paid
                $this->transactionStateHandler->paid($orderTransactionId, $context);
                break;
            case self::REFUSED:
                //Sync response, do nothing, wait for finalize()
                $this->transactionStateHandler->fail($orderTransactionId, $context);
                // Cancel the order
                throw new PaymentException(
                    'The payment was refused'
                );
                break;
            case self::REDIRECT_SHOPPER:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
            case self::RECEIVED:
            case self::PRESENT_TO_SHOPPER:
                // Return to the frontend without throwing an exception
                $this->transactionStateHandler->process($orderTransactionId, $context);
                break;
            case self::ERROR:
            default:
                // Cancel the order
                throw new PaymentException(
                    'The payment had an error or an unhandled result code'
                );
        }
    }

    public function handleAdyenApis(
        PaymentResponseHandlerResult $paymentResponseHandlerResult
    ): array {
        $resultCode = $paymentResponseHandlerResult->getResultCode();

        switch ($resultCode) {
            case self::AUTHORISED:
            case self::REFUSED:
            case self::ERROR:
                return [
                    "isFinal" => true,
                    "resultCode" => $this->paymentResponseHandlerResult->getResultCode(),
                ];
            case self::REDIRECT_SHOPPER:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
            case self::PRESENT_TO_SHOPPER:
                return [
                    "isFinal" => false,
                    "resultCode" => $this->paymentResponseHandlerResult->getResultCode(),
                    "action" => $this->paymentResponseHandlerResult->getAction()
                ];
                break;
            case self::RECEIVED:
                return [
                    "isFinal" => true,
                    "resultCode" => $this->paymentResponseHandlerResult->getResultCode(),
                    "additionalData" => $this->paymentResponseHandlerResult->getAdditionalData()
                ];
                break;
            default:
                return [
                    "isFinal" => true,
                    "resultCode" => self::ERROR,
                ];
        }
    }

    /**
     * Validates if the state is already changed where the resultCode would switch it
     * Example: Authorised -> paid, Refused -> failed
     *
     * @param string $transactionStateTechnicalName
     * @param string $resultCode
     * @return bool
     */
    private function isTransactionHandled(
        $transactionStateTechnicalName,
        $resultCode
    ) {
        // TODO check all the states and adyen resultCodes not just the straightforward ones
        switch ($resultCode) {
            case self::AUTHORISED:
                if ($transactionStateTechnicalName === OrderTransactionStates::STATE_PAID) {
                    return true;
                }
                break;
            case self::REFUSED:
            case self::ERROR:
                if ($transactionStateTechnicalName === OrderTransactionStates::STATE_FAILED) {
                    return true;
                }
                break;
            case self::CANCELED:
                if ($transactionStateTechnicalName === OrderTransactionStates::STATE_CANCELLED) {
                    return true;
                }
                break;
            default:
        }

        return false;
    }
}
