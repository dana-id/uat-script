package id.dana.widget;

import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.*;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.security.SignatureException;
import java.security.spec.InvalidKeySpecException;

public class AccountUnbindingTest {
    private final static String USER_PIN = "123321";
    private final static String USER_PHONENUMBER = "0811742234";
    private static final String titleCase = "AccountUnbinding";
    private static final String jsonPathFile = ApplyToken.class.getResource("/request/components/Widget.json")
            .getPath();
    private WidgetApi widgetApi;
    private String authCode;
    private String accessToken;

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

        accessToken = ApplyToken.applyToken(authCode);
    }

    @Test
    void testAccountUnbindSuccess() throws IOException {
        // Create an order with an initial status
        AccountUnbindingRequestAdditionalInfo additionalInfo = new AccountUnbindingRequestAdditionalInfo();
        String caseName = "AccountUnbindSuccess";
        AccountUnbindingRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                AccountUnbindingRequest.class);

        additionalInfo.setAccessToken(accessToken);
        requestData.setMerchantId(ConfigUtil.getConfig("MERCHANT_ID", ""));
        requestData.setAdditionalInfo(additionalInfo);

        AccountUnbindingResponse response = widgetApi.accountUnbinding(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testAccountUnbindFailAccessTokenNotExist() throws IOException {
        // Create an order with an initial status
        AccountUnbindingRequestAdditionalInfo additionalInfo = new AccountUnbindingRequestAdditionalInfo();
        String caseName = "AccountUnbindFailAccessTokenNotExist";
        AccountUnbindingRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                AccountUnbindingRequest.class);

        additionalInfo.setAccessToken("TEST123");
        requestData.setMerchantId(ConfigUtil.getConfig("MERCHANT_ID", ""));
        requestData.setAdditionalInfo(additionalInfo);

        AccountUnbindingResponse response = widgetApi.accountUnbinding(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testAccountUnbindFailInvalidUserStatus() throws IOException {
        // Create an order with an initial status
        AccountUnbindingRequestAdditionalInfo additionalInfo = new AccountUnbindingRequestAdditionalInfo();
        String caseName = "AccountUnbindFailInvalidUserStatus";
        AccountUnbindingRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                AccountUnbindingRequest.class);

        additionalInfo.setAccessToken(accessToken);
        requestData.setMerchantId(ConfigUtil.getConfig("MERCHANT_ID", ""));
        requestData.setAdditionalInfo(additionalInfo);

        AccountUnbindingResponse response = widgetApi.accountUnbinding(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }

    @Test
    void testAccountUnbindFailInvalidParams() throws IOException {
        // Create an order with an initial status
        AccountUnbindingRequestAdditionalInfo additionalInfo = new AccountUnbindingRequestAdditionalInfo();
        String caseName = "AccountUnbindFailInvalidParams";
        AccountUnbindingRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
                AccountUnbindingRequest.class);

        additionalInfo.setAccessToken(accessToken);
        requestData.setMerchantId(ConfigUtil.getConfig("MERCHANT_ID", ""));
        requestData.setAdditionalInfo(additionalInfo);

        AccountUnbindingResponse response = widgetApi.accountUnbinding(requestData);
        TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, null);
    }
}
