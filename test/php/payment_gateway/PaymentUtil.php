<?php

namespace DanaUat\PaymentGateway;

use PHPUnit\Framework\TestCase;
use Dana\PaymentGateway\v1\Api\PaymentGatewayApi;
use Dana\PaymentGateway\v1\Model\QueryPaymentRequest;
use Dana\ObjectSerializer;
use DanaUat\Helper\Util;
use DanaUat\PaymentGateway\Scripts\WebAutomation;
use Exception;

class PaymentUtil extends TestCase
{
    /**
     * Cache for paid orders, to avoid creating new ones each time
     * [key: test case name, value: partner reference number]
     */
    private static $paidOrderCache = [];
    
    /**
     * Get or create a test order with paid status
     * 
     * @param PaymentGatewayApi $apiInstance API client instance to use
     * @param string $jsonPathFile Path to the JSON file with test data
     * @param string $createOrderTitleCase Title case for create order API
     * @param string $cacheKey Optional cache key, defaults to 'default'
     * @param string|null $partnerReferenceNo Optional partner reference number, if null one will be generated
     * @return string Partner reference number of the created/cached order
     * @throws Exception If order creation fails
     */
    public static function getOrCreatePaidOrder(
        PaymentGatewayApi $apiInstance,
        string $jsonPathFile,
        string $createOrderTitleCase,
        string $cacheKey = 'default',
        ?string $partnerReferenceNo = null
    ): string {
        // Check if we have a cached order for this cache key
        if (isset(self::$paidOrderCache[$cacheKey])) {
            echo "Using cached paid order with reference: " . self::$paidOrderCache[$cacheKey] . PHP_EOL;
            return self::$paidOrderCache[$cacheKey];
        }

        // Generate a partner reference number if one wasn't provided
        if ($partnerReferenceNo === null) {
            $partnerReferenceNo = Util::generatePartnerReferenceNo();
        }
        
        $caseName = 'CreateOrderRedirect';

        // Get the request data from the JSON file
        $jsonDict = Util::getRequest(
            $jsonPathFile,
            $createOrderTitleCase,
            $caseName
        );
        $jsonDict['validUpTo'] = Util::generateFormattedDate(25600, 7);

        // Create a CreateOrderByRedirectRequest object from the JSON request data
        $createOrderRequestObj = ObjectSerializer::deserialize(
            $jsonDict,
            'Dana\PaymentGateway\v1\Model\CreateOrderByRedirectRequest',
        );

        $createOrderRequestObj->setPartnerReferenceNo($partnerReferenceNo);

        try {
            // Make the API call
            $response = $apiInstance->createOrder($createOrderRequestObj);
            error_log("CreateOrder API response: " . $response->__toString());

            // Add delay to allow order to be processed
            sleep(2);

            // Run the web automation to complete the payment
            $webUrl = $response->getWebRedirectUrl();
            if ($webUrl) {
                echo "Starting web automation for payment at URL: {$webUrl}" . PHP_EOL;
                WebAutomation::automatePayment($webUrl);
                echo "Web automation completed" . PHP_EOL;

                // Query payment status to confirm it's completed
                echo "Querying payment status..." . PHP_EOL;
                $maxRetries = 3;
                $querySucceeded = false;

                // Create query payment request
                $queryRequest = new QueryPaymentRequest();
                $queryRequest->setOriginalPartnerReferenceNo($partnerReferenceNo);
                $queryRequest->setMerchantId(getenv('MERCHANT_ID'));

                // Try querying payment status a few times
                for ($i = 0; $i < $maxRetries; $i++) {
                    try {
                        echo "Query payment attempt " . ($i + 1) . " of {$maxRetries}..." . PHP_EOL;
                        $respQueryPayment = $apiInstance->queryPayment($queryRequest);

                        echo "Query payment response: status=" .
                            ($respQueryPayment->getTransactionStatusDesc() ?? 'unknown') .
                            ", code=" . $respQueryPayment->getResponseCode() . PHP_EOL;

                        if ($respQueryPayment->getTransactionStatusDesc() === 'SUCCESS') {
                            echo "Payment completed successfully!" . PHP_EOL;
                            $querySucceeded = true;
                            break;
                        }

                        // Wait before trying again
                        sleep(5);
                    } catch (Exception $e) {
                        echo "Query payment attempt " . ($i + 1) . " failed: " . $e->getMessage() . PHP_EOL;
                        sleep(2);
                    }
                }

                if (!$querySucceeded) {
                    echo "Warning: Could not confirm payment success. Continuing anyway..." . PHP_EOL;
                }
            } else {
                echo "No web URL found in the response, skipping automation" . PHP_EOL;
            }
            
            // Cache the successful order
            self::$paidOrderCache[$cacheKey] = $partnerReferenceNo;
            
        } catch (Exception $e) {
            throw new Exception("Failed to create paid test order: " . $e->getMessage());
        }
        
        return $partnerReferenceNo;
    }
}
