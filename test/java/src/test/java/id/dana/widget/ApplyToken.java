package id.dana.widget;

import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.util.ConfigUtil;
import id.dana.util.OauthUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.*;
import okhttp3.OkHttpClient;
import org.apache.commons.lang3.RandomStringUtils;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.security.SignatureException;
import java.security.spec.InvalidKeySpecException;
import java.util.HashMap;
import java.util.Map;
import java.util.Random;
import java.util.UUID;

public class ApplyToken {
    private final static String USER_PIN = "123321";
    private final static String USER_PHONENUMBER = "0811742234";
    private static final String titleCase = "ApplyToken";
    private static final String jsonPathFile = ApplyToken.class.getResource("/request/components/Widget.json")
            .getPath();
    public static WidgetApi widgetApi;
    String authCode;

    @BeforeEach
    void setUp() throws
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

        authCode = OauthUtil.getAuthCode(
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                USER_PHONENUMBER,
                USER_PIN);
    }

    @Test
    void testApplyTokenSuccess() throws IOException {
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
    void testApplyTokenFailExpiredAuthcode() throws IOException, NoSuchAlgorithmException, InvalidKeySpecException, SignatureException, InvalidKeyException {
        // Create an order with an initial status
        Map<String, Object> variableDict = new HashMap<>();
        String caseName = "ApplyTokenFailExpiredAuthcode";
        ApplyTokenAuthorizationCodeRequest requestData = TestUtil.getRequest(jsonPathFile, "ApplyToken", "ApplyTokenSuccess",
                ApplyTokenAuthorizationCodeRequest.class);

        //GtRLpA0TyqK3becMq4dCMnVf1N9KLHNixVfC1800 is authcode expired
        requestData.setAuthCode("GtRLpA0TyqK3becMq4dCMnVf1N9KLHNixVfC1800");

        ApplyTokenResponse response = widgetApi.applyToken(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    }

    @Test
    void testApplyTokenFailInvalidSignature() throws IOException {
        Map<String, String> customHeaders = new HashMap<>();
        Map<String, Object> variableDict = new HashMap<>();
        
        customHeaders.put(DanaHeader.X_SIGNATURE, RandomStringUtils.randomAlphanumeric(300));
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
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
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
