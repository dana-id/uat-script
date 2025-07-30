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

import java.io.IOException;
import java.util.*;

import id.dana.util.ConfigUtil;
import id.dana.util.RetryTestUtil;
import id.dana.util.TestUtil;
import okhttp3.OkHttpClient;
import org.junit.jupiter.api.*;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class CancelOrderTest {

    private static final Logger log = LoggerFactory.getLogger(TestUtil.class);
    private static String jsonPathFile = CancelOrderTest.class.getResource("/request/components/PaymentGateway.json")
            .getPath();
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    private static String userPin = "123321";
    private static String userPhone = "0811742234";
    private final String titleCase = "CancelOrder";
    private static PaymentGatewayApi api;
    private static String partnerReferenceNoInit,partnerReferenceNoRefunded;

    @BeforeAll
    static void setUp() throws IOException {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        api = Dana.getInstance().getPaymentGatewayApi();

//        Create order
        List<String> dataOrder= createOrder();
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

        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @DisplayName("Cancel Order with User Status Abnormal")
    void testCancelOrderWithUserStatusAbnormal() throws IOException {
        String caseName = "CancelOrderUserStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @DisplayName("Cancel Order with Merchant Status Abnormal")
    void testCancelOrderWithMerchantStatusAbnormal() throws IOException {
        String caseName = "CancelOrderMerchantStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @DisplayName("Cancel Order with Invalid Mandatory Field")
    void testCancelOrderInvalidMandatoryField() throws IOException {
        String caseName = "CancelOrderInvalidMandatoryField";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @DisplayName("Cancel Order with Transaction Not Found")
    void testCancelOrderTransactionNotFound() throws IOException {
        String caseName = "CancelOrderTransactionNotFound";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @DisplayName("Cancel Order with Expired Transaction")
    void testCancelOrderWithExpiredTransaction() throws IOException {
        String caseName = "CancelOrderTransactionExpired";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @DisplayName("Cancel Order with Agreement Not Allowed")
    void testCancelOrderWithAgreementNotAllowed() throws IOException {
        String caseName = "CancelOrderNotAllowed";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @DisplayName("Cancel Order with Account Status Abnormal")
    void testCancelOrderWithAccountStatusAbnormal() throws IOException {
        String caseName = "CancelOrderAccountStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @DisplayName("Cancel Order with Insufficient Funds")
    void testCancelOrderWithInsufficientFunds() throws IOException {
        String caseName = "CancelOrderInsufficientFunds";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
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

        CancelOrderResponse response = apiWithCustomHeader.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @DisplayName("Cancel Order with Timeout")
    void testCancelOrderTimeout() throws IOException {
        String caseName = "CancelOrderTimeout";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        requestData.setOriginalPartnerReferenceNo("5005701");
        requestData.setOriginalReferenceNo("5005701");

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @RetryTestUtil.Retry
    @DisplayName("Cancel Order with Order Has Been Refunded")
    void testCancelOrderInvalidTransactionStatus() throws IOException {
        partnerReferenceNoRefunded = refundOrder(
                userPhone,
                userPin);

        String caseName = "CancelOrderInvalidTransactionStatus";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoRefunded);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoRefunded);

        CancelOrderResponse response = api.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    public static List<String> createOrder() {
        List<String> dataOrder = new ArrayList<>();

        CreateOrderByApiRequest requestData = TestUtil.getRequest(
                jsonPathFile,
                "CreateOrder",
                "CreateOrderApi",
                CreateOrderByApiRequest.class);

        // Assign unique reference and merchant ID
        String partnerReferenceNo = UUID.randomUUID().toString();
        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNo);

        CreateOrderResponse response = api.createOrder(requestData);
        Assertions.assertTrue(response.getResponseCode().contains("200"),
                "Response code should be 200, but was: " + response.getResponseCode());

        //Index 0 is partnerReferenceNo
        dataOrder.add(partnerReferenceNo);
        //Index 1 is the web redirect URL
        dataOrder.add(response.getWebRedirectUrl());

        return dataOrder;
    }

    public static String refundOrder(
            String phoneNumber,
            String pin) {

        String tempPartnerReferenceNo = payOrder(phoneNumber, pin);

        RefundOrderRequest requestRefund = TestUtil.getRequest(
                jsonPathFile,
                "RefundOrder",
                "RefundOrderValidScenario",
                RefundOrderRequest.class);

        requestRefund.setOriginalPartnerReferenceNo(tempPartnerReferenceNo);
        requestRefund.setPartnerRefundNo(tempPartnerReferenceNo);
        requestRefund.setMerchantId(merchantId);

        RefundOrderResponse responseRefund = api.refundOrder(requestRefund);
        return tempPartnerReferenceNo;
    }

    public static String payOrder(String phoneNumber, String pin) {
        List<String> dataOrder = createOrder();
        PaymentPGUtil.payOrder(phoneNumber,pin,dataOrder.get(1));
        return dataOrder.get(0);
    }

    public static String queryOrder(String partnerReferenceNo) throws IOException {
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, "QueryPayment", "QueryPaymentCreatedOrder",
                QueryPaymentRequest.class);

//        Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        return response.getResponseCode();
    }
}
