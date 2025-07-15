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
import java.util.List;
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

    private static final Logger log = LoggerFactory.getLogger(TestUtil.class);
    private static String jsonPathFile = CancelOrderTest.class.getResource("/request/components/PaymentGateway.json")
            .getPath();
    private final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    private static String userPin = "123321";
    private static String userPhone = "0811742234";
    private final String titleCase = "CancelOrder";
    private PaymentGatewayApi api;
    private static String partnerReferenceNoInit;
    @BeforeEach
    void setUp() throws IOException {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        api = Dana.getInstance().getPaymentGatewayApi();

//        Create order INIT
        List<String> dataOrder= OrderPGUtil.createOrder("CreateOrderRedirect");
        partnerReferenceNoInit = dataOrder.get(0);
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

//        Create order with status refunded
        log.info("Creating order to be refunded");
        String partnerReferenceNo = OrderPGUtil.refundOrder(
                userPhone,
                userPin,
                "CreateOrderRedirect");

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNo);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }
}
