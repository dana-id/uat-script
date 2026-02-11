package id.dana.widget;

import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.*;
import okhttp3.OkHttpClient;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;

import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.security.SignatureException;
import java.security.spec.InvalidKeySpecException;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

@Disabled
public class BalanceInquiryTest {
    private static String jsonPathFile = RefundOrderTest.class.getResource("/request/components/Widget.json")
            .getPath();
    private final String titleCase = "RefundOrder";
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    private static String userPin = "181818";
    private static String userPhone = "083811223355";
    private static WidgetApi widgetApi;
    private static String accessToken, accessTokenExpiry, accessTokenAbnormalAccount;
    private static String partnerReferenceNoInit;

    @BeforeAll
    static void setUp() throws UnsupportedEncodingException, NoSuchAlgorithmException, InvalidKeySpecException, SignatureException, InvalidKeyException {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        widgetApi = Dana.getInstance().getWidgetApi();

        List<String> dataOrder = PaymentWidgetUtil.createPayment("PaymentSuccess");
        partnerReferenceNoInit = dataOrder.get(0);
//        accessToken = OauthUtil.getAccessToken(
//                userPhone,
//                userPin
//        );
//
//        accessTokenExpiry = OauthUtil.getAccessToken(
//                "0815919191",
//                "631642"
//        );
//
//        accessTokenAbnormalAccount = OauthUtil.getAccessToken(
//                "0855100800",
//                "146838"
//        );
    }

    @Test
    @Disabled
    void testBalanceInquirySuccess() throws IOException {
        String caseName = "BalanceInquirySuccess";
        BalanceInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                BalanceInquiryRequest.class);
        BalanceInquiryRequestAdditionalInfo additionalInfo = new BalanceInquiryRequestAdditionalInfo();
        additionalInfo.accessToken(accessToken);

        // Use the reference number for a canceled order
        requestData.setAdditionalInfo(additionalInfo);
        requestData.setPartnerReferenceNo(partnerReferenceNoInit);

        BalanceInquiryResponse response = widgetApi.balanceInquiry(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testBalanceInquiryFailMissingOrInvalidMandatoryField() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "BalanceInquiryFailMissingOrInvalidMandatoryField";
        BalanceInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                BalanceInquiryRequest.class);
        BalanceInquiryRequestAdditionalInfo additionalInfo = new BalanceInquiryRequestAdditionalInfo();
        additionalInfo.accessToken(accessToken);

        // Use the reference number for a canceled order
        requestData.setAdditionalInfo(additionalInfo);
        requestData.setPartnerReferenceNo(partnerReferenceNoInit);

        customHeaders.put(
                DanaHeader.X_TIMESTAMP,
                "");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        WidgetApi apiWithCustomHeader = new WidgetApi(client);

        BalanceInquiryResponse response = apiWithCustomHeader.balanceInquiry(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @Disabled
    void testBalanceInquiryFailInvalidFormat() throws IOException {
        String caseName = "BalanceInquiryFailInvalidFormat";
        BalanceInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                BalanceInquiryRequest.class);
        BalanceInquiryRequestAdditionalInfo additionalInfo = new BalanceInquiryRequestAdditionalInfo();
        additionalInfo.accessToken(accessToken);

        // Use the reference number for a canceled order
        requestData.setAdditionalInfo(additionalInfo);
        requestData.setPartnerReferenceNo(partnerReferenceNoInit);

        BalanceInquiryResponse response = widgetApi.balanceInquiry(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @Disabled
    void testBalanceInquiryFailTokenExpired() throws IOException {
        String caseName = "BalanceInquiryFailTokenExpired";
        BalanceInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                BalanceInquiryRequest.class);
        BalanceInquiryRequestAdditionalInfo additionalInfo = new BalanceInquiryRequestAdditionalInfo();
        additionalInfo.accessToken(accessTokenExpiry);

        // Use the reference number for a canceled order
        requestData.setAdditionalInfo(additionalInfo);
        requestData.setPartnerReferenceNo(partnerReferenceNoInit);

        BalanceInquiryResponse response = widgetApi.balanceInquiry(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @Disabled
    void testBalanceInquiryFailAccountAbnormal() throws IOException {
        String caseName = "BalanceInquiryFailAccountAbnormal";
        BalanceInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                BalanceInquiryRequest.class);
        BalanceInquiryRequestAdditionalInfo additionalInfo = new BalanceInquiryRequestAdditionalInfo();
        additionalInfo.accessToken(accessTokenAbnormalAccount);

        // Use the reference number for a canceled order
        requestData.setAdditionalInfo(additionalInfo);
        requestData.setPartnerReferenceNo(partnerReferenceNoInit);

        BalanceInquiryResponse response = widgetApi.balanceInquiry(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @Disabled
    void testBalanceInquiryFailAccountInactive() throws IOException {
        String caseName = "BalanceInquiryFailAccountInactive";
        BalanceInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                BalanceInquiryRequest.class);
        BalanceInquiryRequestAdditionalInfo additionalInfo = new BalanceInquiryRequestAdditionalInfo();
        additionalInfo.accessToken(accessTokenAbnormalAccount);

        // Use the reference number for a canceled order
        requestData.setAdditionalInfo(additionalInfo);
        requestData.setPartnerReferenceNo(partnerReferenceNoInit);

        BalanceInquiryResponse response = widgetApi.balanceInquiry(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @Disabled
    void testBalanceInquiryFailInvalidSignature() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        String caseName = "BalanceInquiryFailInvalidSignature";
        BalanceInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                BalanceInquiryRequest.class);
        BalanceInquiryRequestAdditionalInfo additionalInfo = new BalanceInquiryRequestAdditionalInfo();
        additionalInfo.accessToken(accessToken);

        // Use the reference number for a canceled order
        requestData.setAdditionalInfo(additionalInfo);
        requestData.setPartnerReferenceNo(partnerReferenceNoInit);

        customHeaders.put(
                DanaHeader.X_SIGNATURE,
                "test");
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        WidgetApi apiWithCustomHeader = new WidgetApi(client);

        BalanceInquiryResponse response = apiWithCustomHeader.balanceInquiry(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }
}
