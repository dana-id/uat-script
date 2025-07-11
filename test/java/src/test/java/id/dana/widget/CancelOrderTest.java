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
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Disabled;
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
    private final String titleCase = "CancelOrder";
    private final String merchantId = "216620010016033632482";

    private WidgetApi widgetApi;
    private static String partnerReferenceNoPaid,partnerReferenceNoRefund,partnerReferenceNoInit;

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
        createPayment("INIT");
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
    @Disabled
    void testCancelOrderTransactionNotFound() throws IOException {
        String caseName = "CancelOrderFailOrderNotExist";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);
        requestData.setMerchantId(merchantId);
        System.out.println("Request Data: " + requestData);
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
    void testCancelOrderFailOrderRefunded() throws IOException {
        createPayment("REFUND");
        String caseName = "CancelOrderFailOrderRefunded";
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
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "CancelOrderFailInvalidSignature";
        CancelOrderRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                CancelOrderRequest.class);

        requestData.setMerchantId(merchantId);
        customHeaders.put(
                DanaHeader.X_SIGNATURE,
                "test");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        WidgetApi apiWithCustomHeader = new WidgetApi(client);
        customHeaders.keySet();

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNoInit);

        CancelOrderResponse response = apiWithCustomHeader.cancelOrder(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
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

    private void createPayment(String status){
        String createPaymentJsonPathFile = PaymentTest.class.getResource(
                        "/request/components/Widget.json")
                .getPath();
        String titleCasePayment = "Payment";
        String createPaymentCase = "PaymentSuccess";
        String titleRefundOrder = "RefundOrder";
        String refundOrderCase= "RefundOrderValidScenario";
        switch (status) {
            case "PAID":
                WidgetPaymentRequest requestDataPaid = TestUtil.getRequest(createPaymentJsonPathFile, titleCasePayment, createPaymentCase,
                        WidgetPaymentRequest.class);

                partnerReferenceNoPaid = UUID.randomUUID().toString();
                requestDataPaid.setPartnerReferenceNo(partnerReferenceNoPaid);
                requestDataPaid.setMerchantId(merchantId);

                WidgetPaymentResponse responsePaid = widgetApi.widgetPayment(requestDataPaid);
                System.out.println(responsePaid.getResponseMessage());
                Assertions.assertTrue(responsePaid.getResponseCode().contains("2005400"));
                break;
            case "REFUND":
                WidgetPaymentRequest requestDataCancel = TestUtil.getRequest(createPaymentJsonPathFile, titleCasePayment, createPaymentCase,
                        WidgetPaymentRequest.class);

                RefundOrderRequest requestDataRefund = TestUtil.getRequest(createPaymentJsonPathFile, titleRefundOrder, refundOrderCase,
                        RefundOrderRequest.class);

                partnerReferenceNoRefund = UUID.randomUUID().toString();
                requestDataCancel.setPartnerReferenceNo(partnerReferenceNoRefund);
                requestDataCancel.setMerchantId(merchantId);
                requestDataRefund.setOriginalPartnerReferenceNo(partnerReferenceNoRefund);
                requestDataRefund.setPartnerRefundNo(partnerReferenceNoRefund);
                requestDataRefund.setMerchantId(merchantId);

                WidgetPaymentResponse responseCancel = widgetApi.widgetPayment(requestDataCancel);
                System.out.println("Response Cancel: " + responseCancel);
                RefundOrderResponse responseRefund = widgetApi.refundOrder(requestDataRefund);
                System.out.println("Response Refund: " + responseRefund);
                Assertions.assertTrue(responseCancel.getResponseCode().contains("2005400"));
                Assertions.assertTrue(responseRefund.getResponseCode().contains("2005400"));
                break;
            case "INIT":
                WidgetPaymentRequest requestDataInit = TestUtil.getRequest(createPaymentJsonPathFile, titleCasePayment, createPaymentCase,
                        WidgetPaymentRequest.class);

                partnerReferenceNoInit = UUID.randomUUID().toString();
                requestDataInit.setPartnerReferenceNo(partnerReferenceNoInit);
                requestDataInit.setMerchantId(merchantId);

                WidgetPaymentResponse responseInit = widgetApi.widgetPayment(requestDataInit);
                Assertions.assertTrue(responseInit.getResponseCode().contains("200"));
                break;
            default:
                throw new IllegalArgumentException("Unknown status: " + status);
        }
    }
}
