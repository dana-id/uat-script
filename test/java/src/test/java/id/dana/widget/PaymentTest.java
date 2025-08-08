package id.dana.widget;

import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.CreateOrderTest;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.paymentgateway.v1.model.CreateOrderByApiRequest;
import id.dana.paymentgateway.v1.model.CreateOrderByRedirectRequest;
import id.dana.paymentgateway.v1.model.CreateOrderResponse;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.Money;
import id.dana.widget.v1.model.RefundOrderResponse;
import id.dana.widget.v1.model.WidgetPaymentRequest;
import id.dana.widget.v1.model.WidgetPaymentResponse;
import io.restassured.RestAssured;
import io.restassured.builder.RequestSpecBuilder;
import io.restassured.http.ContentType;
import io.restassured.mapper.ObjectMapperType;
import io.restassured.path.json.JsonPath;
import io.restassured.response.Response;
import io.restassured.specification.RequestSpecification;
import okhttp3.OkHttpClient;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class PaymentTest {
    private static final Logger log = LoggerFactory.getLogger(PaymentTest.class);
    private static final String titleCase = "Payment";
    private static final String jsonPathFile = PaymentTest.class.getResource("/request/components/Widget.json")
            .getPath();
    private final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");

    private WidgetApi widgetApi;
    private PaymentGatewayApi paymentGatewayApi;
    private String partnerReferenceNo;

    @BeforeEach
    void setUp() throws IOException {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        // Generate a unique partner reference number for each test run
        partnerReferenceNo = UUID.randomUUID().toString();

        widgetApi = Dana.getInstance().getWidgetApi();
        paymentGatewayApi = Dana.getInstance().getPaymentGatewayApi();

        List<String> dataOrder = PaymentWidgetUtil.createPayment("PaymentSuccess");
        partnerReferenceNo = dataOrder.get(0);
    }

    @Test
    void testPaymentOrderSuccess() throws IOException {
        String caseName = "PaymentSuccess";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testPaymentOrderInconsistent() throws IOException {
        String caseName = "PaymentFailInconsistentRequest";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        widgetApi.widgetPayment(requestData);
        Money amount = new Money();
        amount.setCurrency("IDR");
        amount.setValue("21000.00");
        requestData.setAmount(amount);
        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testPaymentOrderMerchantDoesNotExist() throws IOException {
        String caseName = "PaymentFailMerchantNotExistOrStatusAbnormal";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testPaymentFailInvalidFormat() throws IOException {
        String caseName = "PaymentFailInvalidFormat";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testPaymentFailMissingOrInvalidMandatoryField() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "PaymentFailMissingOrInvalidMandatoryField";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        customHeaders.put(
                DanaHeader.X_TIMESTAMP,
                "");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        WidgetApi apiWithCustomHeader = new WidgetApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNo);

        WidgetPaymentResponse response = apiWithCustomHeader.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testPaymentFailInvalidSignature() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "PaymentFailInvalidSignature";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        customHeaders.put(
                DanaHeader.X_SIGNATURE,
                "testing");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        WidgetApi apiWithCustomHeader = new WidgetApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNo);

        WidgetPaymentResponse response = apiWithCustomHeader.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testPaymentFailInternalServerError() throws IOException {
        String caseName = "PaymentFailInternalServerError";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testPaymentFailTimeout() throws IOException {
        String caseName = "PaymentFailTimeout";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        try {
            WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
            TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
        } catch (Exception e) {
            Assertions.assertEquals(e.getMessage(), "Network error");
        }
    }

    private void createOrder(){
        String createOrderJsonPathFile = CreateOrderTest.class.getResource(
                "/request/components/PaymentGateway.json")
                .getPath();
        String createOrderCase = "CreateOrder";
        // Logic to create a pending order
        String caseOrderInit = "CreateOrderRedirect";
        CreateOrderByRedirectRequest requestDataInit = TestUtil.getRequest(createOrderJsonPathFile, createOrderCase, caseOrderInit,
                CreateOrderByRedirectRequest.class);

        partnerReferenceNo = UUID.randomUUID().toString();
        requestDataInit.setPartnerReferenceNo(partnerReferenceNo);
        requestDataInit.setMerchantId(merchantId);

        CreateOrderResponse responseInit = paymentGatewayApi.createOrder(requestDataInit);
        log.info("Create Order Response: " + responseInit.getResponseCode());
        Assertions.assertTrue(responseInit.getResponseCode().contains("200"));
    }

    @Test
    void testPaymentFailIdempotent() throws InterruptedException {
        String caseName = "PaymentFailIdempotent";

        int numberOfThreads = 10;
        ExecutorService executor = Executors.newFixedThreadPool(numberOfThreads);
        CountDownLatch latch = new CountDownLatch(numberOfThreads);

        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        for (int i = 0; i < numberOfThreads; i++) {
            executor.submit(() -> {
                try {
                    WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
                    TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
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
}
