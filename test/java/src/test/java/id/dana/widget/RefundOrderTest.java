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
import id.dana.paymentgateway.v1.model.CreateOrderByRedirectRequest;
import id.dana.paymentgateway.v1.model.CreateOrderResponse;
import id.dana.widget.v1.model.*;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
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
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class RefundOrderTest {
    private static String jsonPathFile = RefundOrderTest.class.getResource("/request/components/Widget.json")
            .getPath();
    private static final Logger log = LoggerFactory.getLogger(RefundOrderTest.class);

    private final String titleCase = "RefundOrder";
    private final String merchantId = "216620010016033632482";
    private static String userPin = "123321";
    private static String userPhone = "0811742234";
    private WidgetApi widgetApi;
    private static String partnerReferenceNoInit;

    @BeforeEach
    void setUp() {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        widgetApi = Dana.getInstance().getWidgetApi();

        List<String> dataOrder = PaymentWidgetUtil.createPayment("PaymentSuccess");
        partnerReferenceNoInit = dataOrder.get(0);
    }

    @Test
    void testRefundOrderValid() throws IOException {
        String caseName = "RefundOrderValidScenario";

        String partnerReferenceNo = PaymentWidgetUtil.payOrderWithDana(
                userPhone,
                userPin,
                "PaymentSuccess");
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNo);
        requestData.setPartnerRefundNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = widgetApi.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundFailDuplicateRequest() throws IOException {
        String caseName = "RefundFailDuplicateRequest";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        widgetApi.refundOrder(requestData);
        RefundOrderResponse response = widgetApi.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderNotPaid() throws IOException {
        String caseName = "RefundFailOrderNotPaid";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = widgetApi.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundFailParameterIllegal() throws IOException {
        String caseName = "RefundFailParameterIllegal";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = widgetApi.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundFailMandatoryParameterInvalid() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "RefundFailMandatoryParameterInvalid";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
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
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        RefundOrderResponse response = apiWithCustomHeader.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testRefundFailOrderNotExist() throws IOException {
        String caseName = "RefundFailOrderNotExist";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = widgetApi.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundFailMerchantStatusAbnormal() throws IOException {
        String caseName = "RefundFailMerchantStatusAbnormal";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = widgetApi.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundFailTimeout() throws IOException {
        String caseName = "RefundFailTimeout";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = widgetApi.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundFailIdempotent() throws InterruptedException {
        String caseName = "RefundIdempotent";
        int numberOfThreads = 10;
        ExecutorService executor = Executors.newFixedThreadPool(numberOfThreads);
        CountDownLatch latch = new CountDownLatch(numberOfThreads);
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        for (int i = 0; i < numberOfThreads; i++) {
            executor.submit(() -> {
                try {
                    RefundOrderResponse response = widgetApi.refundOrder(requestData);
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
