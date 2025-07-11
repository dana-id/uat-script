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
import id.dana.util.TestUtil;
import io.restassured.RestAssured;
import io.restassured.builder.RequestSpecBuilder;
import io.restassured.http.ContentType;
import io.restassured.mapper.ObjectMapperType;
import io.restassured.path.json.JsonPath;

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import id.dana.util.ConfigUtil;
import io.restassured.response.Response;
import io.restassured.specification.RequestSpecification;
import okhttp3.OkHttpClient;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class RefundOrderTest {
    private static String jsonPathFile = RefundOrderTest.class.getResource("/request/components/PaymentGateway.json")
            .getPath();
    private static final Logger log = LoggerFactory.getLogger(RefundOrderTest.class);

    private final String titleCase = "RefundOrder";
    private final String merchantId = "216620010016033632482";

    private PaymentGatewayApi api;
    private static String
            partnerReferenceNoPaid,
            partnerReferenceNoCancel,
            partnerReferenceNoInit;

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

    //Must be paid but found INIT ORDER_STATUS_INVALID::pgOrder.status must be PAID, bud found INIT
    @Test
    @Disabled
    void testRefundOrderValid() throws IOException {
        String caseName = "RefundOrderValidScenario";

        createOrder("PAID");
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
    void testRefundOrderDueToExceedRefundWindowTime() throws IOException {
        String caseName = "RefundOrderDueToExceedRefundWindowTime";
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
    void testRefundOrderDueToExceed() throws IOException {
        String caseName = "RefundOrderExceedsTransactionAmountLimit";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        System.out.println("parccscscs " + requestData);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderMultipleRefund() throws IOException {
        // First refund
        String caseName = "RefundOrderMultipleRefund";
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
    @Disabled
    void testRefundOrderNotExist() throws IOException {
        String caseName = "RefundOrderInvalidBill";
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
    void testRefundOrderDuplicateRequest() throws IOException {
        String caseName = "RefundOrderDuplicateRequest";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        Money money = new Money();
        money.setValue("190098.00");
        money.setCurrency("IDR");

        api.refundOrder(requestData);
        requestData.setRefundAmount(money);
        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundOrderIndempotent() throws InterruptedException {
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "RefundOrderIndempotent";
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

    @Test
    void testRefundOrderExceedsTransactionAmountLimit() throws IOException {
        String caseName = "RefundOrderExceedsTransactionAmountLimit";
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

    private void createOrder(String status){
        String createOrderCase = "CreateOrder";
        switch (status) {
            case "PAID":
                // Logic to create a successful order
                String caseOrder = "CreateOrderNetworkPayPgOtherWallet";
                CreateOrderByApiRequest requestDataPaid = TestUtil.getRequest(jsonPathFile, createOrderCase, caseOrder,
                        CreateOrderByApiRequest.class);

                partnerReferenceNoPaid = UUID.randomUUID().toString();
                requestDataPaid.setPartnerReferenceNo(partnerReferenceNoPaid);
                requestDataPaid.setMerchantId(merchantId);

                CreateOrderResponse responsePaid = api.createOrder(requestDataPaid);
                responsePaid.getResponseMessage();
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