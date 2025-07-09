package id.dana.paymentgateway;

import com.fasterxml.jackson.databind.ObjectMapper;
import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.paymentgateway.v1.model.*;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
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

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import static org.junit.jupiter.api.Assertions.fail;

class QueryOrderTest {
    private static final Logger log = LoggerFactory.getLogger(QueryOrderTest.class);
     private static final String titleCase = "QueryPayment";
    private static final String jsonPathFile = QueryOrderTest.class.getResource("/request/components/PaymentGateway.json")
            .getPath();

    private final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");

    private PaymentGatewayApi api;
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

        api = Dana.getInstance().getPaymentGatewayApi();
    }

    @Test
    void testQueryPaymentCreatedOrder() throws IOException {
        // Create an order with an initial status
        createOrder("INIT");

        // Create an order with an initial status
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryPaymentCreatedOrder";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentPaidOrder() throws IOException {
        // Create an order with a paid status
        createOrder("PAID");

        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryPaymentPaidOrder";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoPaid);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoPaid);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentCanceledOrder() throws IOException {
        // Create an order with a cancelled status
        createOrder("CANCEL");

        Map<String, Object> variableDict = new HashMap<>();
        // Create an order with a cancelled status
        String caseName = "QueryPaymentCanceledOrder";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Use the reference number for a canceled order
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoCancel);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoCancel);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentInvalidFieldFormat() {
        Map<String, String> headers = new HashMap<>();
        String caseName = "QueryPaymentInvalidFormat";

        headers.put("X-TIMESTAMP", "TESTTIMESTAMP");

        Response response = customHeaderQueryOrder(headers);
        TestUtil.assertResponse(jsonPathFile, response, titleCase + "." + caseName);
    }

    @Test
    void testQueryPaymentInvalidMandatoryField() {
        Map<String, String> headers = new HashMap<>();
        String caseName = "QueryPaymentInvalidMandatoryField";

        headers.put("X-TIMESTAMP", "");

        Response response = customHeaderQueryOrder(headers);
        TestUtil.assertResponse(jsonPathFile, response, titleCase + "." + caseName);
    }

    @Test
    void testQueryPaymentUnauthorized() {
        Map<String, String> headers = new HashMap<>();
        String caseName = "QueryPaymentUnauthorized";

        headers.put("X-SIGNATURE", "testing");
        headers.put("X-TIMESTAMP", "2023-08-31T22:27:48+00:00");
        headers.put("X-EXTERNAL-ID", ConfigUtil.getConfig("X_PARTNER_ID", ""));
        headers.put("X-PARTNER-ID", ConfigUtil.getConfig("X_PARTNER_ID", ""));
        headers.put("CHANNEL-ID", ConfigUtil.getConfig("CHANNEL_ID", ""));

        Response response = customHeaderQueryOrder(headers);
        TestUtil.assertResponse(jsonPathFile, response, titleCase + "." + caseName);
    }

    @Test
    void testQueryPaymentTransactionNotFound() throws IOException {
        String caseName = "QueryPaymentTransactionNotFound";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        // Use the reference number for a canceled order
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

    private void createOrder(String status){
        String createOrderCase = "CreateOrder";
        switch (status) {
            case "PAID":
                // Logic to create a successful order
                String caseOrder = "CreateOrderNetworkPayPgOtherWallet";
                CreateOrderByRedirectRequest requestDataPaid = TestUtil.getRequest(jsonPathFile, createOrderCase, caseOrder,
                        CreateOrderByRedirectRequest.class);

                partnerReferenceNoPaid = UUID.randomUUID().toString();
                requestDataPaid.setPartnerReferenceNo(partnerReferenceNoPaid);
                requestDataPaid.setMerchantId(merchantId);

                CreateOrderResponse responsePaid = api.createOrder(requestDataPaid);
                Assertions.assertTrue(responsePaid.getResponseCode().contains("2005400"));
                break;
            case "CANCEL":
                // Logic to create a cancel order
                CreateOrderByRedirectRequest requestDataOrder = TestUtil.getRequest(jsonPathFile, "CreateOrder", "CreateOrderRedirect",
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
                CreateOrderResponse responseOrderInit = api.createOrder(requestDataOrder);
                CancelOrderResponse responseCancel = api.cancelOrder(requestDataCancel);
                Assertions.assertTrue(responseOrderInit.getResponseCode().contains("200"));
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
                new File("src/test/resources/request/components/PaymentGateway.json"));
        Map<String, Object> request = jsonPath.get("QueryPayment.QueryPaymentInvalidFormat.request");

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