package id.dana.widget;

import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.CreateOrderTest;
import id.dana.paymentgateway.PaymentPGUtil;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.paymentgateway.v1.model.CreateOrderByRedirectRequest;
import id.dana.paymentgateway.v1.model.CreateOrderResponse;
import id.dana.widget.v1.api.*;
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
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import java.io.File;
import java.io.IOException;
import java.util.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class QueryOrderTest {
    private static final Logger log = LoggerFactory.getLogger(QueryOrderTest.class);
    private static final String titleCase = "QueryOrder";
    private static final String jsonPathFile = QueryOrderTest.class.getResource("/request/components/Widget.json")
            .getPath();
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    private static String userPin = "123321";
    private static String userPhone = "0811742234";
    private static WidgetApi widgetApi;
    private static String
            partnerReferenceNoPaid,
            partnerReferenceNoCancel,
            partnerReferenceNoInit,
            partnerReferenceNoPaying;

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

        List<String> dataOrderInit = createPayment("PaymentSuccess");
        List<String> dataOrderPaying = createPayment("PaymentPaying");
        partnerReferenceNoInit = dataOrderInit.get(0);
        partnerReferenceNoPaying = dataOrderPaying.get(0);
        partnerReferenceNoCancel = cancelOrder();
    }

    @Test
    void testQueryOrderSuccessInitiated() throws IOException {
        // Create an order with an initial status
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryOrderSuccessInitiated";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = widgetApi.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryOrderSuccessPaid() throws IOException, InterruptedException {
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryOrderSuccessPaid";

        partnerReferenceNoPaid = payOrder(
                userPhone,
                userPin);

        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoPaid);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoPaid);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = widgetApi.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryOrderSuccessPaying() throws IOException {
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryOrderSuccessPaying";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoPaying);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoPaying);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = widgetApi.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryOrderSuccessCancelled() throws IOException {
        Map<String, Object> variableDict = new HashMap<>();
        // Create an order with a cancelled status
        String caseName = "QueryOrderSuccessCancelled";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Use the reference number for a canceled order
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoCancel);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoCancel);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = widgetApi.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryOrderFailInvalidField() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "QueryOrderFailInvalidField";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        customHeaders.put(
                DanaHeader.X_TIMESTAMP,
                "X_TIMESTAMP");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        WidgetApi apiWithCustomHeader = new WidgetApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        QueryPaymentResponse response = apiWithCustomHeader.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryOrderFailTransactionNotFound() throws IOException {
        String caseName = "QueryOrderFailTransactionNotFound";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Use the reference number for a canceled order
        requestData.setMerchantId(merchantId);

        QueryPaymentResponse response = widgetApi.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testQueryOrderFailGeneralError() throws IOException {
        String caseName = "QueryOrderFailGeneralError";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Use the reference number for a canceled order
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        QueryPaymentResponse response = widgetApi.queryPayment(requestData);
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

    public static String cancelOrder() {
        List<String> dataOrder = createPayment("PaymentSuccess");
        CancelOrderRequest requestDataCancel = TestUtil.getRequest(
                jsonPathFile,
                "CancelOrder",
                "CancelOrderValidScenario",
                CancelOrderRequest.class);

        requestDataCancel.setOriginalPartnerReferenceNo(dataOrder.get(0));
        requestDataCancel.setMerchantId(merchantId);

        CancelOrderResponse responseCancel = widgetApi.cancelOrder(requestDataCancel);
        Assertions.assertTrue(responseCancel.getResponseCode().contains("200"));
        return dataOrder.get(0);
    }

    public static String payOrder(String phoneNumber, String pin) throws InterruptedException {
        List<String> dataOrder = createPayment("PaymentSuccess");
        PaymentWidgetUtil.payOrder(phoneNumber,pin,dataOrder.get(1));
        Thread.sleep(5000); // Wait for the payment to be processed
        return dataOrder.get(0);
    }
}
