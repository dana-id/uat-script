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
import java.util.Map;
import java.util.UUID;
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
    void setUp() {
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

        // Create a pending order before running the test
        createOrder();
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
    @Disabled
    void testPaymentFailGeneralError() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "PaymentFailGeneralError";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        customHeaders.put(
                DanaHeader.X_SIGNATURE,
                "85be817c55b2c135157c7e89f52499e04a986e8c862561b19a5");
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
    void testPaymentFailTransactionNotPermitted() throws IOException {
        String caseName = "PaymentFailTransactionNotPermitted";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testPaymentFailMerchantNotExistOrStatusAbnormal() throws IOException {
        String caseName = "PaymentFailMerchantNotExistOrStatusAbnormal";
        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                WidgetPaymentRequest.class);

        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testPaymentFailInconsistentRequest() throws IOException {
        String caseNameFirst = "PaymentFailInconsistentRequest";
        String caseNameSecond = "PaymentSuccess";
        WidgetPaymentRequest requestDataFirst = TestUtil.getRequest(jsonPathFile, titleCase, caseNameFirst,
                WidgetPaymentRequest.class);

        WidgetPaymentRequest requestDataSecond = TestUtil.getRequest(jsonPathFile, titleCase, caseNameSecond,
                WidgetPaymentRequest.class);

        requestDataFirst.setPartnerReferenceNo(partnerReferenceNo);
        requestDataFirst.setMerchantId(merchantId);
        requestDataSecond.setPartnerReferenceNo(partnerReferenceNo);
        requestDataSecond.setMerchantId(merchantId);

        widgetApi.widgetPayment(requestDataFirst);
        WidgetPaymentResponse response = widgetApi.widgetPayment(requestDataSecond);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseNameFirst, response, null);
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
    void testPaymentFailExceedsTransactionAmountLimit() throws IOException {
        String caseName = "PaymentFailExceedsTransactionAmountLimit";
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

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
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

    private Response customHeaderPaymentOrder(Map<String, String> headers) {
        String baseUrl = "https://api.sandbox.dana.id";
        String path = "/rest/redirection/v1.0/debit/payment-host-to-host";
        RequestSpecBuilder builder = new RequestSpecBuilder();
        JsonPath jsonPath = JsonPath.from(
                new File("src/test/resources/request/components/Widget.json"));
        Map<String, Object> request = jsonPath.get("Payment.PaymentSuccess.request");

        log.info("Request: {}", request);

        RequestSpecification requestSpecification = builder.setBody(
                        request, ObjectMapperType.JACKSON_2)
                .setContentType(ContentType.JSON)
                .addHeaders(headers)
                .build();

        Response response = RestAssured.given(requestSpecification)
                .relaxedHTTPSValidation()
                .when()
                .request("POST", baseUrl + path)
                .then()
                .extract()
                .response();

        return response;
    }
}
