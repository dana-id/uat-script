package id.dana.widget;

import id.dana.util.ConfigUtil;
import io.restassured.RestAssured;
import io.restassured.builder.RequestSpecBuilder;
import io.restassured.http.ContentType;
import io.restassured.response.Response;
import io.restassured.specification.RequestSpecification;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.UnsupportedEncodingException;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.security.SignatureException;
import java.security.spec.InvalidKeySpecException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;

public class GetOauthUrl {
    private static String seamlessData;
    private static String seamlessSign;
    private static final String redirecrUrl = ConfigUtil.getConfig("REDIRECT_URL_OAUTH", "https://google.com");

    @BeforeEach
    void setUp() throws
            UnsupportedEncodingException,
            NoSuchAlgorithmException,
            InvalidKeySpecException,
            SignatureException,
            InvalidKeyException {

        seamlessData = OauthUtil.generateSeamlessData(
                "0811742234",
                "PAYMENT",
                "2024-12-23T07:44:11+07:00",
                UUID.randomUUID().toString(),
                "637216gygd76712313",
                true);

        seamlessSign = OauthUtil.generateSeamlessSign(
                seamlessData);
    }

    @Test
    void getOauthV2Valid() {
        Map<String, String> queryParams = new HashMap<>();

        queryParams.put("partnerId", ConfigUtil.getConfig("X_PARTNER_ID", "123123123"));
        queryParams.put("timestamp", "2024-08-31T22:27:48+00:00");
        queryParams.put("externalId", "test");
        queryParams.put("channelId", ConfigUtil.getConfig("X_PARTNER_ID", "95221"));
        queryParams.put("scopes", "CASHIER,AGREEMENT_PAY,QUERY_BALANCE,DEFAULT_BASIC_PROFILE,MINI_DANA");
        queryParams.put("redirectUrl", redirecrUrl);
        queryParams.put("state", "02c92610-aa7c-42b0-bf26-23bb06e4d475");
        queryParams.put("isSnapBI", "true");
        queryParams.put("seamlessData", seamlessData);
        queryParams.put("seamlessSign", seamlessSign);

        Response oauthUrl = getOauthUrl(queryParams);
        Logger log = LoggerFactory.getLogger(GetOauthUrl.class);

        log.info("Response: {}", oauthUrl.asString());
        log.info("Response Code: {}", oauthUrl.getStatusCode());
        Assertions.assertEquals(200, oauthUrl.getStatusCode(), "Response status code should be 200");
    }
    public Response getOauthUrl(Map<String, String> queryParams) {
        String basePath = "https://m.sandbox.dana.id/";
        String path = "v1.0/get-auth-code";

        RequestSpecBuilder builder = new RequestSpecBuilder();
        RequestSpecification requestSpecification = builder
                .setBaseUri(basePath + path)
                .setContentType(ContentType.JSON)
                .addQueryParams(queryParams)
                .build();

        return RestAssured
                .given()
                .spec(requestSpecification)
                .log().all()
                .when()
                .get()
                .then()
                .log().ifError()
                .extract()
                .response();
    }
}
