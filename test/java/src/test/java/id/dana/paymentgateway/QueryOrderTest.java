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
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
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
import java.util.List;
import java.util.Map;
import java.util.UUID;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import static org.junit.jupiter.api.Assertions.fail;

class QueryOrderTest {
    private static final Logger log = LoggerFactory.getLogger(QueryOrderTest.class);
    private static final String jsonPathFile = QueryOrderTest.class.getResource("/request/components/PaymentGateway.json")
            .getPath();
    private static final String titleCase = "QueryPayment";
    private static String userPin = "123321";
    private static String userPhone = "0811742234";

    private final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");

    private PaymentGatewayApi api;
    private static String partnerReferenceNoInit;

    @BeforeEach
    void setUp() throws IOException {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        api = Dana.getInstance().getPaymentGatewayApi();

//        Create order with status "INIT"
        List<String> dataOrder = OrderPGUtil.createOrder("CreateOrderRedirect");
        partnerReferenceNoInit = dataOrder.get(0);
    }

    @Test
    void testQueryPaymentCreatedOrder() throws IOException {
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryPaymentCreatedOrder";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

//        Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoInit);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentPaidOrder() throws IOException, InterruptedException {
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryPaymentPaidOrder";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

//        Create paid order
        String partnerReferenceNoPaid = OrderPGUtil.payOrderWithDana(
                userPhone,
                userPin,
                "CreateOrderRedirect");

//        Assign unique reference and merchant ID
        requestData.setOriginalPartnerReferenceNo(partnerReferenceNoPaid);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", partnerReferenceNoPaid);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentCanceledOrder() throws IOException {
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "QueryPaymentCanceledOrder";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

//        Create order with status "CANCEL"
        String dataOrder = OrderPGUtil.cancelOrder("CreateOrderRedirect");

        requestData.setOriginalPartnerReferenceNo(dataOrder);
        requestData.setMerchantId(merchantId);

        variableDict.put("partnerReferenceNo", dataOrder);
        variableDict.put("merchantId", merchantId);

        QueryPaymentResponse response = api.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentInvalidFieldFormat() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "QueryPaymentInvalidFormat";

        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        customHeaders.put(
                DanaHeader.X_TIMESTAMP,
                "TIMESTAMP");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        QueryPaymentResponse response = apiWithCustomHeader.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentInvalidMandatoryField() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "QueryPaymentInvalidMandatoryField";

        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        customHeaders.put(
                DanaHeader.X_TIMESTAMP,
                "");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        QueryPaymentResponse response = apiWithCustomHeader.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentUnauthorized() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "QueryPaymentUnauthorized";

        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

        customHeaders.put(
                DanaHeader.X_SIGNATURE,
                "testing");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        QueryPaymentResponse response = apiWithCustomHeader.queryPayment(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testQueryPaymentTransactionNotFound() throws IOException {
        String caseName = "QueryPaymentTransactionNotFound";
        QueryPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                QueryPaymentRequest.class);

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
}