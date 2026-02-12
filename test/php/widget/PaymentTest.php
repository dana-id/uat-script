<?php

/**
 * @fileoverview Dana Widget Payment Test Suite for DANA Widget Integration
 * 
 * This suite validates the DANA widget payment functionality of the DANA Widget API.
 * Each test case includes comprehensive documentation following PHPDoc standards.
 * Tests cover success scenarios, error handling, validation, and idempotency behavior.
 * 
 * @author Integration Test Team
 * @version 1.0.0
 * @since 2024
 * @package DanaUat\Widget
 * @requires dana-php-sdk
 * @requires phpunit
 * @requires dotenv
 * 
 * Test Categories:
 * - Payment Success: Validates successful payment processing
 * - Error Handling: Tests various error scenarios and validation
 * - Idempotency: Verifies duplicate request handling behavior
 * - Security: Tests signature validation and authentication
 * - Timeout: Validates timeout handling
 */

namespace DanaUat\Widget;

use PHPUnit\Framework\TestCase;
use Dana\Widget\v1\Api\WidgetApi;
use Dana\Configuration;
use Dana\ObjectSerializer;
use Dana\Env;
use Dana\ApiException;
use DanaUat\Helper\Assertion;
use DanaUat\Helper\Util;
use DanaUat\Widget\PaymentUtil;
use Exception;

class PaymentTest extends TestCase
{
    private static $titleCase = 'Payment';
    private static $jsonPathFileWidget = 'resource/request/components/Widget.json';
    private static $apiInstanceWidget;
    private static $configuration;
    private static $merchantId;
    private static $sharedOriginalPartnerReference;

    public static function setUpBeforeClass(): void
    {
        // Set up configuration with authentication settings
        self::$configuration = new Configuration();

        // The Configuration constructor automatically loads values from environment variables
        // But we can manually set them if needed
        self::$configuration->setApiKey('PRIVATE_KEY', getenv('PRIVATE_KEY'));
        self::$configuration->setApiKey('ORIGIN', getenv('ORIGIN'));
        self::$configuration->setApiKey('X_PARTNER_ID', getenv('X_PARTNER_ID'));
        self::$configuration->setApiKey('ENV', Env::SANDBOX);

        self::$merchantId = getenv('MERCHANT_ID');
        self::$apiInstanceWidget = new WidgetApi(null, self::$configuration);

        // Create one paid order (runs automation once); cache is reused by QueryOrderTest etc.
        self::$sharedOriginalPartnerReference = PaymentUtil::createPaymentWidgetPaid("PaymentSuccess");
    }

    /**
     * @testdox Should give success response for payment scenario
     * @description Validates successful payment processing through DANA Widget API
     * @test Verifies that a valid payment request returns success response with proper reference number
     * @expectedResult Payment request succeeds and returns valid response object
     * @category PaymentSuccess
     * @priority High
     * @author Integration Test Team
     * @since 1.0.0
     */
    public function testPaymentSuccess(): void
    {
        Util::withDelay(function () {
            $caseName = 'PaymentSuccess';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            $apiResponse = self::$apiInstanceWidget->widgetPayment($requestObj);
            // Assert the response matches the expected data
            Assertion::assertResponse(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName,
                $apiResponse->__toString(),
                []
            );
        });
    }

