package id.dana.widget;

import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.util.ConfigUtil;
import id.dana.util.RetryTestUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.*;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;

import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.security.SignatureException;
import java.security.spec.InvalidKeySpecException;

public class ApplyOttTest {
    private final static String USER_PIN = "123321";
    private final static String USER_PHONENUMBER = "0811742234";
    private static final String titleCase = "ApplyOtt";
    private static final String jsonPathFile = ApplyToken.class.getResource("/request/components/Widget.json")
            .getPath();
    private static WidgetApi widgetApi;
    private static String accessToken;

    @BeforeAll
    static void setUp() throws
            UnsupportedEncodingException,
            NoSuchAlgorithmException,
            InvalidKeySpecException,
            SignatureException,
            InvalidKeyException {

        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);
        widgetApi = Dana.getInstance().getWidgetApi();

        accessToken = OauthUtil.getAccessToken(
                USER_PHONENUMBER,
                USER_PIN
        );
    }

    @Test
    @RetryTestUtil.Retry
    void testApplyOttSuccess() throws IOException {
        String caseName = "ApplyOttSuccess";
        ApplyOTTRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                ApplyOTTRequest.class);

        ApplyOTTRequestAdditionalInfo additionalInfo = new ApplyOTTRequestAdditionalInfo();
        additionalInfo.setAccessToken(accessToken);
        additionalInfo.setDeviceId("deviceid123");

        requestData.setAdditionalInfo(additionalInfo);

        ApplyOTTResponse response = widgetApi.applyOTT(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    @Disabled
    void testApplyOttFailInvalidUserStatus() throws IOException, NoSuchAlgorithmException, InvalidKeySpecException, SignatureException, InvalidKeyException {
        String caseName = "ApplyOttFailInvalidUserStatus";
        String abnormalUserPhone = "0855100800";
        String abnormalUserPin = "146838";

        String abnormalAccessToken = OauthUtil.getAccessToken(
                abnormalUserPhone,
                abnormalUserPin
        );

        ApplyOTTRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                ApplyOTTRequest.class);

        ApplyOTTRequestAdditionalInfo additionalInfo = new ApplyOTTRequestAdditionalInfo();
        additionalInfo.setAccessToken(abnormalAccessToken);
        additionalInfo.setDeviceId("deviceid123");

        requestData.setAdditionalInfo(additionalInfo);

        ApplyOTTResponse response = widgetApi.applyOTT(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testApplyOttCustomerTokenNotFound() throws IOException {
        String caseName = "ApplyOttCustomerTokenNotFound";
        ApplyOTTRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                ApplyOTTRequest.class);

        ApplyOTTRequestAdditionalInfo additionalInfo = new ApplyOTTRequestAdditionalInfo();
        additionalInfo.setAccessToken("GtRLpA0TyqK3becMq4dCMnVf1N9KLHNixVfC1800");
        additionalInfo.setDeviceId("deviceid123");

        requestData.setAdditionalInfo(additionalInfo);

        ApplyOTTResponse response = widgetApi.applyOTT(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }
}
