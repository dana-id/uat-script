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
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class QueryOrderTest {
    private static final Logger log = LoggerFactory.getLogger(QueryOrderTest.class);
    private static final String titleCase = "QueryOrder";
    private static final String jsonPathFile = QueryOrderTest.class.getResource("/request/components/Widget.json")
            .getPath();

    private final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    private WidgetApi widgetApi;
    private PaymentGatewayApi paymentGatewayApi;
    private static String partnerReferenceNoPaid,partnerReferenceNoCancel,partnerReferenceNoInit;

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
        paymentGatewayApi = Dana.getInstance().getPaymentGatewayApi();
    }

    @Test
    void testQueryOrderSuccessInitiated() throws IOException {
        // Create an order with an initial status
        createOrder("INIT");

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
    void testQueryOrderSuccessPaid() throws IOException {
        // Create an order with a paid status
        createOrder("PAID");

        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryOrderSuccessPaid";
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
        // Create an order with a paid status
        createOrder("PAID");

        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryOrderSuccessPaying";
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
    void testQueryOrderSuccessCancelled() throws IOException {
        // Create an order with a cancelled status
        createOrder("CANCEL");

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
    void testQueryOrderFailInvalidMandatoryField() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "QueryOrderFailInvalidMandatoryField";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

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

        QueryPaymentResponse response = apiWithCustomHeader.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryOrderFailUnauthorized() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "QueryOrderFailUnauthorized";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
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
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        QueryPaymentResponse response = apiWithCustomHeader.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryOrderNotFound() throws IOException {
        String caseName = "QueryOrderNotFound";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Use the reference number for a canceled order
        requestData.setMerchantId(merchantId);

        QueryPaymentResponse response = widgetApi.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
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

    private void createOrder(String status){
        String createOrderJsonPathFile = CreateOrderTest.class.getResource(
                        "/request/components/PaymentGateway.json")
                .getPath();
        String createOrderCase = "CreateOrder";
        switch (status) {
            case "PAID":
                // Logic to create a successful order
                String caseOrder = "CreateOrderNetworkPayPgOtherWallet";
                CreateOrderByRedirectRequest requestDataPaid = TestUtil.getRequest(createOrderJsonPathFile, createOrderCase, caseOrder,
                        CreateOrderByRedirectRequest.class);

                partnerReferenceNoPaid = UUID.randomUUID().toString();
                requestDataPaid.setPartnerReferenceNo(partnerReferenceNoPaid);
                requestDataPaid.setMerchantId(merchantId);

                CreateOrderResponse responsePaid = paymentGatewayApi.createOrder(requestDataPaid);
                Assertions.assertTrue(responsePaid.getResponseCode().contains("2005400"));
                break;
            case "CANCEL":
                // Logic to create a cancel order
                CreateOrderByRedirectRequest requestDataOrder = TestUtil.getRequest(createOrderJsonPathFile, "CreateOrder", "CreateOrderRedirect",
                        CreateOrderByRedirectRequest.class);
                CancelOrderRequest requestDataCancel = TestUtil.getRequest(jsonPathFile, "CancelOrder", "CancelOrderValidScenario",
                        CancelOrderRequest.class);

                // Assign unique reference and merchant ID
                partnerReferenceNoCancel = UUID.randomUUID().toString();
                requestDataOrder.setPartnerReferenceNo(partnerReferenceNoCancel);
                requestDataOrder.setMerchantId(merchantId);
                requestDataCancel.setOriginalPartnerReferenceNo(partnerReferenceNoCancel);
                requestDataCancel.setMerchantId(merchantId);

                // Hit API to create an order first and then cancel it
                CreateOrderResponse responseOrderInit = paymentGatewayApi.createOrder(requestDataOrder);
                CancelOrderResponse responseCancel = widgetApi.cancelOrder(requestDataCancel);
                Assertions.assertTrue(responseOrderInit.getResponseCode().contains("200"));
                Assertions.assertTrue(responseCancel.getResponseCode().contains("200"));
                break;
            case "INIT":
                // Logic to create a pending order
                String caseOrderInit = "CreateOrderRedirect";
                CreateOrderByRedirectRequest requestDataInit = TestUtil.getRequest(createOrderJsonPathFile, createOrderCase, caseOrderInit,
                        CreateOrderByRedirectRequest.class);

                partnerReferenceNoInit = UUID.randomUUID().toString();
                requestDataInit.setPartnerReferenceNo(partnerReferenceNoInit);
                requestDataInit.setMerchantId(merchantId);

                CreateOrderResponse responseInit = paymentGatewayApi.createOrder(requestDataInit);
                Assertions.assertTrue(responseInit.getResponseCode().contains("2005400"));
                break;
            default:
                throw new IllegalArgumentException("Unknown status: " + status);
        }
    }

    private Response customHeaderQueryOrder(Map<String, String> headers) {
        String baseUrl = "https://api.sandbox.dana.id";
        String path = "/payment-gateway/v1.0/debit/status.htm";
        RequestSpecBuilder builder = new RequestSpecBuilder();
        JsonPath jsonPath = JsonPath.from(
                new File("src/test/resources/request/components/Widget.json"));
        Map<String, Object> request = jsonPath.get("QueryOrder.QueryOrderSuccessPaid.request");

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