    /**
     * @testdox Should give fail response for merchant not exist or status abnormal scenario
     * @description Validates error handling when merchant ID does not exist or has abnormal status
     * @test Verifies that invalid merchant scenarios return appropriate error responses
     * @expectedResult API returns merchant-related error with proper error code and message
     * @category ErrorHandling
     * @priority High
     * @author Integration Test Team
     * @since 1.0.0
     */
    public function testPaymentFailMerchantNotExistOrStatusAbnormal(): void
    {
        Util::withDelay(function () {
            $caseName = 'PaymentFailMerchantNotExistOrStatusAbnormal';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );

            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();
            $jsonDict['validUpTo'] = Util::generateFormattedDate(600, 7);

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            // Assert the response matches the expected data
            try {
                self::$apiInstanceWidget->widgetPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget,
                    self::$titleCase,
                    $caseName,
                    $e,
                    [],
                    $jsonDict
                );
            }
        });
    }

    /**
     * @testdox Should give fail response for inconsistent payment scenario
     * @description Validates detection of inconsistent payment data with same partner reference
     * @test Verifies that modified payment data with same reference ID is properly rejected
     * @expectedResult API returns inconsistent request error when data differs for same reference
     * @category DataValidation
     * @priority High
     * @author Integration Test Team
     * @since 1.0.0
     */
    public function testPaymentFailInconsistentRequest(): void
    {
        $this->markTestSkipped('Inconsistent request scenario skipped');
        Util::withDelay(function () {
            $caseName = 'PaymentFailInconsistentRequest';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );

            $jsonDict['merchantId'] = self::$merchantId;
            $fixedPartnerRef = PaymentUtil::generatePartnerReferenceNo();
            $jsonDict['partnerReferenceNo'] = $fixedPartnerRef;
            $jsonDict['validUpTo'] = Util::generateFormattedDate(600, 7);

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            try {
                // First request with original data
                self::$apiInstanceWidget->widgetPayment($requestObj);

                // Second request with modified amount but same partnerReferenceNo
                $jsonDict['amount']['currency'] = 'IDR';
                $jsonDict['amount']['value'] = '2000.00';
                $modifiedRequestObj = ObjectSerializer::deserialize(
                    $jsonDict,
                    'Dana\Widget\v1\Model\WidgetPaymentRequest'
                );

                self::$apiInstanceWidget->widgetPayment($modifiedRequestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget,
                    self::$titleCase,
                    $caseName,
                    $e,
                    [],
                    $jsonDict
                );
            }
        });
    }

    /**
     * @testdox Should give fail response for invalid field format scenario
     * @description Validates error handling for invalid field formats in payment requests
     * @test Verifies that malformed or invalid field values return appropriate validation errors
     * @expectedResult API returns field format validation error with descriptive message
     * @category FieldValidation
     * @priority Medium
     * @author Integration Test Team
     * @since 1.0.0
     */
    public function testPaymentInvalidFieldFormat(): void
    {
        Util::withDelay(function () {
            $caseName = 'PaymentFailInvalidFormat';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );

            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();
            $jsonDict['validUpTo'] = Util::generateFormattedDate(600, 7);

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            // Assert the response matches the expected data
            try {
                self::$apiInstanceWidget->widgetPayment($requestObj);
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget,
                    self::$titleCase,
                    $caseName,
                    $e,
                    [],
                    $jsonDict
                );
            }
        });
    }

    /**
     * @testdox Should give fail response for missing or invalid mandatory field scenario
     * @description Validates error handling when required fields are missing or invalid
     * @test Verifies that requests without mandatory headers (X-TIMESTAMP) are rejected
     * @expectedResult API returns mandatory field validation error with clear indication
     * @category MandatoryFieldValidation
     * @priority High
     * @author Integration Test Team
     * @since 1.0.0
     */
    public function testPaymentFailMissingOrInvalidMandatoryField(): void
    {
        Util::withDelay(function () {
            $caseName = 'PaymentFailMissingOrInvalidMandatoryField';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );

            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();
            $jsonDict['validUpTo'] = Util::generateFormattedDate(600, 7);

            // Create headers without timestamp to test validation
            $headers = Util::getHeadersWithSignature(
                'POST',
                '/rest/redirection/v1.0/debit/payment-host-to-host.htm',
                $jsonDict,
                false
            );

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            try {
                Util::executeApiRequest(
                    'POST',
                    'https://api.sandbox.dana.id/rest/redirection/v1.0/debit/payment-host-to-host',
                    $headers,
                    $jsonDict
                );

                $this->fail('Expected ApiException for missing X-TIMESTAMP but the API call succeeded');
            } catch (ApiException $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget,
                    self::$titleCase,
                    $caseName,
                    $e,
                    [],
                    $jsonDict
                );
            }
        });
    }

    /**
     * @testdox Should give fail response for invalid signature scenario
     * @description Validates security by testing invalid signature rejection
     * @test Verifies that requests with invalid signatures are properly rejected
     * @expectedResult API returns signature validation error with security-related message
     * @category SecurityValidation
     * @priority Critical
     * @author Integration Test Team
     * @since 1.0.0
     */
    public function testPaymentInvalidSignature(): void
    {
        Util::withDelay(function () {
            $caseName = 'PaymentFailInvalidSignature';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );

            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();
            $jsonDict['validUpTo'] = Util::generateFormattedDate(600, 7);

            // Create headers without timestamp to test validation
            $headers = Util::getHeadersWithSignature(
                'POST',
                '/rest/redirection/v1.0/debit/payment-host-to-host.htm',
                $jsonDict,
                true,
                false,
                true
            );

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            try {
                Util::executeApiRequest(
                    'POST',
                    'https://api.sandbox.dana.id/rest/redirection/v1.0/debit/payment-host-to-host',
                    $headers,
                    $jsonDict
                );

                $this->fail('Expected ApiException for missing X-TIMESTAMP but the API call succeeded');
            } catch (Exception $e) {
                Assertion::assertApiException(
                    self::$jsonPathFileWidget,
                    self::$titleCase,
                    $caseName,
                    $e,
                    [],
                    $jsonDict
                );
            }
        });
    }

    /**
     * @testdox Should give fail response for timeout scenario
     * @description Validates timeout handling when API requests exceed time limits
     * @test Verifies that timeout scenarios return appropriate HTTP 504 Gateway Timeout
     * @expectedResult API returns 504 Gateway Timeout error code for timeout scenarios
     * @category TimeoutHandling
     * @priority Medium
     * @author Integration Test Team
     * @since 1.0.0
     */
    public function testPaymentTimeout(): void
    {
        $this->markTestSkipped('Skipping testPaymentTimeout as requested.');
        Util::withDelay(function () {
            $caseName = 'PaymentFailTimeout';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );

            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();
            $jsonDict['validUpTo'] = Util::generateFormattedDate(600, 7);

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            try {
                self::$apiInstanceWidget->widgetPayment($requestObj);
            } catch (ApiException $e) {
                $this->assertEquals(504, $e->getCode(), "Gateway Time-out");
            }
        });
    }

    /**
     * @testdox Should verify idempotency behavior for payment requests
     * @description Validates idempotency behavior by testing duplicate request handling
     * @test Verifies that duplicate requests with same reference return consistent responses
     * @expectedResult Duplicate requests either return identical responses or proper duplicate errors
     * @category IdempotencyValidation
     * @priority High
     * @author Integration Test Team
     * @since 1.0.0
     * @steps
     * 1. Make initial payment request with fixed partner reference
     * 2. Make immediate duplicate request with same reference
     * 3. Make delayed duplicate request to test persistence
     * @scenarios
     * - True Idempotency: Returns identical response objects
     * - Duplicate Detection: Returns proper duplicate error messages
     */
    public function testPaymentIdempotent(): void
    {
        $this->markTestSkipped('Skipping testPaymentIdempotent as requested.');
        Util::withDelay(function () {
            $caseName = 'PaymentIdempotent';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );

            $jsonDict['merchantId'] = self::$merchantId;
            $fixedPartnerRef = 'IDEMPOTENT_TEST_' . time() . '_' . substr(md5(rand()), 0, 8);
            $jsonDict['partnerReferenceNo'] = $fixedPartnerRef;
            $jsonDict['validUpTo'] = Util::generateFormattedDate(600, 7);

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            $isIdempotent = false;
            $firstResponse = null;

            try {
                // STEP 1: First request - should succeed
                $firstResponse = self::$apiInstanceWidget->widgetPayment($requestObj);

                sleep(1);

                // STEP 2: Second request with SAME partnerReferenceNo - test idempotency
                try {
                    $secondResponse = self::$apiInstanceWidget->widgetPayment($requestObj);

                    if ($firstResponse->getReferenceNo() === $secondResponse->getReferenceNo()) {
                        $isIdempotent = true;

                        $this->assertEquals(
                            $firstResponse->getReferenceNo(),
                            $secondResponse->getReferenceNo(),
                            'Reference numbers must be identical for truly idempotent requests'
                        );

                        if (method_exists($firstResponse, 'getResponseCode') && method_exists($secondResponse, 'getResponseCode')) {
                            $this->assertEquals(
                                $firstResponse->getResponseCode(),
                                $secondResponse->getResponseCode(),
                                'Response codes must be identical for truly idempotent requests'
                            );
                        }
                    } else {
                        $this->fail('Both requests succeeded but returned different reference numbers - NOT truly idempotent');
                    }
                } catch (ApiException $duplicateError) {
                    $errorResponse = json_decode($duplicateError->getResponseBody(), true);

                    $isDuplicateError =
                        (isset($errorResponse['responseCode']) &&
                            (strpos($errorResponse['responseCode'], 'DUPLICATE') !== false ||
                                strpos($errorResponse['responseCode'], 'ALREADY_EXIST') !== false ||
                                strpos($errorResponse['responseCode'], 'IDEMPOTENT') !== false)) ||
                        (isset($errorResponse['responseMessage']) &&
                            (strpos(strtolower($errorResponse['responseMessage']), 'duplicate') !== false ||
                                strpos(strtolower($errorResponse['responseMessage']), 'already exist') !== false ||
                                strpos(strtolower($errorResponse['responseMessage']), 'idempotent') !== false));

                    if ($isDuplicateError) {
                        Assertion::assertApiException(
                            self::$jsonPathFileWidget,
                            self::$titleCase,
                            $caseName,
                            $duplicateError,
                            ['partnerReferenceNo' => $fixedPartnerRef],
                            $jsonDict
                        );
                    } else {
                        $this->fail('Expected duplicate error, but got unexpected error: ' . $duplicateError->getMessage());
                    }
                }

                // STEP 3: Test with longer delay to check idempotency persistence
                sleep(1);

                $thirdResponse = self::$apiInstanceWidget->widgetPayment($requestObj);

                if ($isIdempotent) {
                    Assertion::assertResponse(
                        self::$jsonPathFileWidget,
                        self::$titleCase,
                        $caseName,
                        $thirdResponse->__toString(),
                        []
                    );
                }
            } catch (ApiException $firstError) {
                $this->markTestSkipped('First request failed - cannot test idempotency: ' . $firstError->getMessage());
            }
        });
    }

    /**
     * @testdox Should give fail response for internal server error scenario
     * @description Validates error handling when server encounters internal processing errors
     * @test Verifies that server-side errors return appropriate HTTP 500 Internal Server Error
     * @expectedResult API returns 500 Internal Server Error code for server-side processing failures
     * @category ServerErrorHandling
     * @priority Medium
     * @author Integration Test Team
     * @since 1.0.0
     */
    public function testPaymentFailInternalServerError(): void
    {
        Util::withDelay(function () {
            $caseName = 'PaymentFailInternalServerError';
            $jsonDict = Util::getRequest(
                self::$jsonPathFileWidget,
                self::$titleCase,
                $caseName
            );

            $jsonDict['merchantId'] = self::$merchantId;
            $jsonDict['partnerReferenceNo'] = PaymentUtil::generatePartnerReferenceNo();
            $jsonDict['validUpTo'] = Util::generateFormattedDate(600, 7);

            $requestObj = ObjectSerializer::deserialize(
                $jsonDict,
                'Dana\Widget\v1\Model\WidgetPaymentRequest'
            );

            try {
                self::$apiInstanceWidget->widgetPayment($requestObj);
                $this->fail('Expected ApiException for internal server error but the API call succeeded');
            } catch (ApiException $e) {
                // Verify it's a 500 Internal Server Error
                $this->assertEquals(500, $e->getCode(), "Expected HTTP 500 Internal Server Error, got {$e->getCode()}");
                
                // Use assertApiException to validate the error response
                Assertion::assertApiException(
                    self::$jsonPathFileWidget,
                    self::$titleCase,
                    $caseName,
                    $e,
                    [],
                    $jsonDict
                );
            } catch (Exception $e) {
                $this->fail('Unexpected exception: ' . $e->getMessage());
            }
        });
    }
}
