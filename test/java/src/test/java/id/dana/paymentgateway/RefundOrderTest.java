package id.dana.paymentgateway;

import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
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

import id.dana.util.ConfigUtil;
import io.restassured.response.Response;
import io.restassured.specification.RequestSpecification;
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

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderInProgress() throws IOException {
        String caseName = "RefundOrderInProgress";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        System.out.println("requestData: " + requestData);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderNotAllowed() throws IOException {
        String caseName = "RefundOrderNotAllowed";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderDueToExceedRefundWindowTime() throws IOException {
        String caseName = "RefundOrderDueToExceedRefundWindowTime";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
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

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderNotPaid() throws IOException {
        String caseName = "RefundOrderNotPaid";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderIllegalParameter() throws IOException {
        String caseName = "RefundOrderIllegalParameter";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderInvalidMandatoryField() throws IOException {
        Map<String, String> headers = new HashMap<>();
        String caseName = "RefundOrderInvalidMandatoryParameter";

        headers.put("X-TIMESTAMP", "");

        Response response = customHeaderRefundOrder(headers);
        TestUtil.assertResponse(jsonPathFile, response, titleCase + "." + caseName);
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

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderInsufficientFunds() throws IOException {
        String caseName = "RefundOrderInsufficientFunds";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderUnauthorized() {
        Map<String, String> headers = new HashMap<>();
        String caseName = "RefundOrderUnauthorized";

        headers.put("X-SIGNATURE", "testing");
        headers.put("X-TIMESTAMP", "2023-08-31T22:27:48+00:00");
        headers.put("X-EXTERNAL-ID", ConfigUtil.getConfig("X_PARTNER_ID", ""));
        headers.put("X-PARTNER-ID", ConfigUtil.getConfig("X_PARTNER_ID", ""));
        headers.put("CHANNEL-ID", ConfigUtil.getConfig("CHANNEL_ID", ""));

        Response response = customHeaderRefundOrder(headers);
        TestUtil.assertResponse(jsonPathFile, response, titleCase + "." + caseName);
    }

    @Test
    void testRefundOrderMerchantStatusAbnormal() throws IOException {
        String caseName = "RefundOrderMerchantStatusAbnormal";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testRefundOrderTimeout() throws IOException {
        String caseName = "RefundOrderTimeout";
        RefundOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                RefundOrderRequest.class);
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setPartnerRefundNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        RefundOrderResponse response = api.refundOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
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

    private Response customHeaderRefundOrder(Map<String, String> headers) {
        String baseUrl = "https://api.sandbox.dana.id";
        String path = "/payment-gateway/v1.0/debit/cancel.htm";
        RequestSpecBuilder builder = new RequestSpecBuilder();
        JsonPath jsonPath = JsonPath.from(
                new File("src/test/resources/request/components/PaymentGateway.json"));
        Map<String, Object> request = jsonPath.get("RefundOrder.RefundOrderValidScenario.request");

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