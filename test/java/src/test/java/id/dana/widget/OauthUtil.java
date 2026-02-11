package id.dana.widget;

import com.microsoft.playwright.*;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.UnsupportedEncodingException;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.nio.file.Paths;
import java.security.*;
import java.security.spec.InvalidKeySpecException;
import java.security.spec.PKCS8EncodedKeySpec;
import java.util.*;

import static io.restassured.RestAssured.given;

public class OauthUtil {
    private static String authCode;
    private final static String DEFAULT_USER_PIN = "181818";
    private final static String DEFAULT_USER_PHONENUMBER = "083811223355";
    private static final Logger log = LoggerFactory.getLogger(TestUtil.class);
    private static final String redirecrUrl = ConfigUtil.getConfig("REDIRECT_URL_OAUTH", "https://google.com");
    public static String generateSeamlessData(
            String phoneNumber,
            String bizScenario,
            String timeVerified,
            String externalUid,
            String deviceId,
            Boolean skipRegisterConsult)
            throws UnsupportedEncodingException {

        if (skipRegisterConsult == null)
            skipRegisterConsult = true;

        String seamlessData = String.format(
                "{\"phoneNumber\":\"%s\",\"bizScenario\":\"%s\",\"timeVerified\":\"%s\",\"externalUid\":\"%s\",\"deviceId\":\"%s\",\"skipRegisterConsult\":%b}",
                phoneNumber, bizScenario, timeVerified, externalUid, deviceId, skipRegisterConsult);


        return seamlessData;
    }

    public static String generateSeamlessSign(
            String seamlessData) throws
            UnsupportedEncodingException,
            NoSuchAlgorithmException,
            InvalidKeySpecException,
            SignatureException,
            InvalidKeyException {

        String privateKey = ConfigUtil.getConfig("PRIVATE_KEY", "");
        String signResult = sign(
                seamlessData,
                privateKey);

        return URLEncoder.encode(
                signResult,
                String.valueOf(StandardCharsets.UTF_8));
    }

    private static String sign(String textPayload, String privateKeyMerchant)
            throws NoSuchAlgorithmException,
            InvalidKeySpecException,
            SignatureException,
            InvalidKeyException {

        PrivateKey privateKeyObject = getPrivateKey(privateKeyMerchant);
        Signature signatureProcessor = Signature.getInstance("SHA256withRSA");
        signatureProcessor.initSign(privateKeyObject);
        signatureProcessor.update(textPayload.getBytes());

        byte[] signature = signatureProcessor.sign();

        return new String(Base64.getEncoder().encode(signature));
    }

    public static PrivateKey getPrivateKey(String privateKeyMerchant)
            throws NoSuchAlgorithmException,
            InvalidKeySpecException {

        byte[] key = Base64.getDecoder().decode(privateKeyMerchant.getBytes());
        PKCS8EncodedKeySpec pkcs8EncodedKeySpec = new PKCS8EncodedKeySpec(key);
        KeyFactory keyFactory = KeyFactory.getInstance("RSA");

        return keyFactory.generatePrivate(pkcs8EncodedKeySpec);
    }

    public static String generateRedirectLinkAuthCode(
            String partnerId,
            String channelId,
            String scope,
            String redirectUrl,
            String seamlessData,
            String seamlessSign) throws UnsupportedEncodingException {

        String basePath = "https://m.sandbox.dana.id/";
        String path = "v1.0/get-auth-code";

        String encodedSeamlessData = URLEncoder.encode(seamlessData, StandardCharsets.UTF_8.toString());
        String encodedSeamlessSign = URLEncoder.encode(seamlessSign, StandardCharsets.UTF_8.toString());

        String url = basePath + path + "?" +
                "partnerId=" + partnerId +
                "&timestamp=2023-08-31T22:27:48+00:00" +
                "&externalId=test" +
                "&channelId=" + channelId +
                "&scopes=" + scope +
                "&redirectUrl=" + redirectUrl +
                "&state=22321" +
                "&seamlessData=" + encodedSeamlessData +
                "&seamlessSign=" + encodedSeamlessSign;

        return url.toString();
    }

    public static String getAuthCode(
            String partnerId,
            String channelId,
            String phoneNumberUser,
            String pinUser)
            throws
            UnsupportedEncodingException,
            NoSuchAlgorithmException,
            InvalidKeySpecException,
            SignatureException,
            InvalidKeyException {

        String seamlessData = OauthUtil.generateSeamlessData(
                phoneNumberUser,
                "PAYMENT",
                "2024-12-23T07:44:11+07:00",
                UUID.randomUUID().toString(),
                "637216gygd76712313",
                true);

        String seamlessSign = OauthUtil.generateSeamlessSign(
                seamlessData);

        String urlRedirectAuth = OauthUtil.generateRedirectLinkAuthCode(
                partnerId,
                channelId,
                "DEFAULT_BASIC_PROFILE,QUERY_BALANCE,CASHIER,MINI_DANA",
                redirecrUrl,
                seamlessData,
                seamlessSign
        );

        return OauthUtil.getOauthViaView(urlRedirectAuth,phoneNumberUser,pinUser);
    }

    public static String getOauthViaView(String urlRedirectLinkAuthCode, String phoneNumber, String pin) {
        try (Playwright playwright = Playwright.create()) {
            Browser browser = playwright.webkit().launch();
            playwright.firefox().launch(new BrowserType.LaunchOptions());
            Page page = browser.newPage();
//            Redirect to page login user with phone number
            page.navigate(urlRedirectLinkAuthCode);

            Thread.sleep(5000); // Wait for the page to load

            String inputPhoneNumber = ".desktop-input>.txt-input-phone-number-field";
            String buttonSubmitPhoneNumber = ".agreement__button>.btn-continue";
            String inputPin = ".txt-input-pin-field";

//            Do action input phone number
            if (page.locator(inputPhoneNumber).isVisible())
                page.locator(inputPhoneNumber).fill(phoneNumber);
                page.locator(buttonSubmitPhoneNumber).click();

//            Input pin user
            page.locator(inputPin).fill(pin);

//            wait until page authcode visible
            page.waitForURL("**/**authCode**", new Page.WaitForURLOptions().setTimeout(15000));

            String currentUrl = page.url();
            String tempCurrentUrl = currentUrl
                    .replace(redirecrUrl + "/?","");

            authCode = tempCurrentUrl.split("authCode=")[1].split("&")[0];

            log.info("Auth Code: {}", authCode);
        } catch (InterruptedException e) {
            throw new RuntimeException(e);
        }
        return authCode;
    }

    public static String getAccessToken(String phoneNumberUser, String pinUser) throws
            UnsupportedEncodingException,
            NoSuchAlgorithmException,
            InvalidKeySpecException,
            SignatureException,
            InvalidKeyException {

        authCode = OauthUtil.getAuthCode(
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                ConfigUtil.getConfig("X_PARTNER_ID", ""),
                phoneNumberUser,
                pinUser);

        String accessToken = ApplyToken.applyToken(authCode);
        return accessToken;
    }
}
