package id.dana.widget;

import com.microsoft.playwright.*;
import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.CreateOrderTest;
import id.dana.paymentgateway.PaymentPGUtil;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.*;
import org.junit.jupiter.api.Assertions;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.IOException;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.*;

public class PaymentWidgetUtil {
    private static final String titleCase = "Payment";
    private static final Logger log = LoggerFactory.getLogger(CreateOrderTest.class);
    private static final String jsonPathFile = PaymentTest.class.getResource(
            "/request/components/Widget.json").getPath();
    private static WidgetApi widgetApi;
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    private static void topUpUserSaldo(String phoneNumber) {
        try {
            String payload = String.format(
                "{\"urlEndpoint\":\"/v1.0/emoney/topup.htm\",\"requestBody\":{" +
                "\"partnerReferenceNo\":\"%s\"," +
                "\"customerNumber\":\"%s\"," +
                "\"amount\":{\"value\":\"1000000.00\",\"currency\":\"IDR\"}," +
                "\"feeAmount\":{\"value\":\"0.00\",\"currency\":\"IDR\"}," +
                "\"additionalInfo\":{\"fundType\":\"AGENT_TOPUP_FOR_USER_SETTLE\"}}}",
                UUID.randomUUID().toString(),
                phoneNumber
            );

            URL url = new URL("https://dashboard-sandbox.dana.id/merchant-portal-app/api/sandbox-tools/execute");
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setDoOutput(true);
            conn.setRequestProperty("accept", "application/json");
            conn.setRequestProperty("accept-language", "en,id-ID;q=0.9,id;q=0.8,en-US;q=0.7");
            conn.setRequestProperty("content-type", "application/json");
            conn.setRequestProperty("origin", "https://dashboard.dana.id");
            conn.setRequestProperty("priority", "u=1, i");
            conn.setRequestProperty("referer", "https://dashboard.dana.id/");
            conn.setRequestProperty("sec-ch-ua", "\"Chromium\";v=\"146\", \"Not-A.Brand\";v=\"24\", \"Google Chrome\";v=\"146\"");
            conn.setRequestProperty("sec-ch-ua-mobile", "?0");
            conn.setRequestProperty("sec-ch-ua-platform", "\"macOS\"");
            conn.setRequestProperty("sec-fetch-dest", "empty");
            conn.setRequestProperty("sec-fetch-mode", "cors");
            conn.setRequestProperty("sec-fetch-site", "same-site");
            conn.setRequestProperty("user-agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36");
            conn.setConnectTimeout(10000);
            conn.setReadTimeout(10000);

            try (OutputStream os = conn.getOutputStream()) {
                os.write(payload.getBytes(StandardCharsets.UTF_8));
            }

            int responseCode = conn.getResponseCode();
            log.info("Top-up saldo response code: {}", responseCode);
            conn.disconnect();
        } catch (Exception e) {
            log.warn("Top-up saldo failed: {}", e.getMessage());
        }
    }

    public static void payOrder(
            String phoneNumber,
            String pin,
            String redirectUrlPay) {

        topUpUserSaldo(phoneNumber);

        String buttonDana = "//*[contains(@class,\"dana\")]/*[contains(@class,\"bank-title\")]";
        String inputPhoneNumber = ".desktop-input>.txt-input-phone-number-field";
        String buttonSubmitPhoneNumber = ".agreement__button>.btn-continue";
        String inputPin = ".txt-input-pin-field";
        String buttonPay = ".btn.btn-primary";
        String textAlreadyPaid = "//*[contains(text(),'order is already paid.')]";

        try (Playwright playwright = Playwright.create()) {
            BrowserType.LaunchOptions opts = new BrowserType.LaunchOptions();
            opts.setHeadless(true);
            Browser browser = playwright.chromium().launch(opts);
            Page page = browser.newPage();
//            Redirect to page payment
            page.navigate(redirectUrlPay);

//            Set timeout wait
            Locator.WaitForOptions waitForOptions = new Locator.WaitForOptions();
            waitForOptions.setTimeout(5000);

//            Payment with Dana
            if (page.locator(buttonDana).isVisible()) {
                page.locator(buttonDana).waitFor(waitForOptions);
                page.locator(buttonDana).click();
            }

//            Input phone number and pin
            page.locator(inputPhoneNumber).waitFor(waitForOptions);
            if (page.locator(inputPhoneNumber).isVisible()){
//                Do action input phone number
                page.locator(inputPhoneNumber).fill(phoneNumber.replaceFirst("0", ""));
                page.locator(buttonSubmitPhoneNumber).click();
//                Input pin
                page.locator(inputPin).fill(pin);
            }
//            Click button pay
            page.locator(buttonPay).click();

//            Wait transaction success
            Thread.sleep(5000);

            // page.locator(textAlreadyPaid).isVisible();
            // log.info("Order already paid ...");
        } catch (InterruptedException e) {
            throw new RuntimeException(e);
        }
    }

    public static List<String> createPayment(String paymentOrigin) {
        List<String> dataOrder = new ArrayList<>();

        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        widgetApi = Dana.getInstance().getWidgetApi();

        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
                paymentOrigin, WidgetPaymentRequest.class);

        // Assign unique reference and merchant ID
        String partnerReferenceNo = UUID.randomUUID().toString();
        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);
        requestData.setValidUpTo(PaymentPGUtil.generateDateWithOffset(30));

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNo);

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        Assertions.assertTrue(response.getResponseCode().contains("200"),
                "Response code is not 200, actual: " + response.getResponseCode());

        //Index 0 is partnerReferenceNo
        dataOrder.add(partnerReferenceNo);
        //Index 1 is the web redirect URL
        dataOrder.add(response.getWebRedirectUrl());

        return dataOrder;
    }
}
