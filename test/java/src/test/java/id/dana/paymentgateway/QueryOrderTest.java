package id.dana.paymentgateway;

import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.paymentgateway.v1.model.*;
import id.dana.util.ConfigUtil;
import id.dana.util.RetryTestUtil;
import id.dana.util.TestUtil;
import okhttp3.OkHttpClient;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;

import java.io.IOException;
import java.util.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class QueryOrderTest {
    private static final Logger log = LoggerFactory.getLogger(QueryOrderTest.class);
    private static final String jsonPathFile = QueryOrderTest.class.getResource("/request/components/PaymentGateway.json")
            .getPath();
    private static final String titleCase = "QueryPayment";
    private static String userPin = "123321";
    private static String userPhone = "0811742234";
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    private static PaymentGatewayApi api;
    private static String partnerReferenceNoInit,partnerReferenceNoPaid,partnerReferenceNoCancel;

    @BeforeAll
    static void setUpBeforeAll() throws IOException, InterruptedException {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        api = Dana.getInstance().getPaymentGatewayApi();

//        Create order
        List<String> dataOrder = createOrder();
        partnerReferenceNoInit = dataOrder.get(0);
        partnerReferenceNoCancel = cancelOrder();
    }

    @Test
    void testQueryPaymentCreatedOrder() throws IOException {
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryPaymentCreatedOrder";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

//        Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @RetryTestUtil.Retry
    void testQueryPaymentPaidOrder() throws InterruptedException {
        partnerReferenceNoPaid = payOrder(
                userPhone,
                userPin);

        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryPaymentPaidOrder";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

//        Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoPaid);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoPaid);
        variableDict.put("merchantId", merchantId);

        Thread.sleep(5000); // Wait for the payment to be processed
        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentCanceledOrder() throws IOException {
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryPaymentCanceledOrder";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoCancel);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoCancel);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentInvalidFieldFormat() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "QueryPaymentInvalidFormat";

        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        customHeaders.put(
                DanaHeader.X_TIMESTAMP,
                "TIMESTAMP");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        QueryPaymentResponse response = apiWithCustomHeader.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentInvalidMandatoryField() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "QueryPaymentInvalidMandatoryField";

        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        customHeaders.put(
                DanaHeader.X_TIMESTAMP,
                "");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        QueryPaymentResponse response = apiWithCustomHeader.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentUnauthorized() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "QueryPaymentUnauthorized";

        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        customHeaders.put(
                DanaHeader.X_SIGNATURE,
                "testing");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        QueryPaymentResponse response = apiWithCustomHeader.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentTransactionNotFound() throws IOException {
        String caseName = "QueryPaymentTransactionNotFound";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        requestData.setMerchantId(merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testQueryPaymentGeneralError() throws IOException {
        String caseName = "QueryPaymentGeneralError";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Use the reference number for a canceled order
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
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

    public static String cancelOrder() {
        List<String> tempDataOrder = createOrder();

        CancelOrderRequest requestDataCancel = TestUtil.getRequest(
                jsonPathFile,
                "CancelOrder",
                "CreateOrderApi",
                CancelOrderRequest.class);

        requestDataCancel.setOriginalPartnerReferenceNo(tempDataOrder.get(0));
        requestDataCancel.setMerchantId(merchantId);

        CancelOrderResponse responseCancel = api.cancelOrder(requestDataCancel);
        Assertions.assertTrue(responseCancel.getResponseCode().contains("200"));
        return tempDataOrder.get(0);
    }

    public static String payOrder(String phoneNumber, String pin) throws InterruptedException {
        List<String> dataOrder = createOrder();
        PaymentPGUtil.payOrder(phoneNumber,pin,dataOrder.get(1));
        Thread.sleep(5000); // Wait for the payment to be processed
        return dataOrder.get(0);
    }
}