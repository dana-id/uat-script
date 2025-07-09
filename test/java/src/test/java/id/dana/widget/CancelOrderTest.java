package id.dana.widget;

import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.CreateOrderTest;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.widget.v1.model.CancelOrderRequest;
import id.dana.widget.v1.model.CancelOrderResponse;
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
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;

public class CancelOrderTest {
    private static String jsonPathFile = CancelOrderTest.class.getResource("/request/components/Widget.json")
            .getPath();
    private static final Logger log = LoggerFactory.getLogger(CancelOrderTest.class);

    private final String titleCase = "CancelOrder";
    private final String merchantId = "216620010016033632482";

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
    void testCancelOrderInProgress() throws IOException {
        createOrder("INIT");
        String caseName = "CancelOrderSuccessInProcess";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
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
    void testCancelOrderTransactionNotFound() throws IOException {
        String caseName = "CancelOrderFailOrderNotExist";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
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
    void testCancelOrderInvalidTransactionStatus() throws IOException {
        String caseName = "CancelOrderInvalidTransactionStatus";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
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
    void testCancelOrderFailInvalidSignature() throws IOException {
        Map<String, String> headers = new HashMap<>();
        String caseName = "CancelOrderFailInvalidSignature";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);

        headers.put("X-SIGNATURE", "testing");
        headers.put("X-TIMESTAMP", "2023-08-31T22:27:48+00:00");
        headers.put("X-EXTERNAL-ID", ConfigUtil.getConfig("X_PARTNER_ID", ""));
        headers.put("X-PARTNER-ID", ConfigUtil.getConfig("X_PARTNER_ID", ""));
        headers.put("CHANNEL-ID", ConfigUtil.getConfig("CHANNEL_ID", ""));

        Response response = customHeaderCancelOrderWidget(headers);
        TestUtil.assertResponse(jsonPathFile, response, titleCase + "." + caseName);
    }

    @Test
    void testCancelOrderFailTimeout() throws IOException {
        String caseName = "CancelOrderFailTimeout";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        CancelOrderResponse response = widgetApi.cancelOrder(requestData);
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
                // Logic to create a failed order
                String caseOrderCancel = "CancelOrderValidScenario";
                CreateOrderByRedirectRequest requestDataCancel = TestUtil.getRequest(createOrderJsonPathFile, createOrderCase, caseOrderCancel,
                        CreateOrderByRedirectRequest.class);

                partnerReferenceNoCancel = UUID.randomUUID().toString();
                requestDataCancel.setPartnerReferenceNo(partnerReferenceNoCancel);
                requestDataCancel.setMerchantId(merchantId);

                CreateOrderResponse responseCancel = paymentGatewayApi.createOrder(requestDataCancel);
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
                log.info("Create Order Response: " + responseInit.getResponseCode());
                Assertions.assertTrue(responseInit.getResponseCode().contains("200"));
                break;
            default:
                throw new IllegalArgumentException("Unknown status: " + status);
        }
    }

    private Response customHeaderCancelOrderWidget(Map<String, String> headers) {
        String baseUrl = "https://api.sandbox.dana.id";
        String path = "/payment-gateway/v1.0/debit/cancel.htm";
        RequestSpecBuilder builder = new RequestSpecBuilder();
        JsonPath jsonPath = JsonPath.from(
                new File("src/test/resources/request/components/Widget.json"));
        Map<String, Object> request = jsonPath.get("CancelOrder.CancelOrderSuccessInProcess.request");

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
