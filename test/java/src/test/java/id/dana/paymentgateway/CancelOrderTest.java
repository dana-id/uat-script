package id.dana.paymentgateway;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.paymentgateway.v1.model.CancelOrderRequest;
import id.dana.paymentgateway.v1.model.CancelOrderResponse;
import id.dana.paymentgateway.v1.model.CreateOrderByRedirectRequest;
import id.dana.paymentgateway.v1.model.CreateOrderResponse;

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;

import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import io.restassured.path.json.JsonPath;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class CancelOrderTest {

    private static String jsonPathFile = CancelOrderTest.class.getResource("/request/components/PaymentGateway.json")
            .getPath();
    private static final Logger log = LoggerFactory.getLogger(CancelOrderTest.class);

    private final String titleCase = "CancelOrder";
    private final String merchantId = "216620010016033632482";

    private PaymentGatewayApi api;

    private static String partnerReferenceNoPaid,partnerReferenceNoCancel,partnerReferenceNoInit;

    @BeforeEach
    void setUp() {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        api = Dana.getInstance().getPaymentGatewayApi();

        createOrder("INIT");
    }

    @Test
    void testCancelOrderValid() throws IOException {
        String caseName = "CancelOrderValidScenario";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderInProgress() throws IOException {
        String caseName = "CancelOrderInProgress";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderWithUserStatusAbnormal() throws IOException {
        String caseName = "CancelOrderWithUserStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderWithMerchantStatusAbnormal() throws IOException {
        String caseName = "CancelOrderWithMerchantStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderInvalidMandatoryField() throws IOException {
        String caseName = "CancelOrderInvalidMandatoryField";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderTransactionNotFound() throws IOException {
        String caseName = "CancelOrderTransactionNotFound";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderWithExpiredTransaction() throws IOException {
        String caseName = "CancelOrderTransactionExpired";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderWithAgreementNotAllowed() throws IOException {
        String caseName = "CancelOrderNotAllowed";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderWithAccountStatusAbnormal() throws IOException {
        String caseName = "CancelOrderWithAccountStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderWithInsufficientFunds() throws IOException {
        String caseName = "CancelOrderWithInsufficientFunds";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderUnauthorized() throws IOException {
        String caseName = "CancelOrderUnauthorized";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderTimeout() throws IOException {
        String caseName = "CancelOrderTimeout";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    private void createOrder(String status){
        String createOrderCase = "CreateOrder";
        switch (status) {
            case "PAID":
                // Logic to create a successful order
                String caseOrder = "CreateOrderNetworkPayPgOtherWallet";
                CreateOrderByRedirectRequest requestDataPaid = TestUtil.getRequest(jsonPathFile, createOrderCase, caseOrder,
                        CreateOrderByRedirectRequest.class);

                partnerReferenceNoPaid = UUID.randomUUID().toString();
                requestDataPaid.setPartnerReferenceNo(partnerReferenceNoPaid);
                requestDataPaid.setMerchantId(merchantId);

                CreateOrderResponse responsePaid = api.createOrder(requestDataPaid);
                Assertions.assertTrue(responsePaid.getResponseCode().contains("2005400"));
                break;
            case "CANCEL":
                // Logic to create a failed order
                String caseOrderCancel = "CancelOrderValidScenario";
                CreateOrderByRedirectRequest requestDataCancel = TestUtil.getRequest(jsonPathFile, createOrderCase, caseOrderCancel,
                        CreateOrderByRedirectRequest.class);

                partnerReferenceNoCancel = UUID.randomUUID().toString();
                requestDataCancel.setPartnerReferenceNo(partnerReferenceNoCancel);
                requestDataCancel.setMerchantId(merchantId);

                CreateOrderResponse responseCancel = api.createOrder(requestDataCancel);
                Assertions.assertTrue(responseCancel.getResponseCode().contains("200"));
                break;
            case "INIT":
                // Logic to create a pending order
                String caseOrderInit = "CreateOrderRedirect";
                CreateOrderByRedirectRequest requestDataInit = TestUtil.getRequest(jsonPathFile, createOrderCase, caseOrderInit,
                        CreateOrderByRedirectRequest.class);

                partnerReferenceNoInit = UUID.randomUUID().toString();
                requestDataInit.setPartnerReferenceNo(partnerReferenceNoInit);
                requestDataInit.setMerchantId(merchantId);

                CreateOrderResponse responseInit = api.createOrder(requestDataInit);
                log.info("Create Order Response: " + responseInit.getResponseCode());
                Assertions.assertTrue(responseInit.getResponseCode().contains("200"));
                break;
            default:
                throw new IllegalArgumentException("Unknown status: " + status);
        }
    }
}
