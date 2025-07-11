package id.dana.paymentgateway;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.paymentgateway.v1.model.*;

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;

import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import io.restassured.path.json.JsonPath;
import okhttp3.OkHttpClient;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.DisplayName;
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

    private static String
            partnerReferenceNoPaid,
            partnerReferenceNoCancel,
            partnerReferenceNoInit,
            partnerReferenceNoRefund;

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

        createOrder("REFUND");
    }

    @Test
    @DisplayName("Cancel Order Valid Scenario")
    void testCancelOrderValid() throws IOException {
        String caseName = "CancelOrderValidScenario";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with In Progress Order")
    void testCancelOrderInProgress() throws IOException {
        String caseName = "CancelOrderInProgress";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        CancelOrderResponse response = api.cancelOrder(requestData);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with User Status Abnormal")
    void testCancelOrderWithUserStatusAbnormal() throws IOException {
        String caseName = "CancelOrderUserStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Merchant Status Abnormal")
    void testCancelOrderWithMerchantStatusAbnormal() throws IOException {
        String caseName = "CancelOrderMerchantStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Invalid Mandatory Field")
    void testCancelOrderInvalidMandatoryField() throws IOException {
        String caseName = "CancelOrderInvalidMandatoryField";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Transaction Not Found")
    void testCancelOrderTransactionNotFound() throws IOException {
        String caseName = "CancelOrderTransactionNotFound";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Expired Transaction")
    void testCancelOrderWithExpiredTransaction() throws IOException {
        String caseName = "CancelOrderTransactionExpired";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Agreement Not Allowed")
    void testCancelOrderWithAgreementNotAllowed() throws IOException {
        String caseName = "CancelOrderNotAllowed";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Account Status Abnormal")
    void testCancelOrderWithAccountStatusAbnormal() throws IOException {
        String caseName = "CancelOrderAccountStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Insufficient Funds")
    void testCancelOrderWithInsufficientFunds() throws IOException {
        String caseName = "CancelOrderInsufficientFunds";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Invalid Signature")
    void testCancelOrderUnauthorized() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();

        String caseName = "CancelOrderUnauthorized";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);

        requestData.setMerchantId(merchantId);
        customHeaders.put(
                DanaHeader.X_SIGNATURE,
                "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = apiWithCustomHeader.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Timeout")
    void testCancelOrderTimeout() throws IOException {
        String caseName = "CancelOrderTimeout";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @DisplayName("Cancel Order with Order Has Been Refunded")
    void testCancelOrderInvalidTransactionStatus() throws IOException {
        String caseName = "CancelOrderInvalidTransactionStatus";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoRefund);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoRefund);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    private void createOrder(String status){
        String createOrderCase = "CreateOrder";
        String refundOrderCase = "RefundOrder";
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
            case "REFUND":
                // Logic to create a pending order
                String caseOrderRefund = "CreateOrderRedirect";
                String caseValidRefund = "RefundOrderValidScenario";

                CreateOrderByRedirectRequest requestOrderRefund = TestUtil.getRequest(jsonPathFile, createOrderCase, caseOrderRefund,
                        CreateOrderByRedirectRequest.class);

                RefundOrderRequest requestValidRefund = TestUtil.getRequest(jsonPathFile, refundOrderCase, caseValidRefund,
                        RefundOrderRequest.class);

                partnerReferenceNoRefund = UUID.randomUUID().toString();
                requestOrderRefund.setPartnerReferenceNo(partnerReferenceNoRefund);
                requestOrderRefund.setMerchantId(merchantId);
                requestValidRefund.setOriginalPartnerReferenceNo(partnerReferenceNoRefund);
                requestValidRefund.setMerchantId(merchantId);

                CreateOrderResponse responseOrderRefund = api.createOrder(requestOrderRefund);
                RefundOrderResponse responseValidRefund = api.refundOrder(requestValidRefund);

                log.info("Refund Order Response: " + responseValidRefund.getResponseCode());
                Assertions.assertTrue(responseOrderRefund.getResponseCode().contains("200"));
                break;
            default:
                throw new IllegalArgumentException("Unknown status: " + status);
        }
    }
}
