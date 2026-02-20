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
import id.dana.util.RetryTestUtil;
import id.dana.util.TestUtil;

import java.io.IOException;
import java.security.SecureRandom;
import java.util.*;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import id.dana.util.ConfigUtil;
import okhttp3.OkHttpClient;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;

class RefundOrderTest {
    private static String jsonPathFile = RefundOrderTest.class.getResource("/request/components/PaymentGateway.json")
            .getPath();
    private static String userPin = "181818";
    private static String userPhone = "083811223355";
    private final String titleCase = "RefundOrder";
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    private static PaymentGatewayApi api;
    private static String
            partnerReferenceNoPaid,
            partnerReferenceNoInit;

    @BeforeAll
    static void setUp() {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        api = Dana.getInstance().getPaymentGatewayApi();

        List<String> dataOrder = createOrder();
        partnerReferenceNoInit = dataOrder.get(0);
    }

    @Test
    @RetryTestUtil.Retry
    void testRefundOrderValid() throws IOException {
        partnerReferenceNoPaid = payOrder(userPhone, userPin);
        String caseName = "RefundOrderValidScenario";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            RefundOrderRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoPaid);
        requestData.setPartnerRefundNo(partnerReferenceNoPaid);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoPaid);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderInProgress() throws IOException {
        String caseName = "RefundOrderInProgress";
        Map<String, Object> variableDict = new HashMap<>();
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);
        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderNotAllowed() throws IOException {
        String caseName = "RefundOrderNotAllowed";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderNotPaid() throws IOException {
        String caseName = "RefundOrderNotPaid";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderIllegalParameter() throws IOException {
        String caseName = "RefundOrderIllegalParameter";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderInvalidMandatoryField() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "RefundOrderInvalidMandatoryParameter";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);
        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        customHeaders.put(
                DanaHeader.X_SIGNATURE,
                "");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        RefundOrderResponse response = apiWithCustomHeader.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderNotExist() throws IOException {
        String orderNotExist = "f77466d6-1825-4091";
        String caseName = "RefundOrderInvalidBill";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(orderNotExist);
        requestData.setPartnerRefundNo(orderNotExist);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderInsufficientFunds() throws IOException {
        String caseName = "RefundOrderInsufficientFunds";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderUnauthorized() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "RefundOrderUnauthorized";

        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        customHeaders.put(
                DanaHeader.X_SIGNATURE,
                "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        RefundOrderResponse response = apiWithCustomHeader.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderMerchantStatusAbnormal() throws IOException {
        String caseName = "RefundOrderMerchantStatusAbnormal";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderTimeout() throws IOException {
        String caseName = "RefundOrderTimeout";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderDuplicateRequest() throws IOException, InterruptedException {
        partnerReferenceNoPaid = payOrder(userPhone, userPin);
        String caseName = "RefundOrderDuplicateRequest";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        String partnerReferenceNo = partnerReferenceNoPaid;
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNo);
        requestData.setPartnerRefundNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNo);

        RefundOrderResponse response1 = api.refundOrder(requestData);
        Money money = new Money();
        money.setCurrency("IDR");
        money.setValue("10000.00");
        requestData.setRefundAmount(money);
        Thread.sleep(5000);
        RefundOrderResponse response2 = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response2, variableDict);
    }

    @Test
    void testRefundOrderIndempotent() throws InterruptedException {
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "RefundOrderIdempotent";
        int numberOfThreads = 10;
        ExecutorService executor = Executors.newFixedThreadPool(numberOfThreads);
        CountDownLatch latch = new CountDownLatch(numberOfThreads);

        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        for (int i = 0; i < numberOfThreads; i++) {
            executor.submit(() -> {
                try {
                    RefundOrderResponse response = api.refundOrder(requestData);
                    TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
                    System.out.println("Thread: " + Thread.currentThread().getId()
                            + " - Status: " + response.getResponseCode());
                } catch (IOException e) {
                    throw new RuntimeException(e);
                } finally {
                    latch.countDown();
                }
            });
        }
        // Wait for all threads to complete
        latch.await();
        executor.shutdown();
    }

    public static List<String> createOrder() {
        List<String> dataOrder = new ArrayList<>();

        CreateOrderByApiRequest requestData = PaymentPGUtil.getCreateOrderApiRequest(
                jsonPathFile,
                "CreateOrder",
                "CreateOrderApi");

        // Assign unique reference and merchant ID
        String partnerReferenceNo = UUID.randomUUID().toString();
        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);
        requestData.setValidUpTo(PaymentPGUtil.generateDateWithOffset(30));

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

    public static String payOrder(String phoneNumber, String pin) {
        List<String> dataOrder = createOrder();
        PaymentPGUtil.payOrder(phoneNumber,pin,dataOrder.get(1));
        return dataOrder.get(0);
    }
}