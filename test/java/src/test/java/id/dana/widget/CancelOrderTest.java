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
import id.dana.util.RetryTestUtil;
import id.dana.widget.v1.model.*;
import id.dana.paymentgateway.v1.model.CreateOrderByRedirectRequest;
import id.dana.paymentgateway.v1.model.CreateOrderResponse;
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
import org.junit.jupiter.api.*;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.File;
import java.io.IOException;
import java.util.*;

public class CancelOrderTest {
    private static String jsonPathFile = CancelOrderTest.class.getResource("/request/components/Widget.json")
            .getPath();
    private final String titleCase = "CancelOrder";
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    private static String userPin = "123321";
    private static String userPhone = "0811742234";
    private static WidgetApi widgetApi;
    private static String partnerReferenceNoInit,partnerReferenceNoRefunded;

    @BeforeAll
    static void setUp() throws InterruptedException {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        widgetApi = Dana.getInstance().getWidgetApi();

        partnerReferenceNoInit = String.valueOf(UUID.randomUUID());
    }

    @Test
    void testCancelOrderValid() throws IOException {
        String caseName = "CancelOrderValidScenario";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderWithUserStatusAbnormal() throws IOException {
        String caseName = "CancelOrderFailUserStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderWithMerchantStatusAbnormal() throws IOException {
        String caseName = "CancelOrderFailMerchantStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderInvalidMandatoryField() throws IOException {
        String caseName = "CancelOrderFailMissingParameter";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @RetryTestUtil.Retry
    void testCancelOrderTransactionNotFound() throws IOException {
        String caseName = "CancelOrderFailOrderNotExist";
        String partnerReferenceNo = UUID.randomUUID().toString();
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNo);
        requestData.setOriginalReferenceNo(partnerReferenceNo);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderFailExceedCancelWindowTime() throws IOException {
        String caseName = "CancelOrderFailExceedCancelWindowTime";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderFailNotAllowedByAgreement() throws IOException {
        String caseName = "CancelOrderFailNotAllowedByAgreement";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @RetryTestUtil.Retry
    void testCancelOrderFailOrderRefunded() throws IOException, InterruptedException {
        String caseName = "CancelOrderFailOrderRefunded";

        partnerReferenceNoRefunded = refundOrder(
                userPhone,
                userPin);
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoRefunded);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderFailAccountStatusAbnormal() throws IOException {
        String caseName = "CancelOrderFailAccountStatusAbnormal";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderFailInsufficientMerchantBalance() throws IOException {
        String caseName = "CancelOrderFailInsufficientMerchantBalance";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testCancelOrderFailTimeout() throws IOException {
        String caseName = "CancelOrderFailTimeout";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);

        requestData.setMerchantId(merchantId);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    public static List<String> createPayment(String originOrder) {
        List<String> dataOrder = new ArrayList<>();

        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, "Payment",
                originOrder, WidgetPaymentRequest.class);

        // Assign unique reference and merchant ID
        String partnerReferenceNo = UUID.randomUUID().toString();
        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNo);

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        Assertions.assertTrue(response.getResponseCode().contains("200"),
                "Response code is not 200, actual: " + response.getResponseCode());

        //Index 0 is partnerReferenceNo
        dataOrder.add(partnerReferenceNo);
        //Index 1 is the web redirect URL
        dataOrder.add(response.getWebRedirectUrl());

        return dataOrder;
    }

    public static String refundOrder(
            String phoneNumber,
            String pin) throws InterruptedException {

        String partnerReferenceNo = payOrder(phoneNumber, pin);

        RefundOrderRequest requestRefund = TestUtil.getRequest(jsonPathFile, "RefundOrder", "RefundOrderValidScenario",
                RefundOrderRequest.class);

        requestRefund.setOriginalPartnerReferenceNo(partnerReferenceNo);
        requestRefund.setPartnerRefundNo(partnerReferenceNo);
        requestRefund.setMerchantId(merchantId);

        RefundOrderResponse responseRefund = widgetApi.refundOrder(requestRefund);
        Assertions.assertTrue(responseRefund.getResponseCode().contains("200"));
        return partnerReferenceNo;
    }

    public static String payOrder(String phoneNumber, String pin) throws InterruptedException {
        List<String> dataOrder = createPayment("PaymentSuccess");
        PaymentWidgetUtil.payOrder(phoneNumber,pin,dataOrder.get(1));
        Thread.sleep(5000); // Wait for the payment to be processed
        return dataOrder.get(0);
    }
}
