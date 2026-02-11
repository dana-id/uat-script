package id.dana.widget;

import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.invoker.model.exception.DanaException;
import id.dana.paymentgateway.CreateOrderTest;
import id.dana.paymentgateway.v1.model.CreateOrderResponse;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.*;
import okhttp3.OkHttpClient;
import org.apache.commons.lang3.RandomStringUtils;
import org.junit.jupiter.api.*;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.security.SignatureException;
import java.security.spec.InvalidKeySpecException;
import java.util.HashMap;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.fail;

public class ApplyToken {
    private static final Logger log = LoggerFactory.getLogger(CreateOrderTest.class);
    private final static String USER_PIN = "181818";
    private final static String USER_PHONENUMBER = "083811223355";
    private static final String titleCase = "ApplyToken";
    private static final String jsonPathFile = ApplyToken.class.getResource("/request/components/Widget.json")
            .getPath();
    public static WidgetApi widgetApi;

    @BeforeAll
    static void setUp() {

        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);
        widgetApi = Dana.getInstance().getWidgetApi();
    }

    @Test
    void testApplyTokenSuccess() throws IOException, NoSuchAlgorithmException, InvalidKeySpecException, SignatureException, InvalidKeyException {
        String authCode = OauthUtil.getAuthCode(
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                USER_PHONENUMBER,
                USER_PIN);

        // Create an order with an initial status
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "ApplyTokenSuccess";
        ApplyTokenAuthorizationCodeRequest requestData = TestUtil.getRequest(jsonPathFile, "ApplyToken", "ApplyTokenSuccess",
                ApplyTokenAuthorizationCodeRequest.class);

        requestData.setAuthCode(authCode);

        ApplyTokenResponse response = widgetApi.applyToken(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    @Disabled
    void testApplyTokenFailExpiredAuthcode() throws IOException, NoSuchAlgorithmException, InvalidKeySpecException, SignatureException, InvalidKeyException {
        String authCode = OauthUtil.getAuthCode(
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                USER_PHONENUMBER,
                USER_PIN);

        OauthUtil.getAuthCode(
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                USER_PHONENUMBER,
                USER_PIN);

        // Create an order with an initial status
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "ApplyTokenFailExpiredAuthcode";
        ApplyTokenAuthorizationCodeRequest requestData = TestUtil.getRequest(jsonPathFile, "ApplyToken", "ApplyTokenSuccess",
                ApplyTokenAuthorizationCodeRequest.class);

        requestData.setAuthCode(authCode);

        ApplyTokenResponse response = widgetApi.applyToken(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testApplyTokenFailAuthcodeUsed() throws IOException, NoSuchAlgorithmException, InvalidKeySpecException, SignatureException, InvalidKeyException {
        String authCode = OauthUtil.getAuthCode(
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                USER_PHONENUMBER,
                USER_PIN);

        applyToken(authCode);

        // Create an order with an initial status
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "ApplyTokenFailAuthcodeUsed";
        ApplyTokenAuthorizationCodeRequest requestData = TestUtil.getRequest(jsonPathFile, "ApplyToken", "ApplyTokenSuccess",
                ApplyTokenAuthorizationCodeRequest.class);

        //GtRLpA0TyqK3becMq4dCMnVf1N9KLHNixVfC1800 is authcode expired
        requestData.setAuthCode(authCode);

        ApplyTokenResponse response = widgetApi.applyToken(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testApplyTokenFailInvalidSignature() throws IOException, NoSuchAlgorithmException, InvalidKeySpecException, SignatureException, InvalidKeyException {
        String authCode = OauthUtil.getAuthCode(
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                USER_PHONENUMBER,
                USER_PIN);

        Map<String, String> customHeaders = new HashMap<>();
        Map<String, Object> variableDict = new HashMap<>();
        
        customHeaders.put(DanaHeader.X_SIGNATURE, RandomStringUtils.randomAlphanumeric(5));
        OkHttpClient client = new OkHttpClient.Builder()
                .addInterceptor(new DanaAuth())
                .addInterceptor(new CustomHeaderInterceptor(customHeaders))
                .build();
        WidgetApi apiWithCustomHeader = new WidgetApi(client);

        String caseName = "ApplyTokenFailInvalidSignature";
        ApplyTokenAuthorizationCodeRequest requestData = TestUtil.getRequest(jsonPathFile, "ApplyToken", "ApplyTokenSuccess",
                ApplyTokenAuthorizationCodeRequest.class);

        requestData.setAuthCode(authCode);

        ApplyTokenResponse response = apiWithCustomHeader.applyToken(requestData);
        TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    public static String applyToken(String authCode) {
        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);
        widgetApi = Dana.getInstance().getWidgetApi();

        ApplyTokenAuthorizationCodeRequest requestData = TestUtil.getRequest(jsonPathFile, "ApplyToken", "ApplyTokenSuccess",
                ApplyTokenAuthorizationCodeRequest.class);

        requestData.setAuthCode(authCode);

        ApplyTokenResponse response = widgetApi.applyToken(requestData);
        return response.getAccessToken().toString();
    }
}
