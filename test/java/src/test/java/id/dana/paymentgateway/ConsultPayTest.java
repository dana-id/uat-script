package id.dana.paymentgateway;

import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.merchantmanagement.v1.api.MerchantManagementApi;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.paymentgateway.v1.model.ConsultPayRequest;
import id.dana.paymentgateway.v1.model.ConsultPayResponse;
import id.dana.paymentgateway.v1.model.RefundOrderRequest;
import id.dana.paymentgateway.v1.model.RefundOrderResponse;
import id.dana.util.ConfigUtil;
import id.dana.util.RetryTestUtil;
import id.dana.util.TestUtil;
import okhttp3.OkHttpClient;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.IOException;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;

public class ConsultPayTest {
    private static final Logger log = LoggerFactory.getLogger(CreateOrderTest.class);

    private static final String titleCase = "ConsultPay";
    private static final String jsonPathFile = CreateOrderTest.class.getResource(
            "/request/components/PaymentGateway.json").getPath();
    private static PaymentGatewayApi api;
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    @BeforeAll
    static void setUp() {
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
    @RetryTestUtil.Retry
    void testConsultPayBalancedSuccess() throws IOException {
        String caseName = "ConsultPayBalancedSuccess";
        ConsultPayRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                ConsultPayRequest.class);

        requestData.setMerchantId(merchantId);

        ConsultPayResponse response = api.consultPay(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testConsultPayBalancedInvalidFieldFormat() throws IOException {
        String caseName = "ConsultPayBalancedInvalidFieldFormat";
        ConsultPayRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                ConsultPayRequest.class);

        ConsultPayResponse response = api.consultPay(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testConsultPayBalancedInvalidMandatoryField() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "ConsultPayBalancedInvalidMandatoryField";
        ConsultPayRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                ConsultPayRequest.class);

        requestData.setMerchantId(merchantId);

        customHeaders.put(
                DanaHeader.X_TIMESTAMP,
                "");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        PaymentGatewayApi apiWithCustomHeader = new PaymentGatewayApi(client);

        ConsultPayResponse response = apiWithCustomHeader.consultPay(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }
}
