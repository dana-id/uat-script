package id.dana.widget;

import com.microsoft.playwright.Browser;
import com.microsoft.playwright.BrowserType;
import com.microsoft.playwright.Locator;
import com.microsoft.playwright.Page;
import com.microsoft.playwright.Playwright;
import id.dana.paymentgateway.CreateOrderTest;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.UUID;

/**
 * Playwright-based widget payment flow. Built only when the {@code with-playwright} Maven profile is active.
 */
public final class PaymentWidgetBrowser {

    private static final Logger log = LoggerFactory.getLogger(CreateOrderTest.class);

    private PaymentWidgetBrowser() {
    }

    private static void topUpUserSaldo(String phoneNumber) {
        try {
            String payload = String.format(
                    "{\"urlEndpoint\":\"/v1.0/emoney/topup.htm\",\"requestBody\":{"
                            + "\"partnerReferenceNo\":\"%s\","
                            + "\"customerNumber\":\"%s\","
                            + "\"amount\":{\"value\":\"1000000.00\",\"currency\":\"IDR\"},"
                            + "\"feeAmount\":{\"value\":\"0.00\",\"currency\":\"IDR\"},"
                            + "\"additionalInfo\":{\"fundType\":\"AGENT_TOPUP_FOR_USER_SETTLE\"}}}",
                    UUID.randomUUID().toString(),
                    phoneNumber);

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
            conn.setRequestProperty(
                    "sec-ch-ua",
                    "\"Chromium\";v=\"146\", \"Not-A.Brand\";v=\"24\", \"Google Chrome\";v=\"146\"");
            conn.setRequestProperty("sec-ch-ua-mobile", "?0");
            conn.setRequestProperty("sec-ch-ua-platform", "\"macOS\"");
            conn.setRequestProperty("sec-fetch-dest", "empty");
            conn.setRequestProperty("sec-fetch-mode", "cors");
            conn.setRequestProperty("sec-fetch-site", "same-site");
            conn.setRequestProperty(
                    "user-agent",
                    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36");
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

    public static void payOrder(String phoneNumber, String pin, String redirectUrlPay) {
        topUpUserSaldo(phoneNumber);

        String buttonDana = "//*[contains(@class,\"dana\")]/*[contains(@class,\"bank-title\")]";
        String inputPhoneNumber = ".desktop-input>.txt-input-phone-number-field";
        String buttonSubmitPhoneNumber = ".agreement__button>.btn-continue";
        String inputPin = ".txt-input-pin-field";
        String buttonPay = ".btn.btn-primary";

        try (Playwright playwright = Playwright.create()) {
            BrowserType.LaunchOptions opts = new BrowserType.LaunchOptions();
            opts.setHeadless(true);
            Browser browser = playwright.chromium().launch(opts);
            Page page = browser.newPage();
            page.navigate(redirectUrlPay);

            Locator.WaitForOptions waitForOptions = new Locator.WaitForOptions();
            waitForOptions.setTimeout(5000);

            if (page.locator(buttonDana).isVisible()) {
                page.locator(buttonDana).waitFor(waitForOptions);
                page.locator(buttonDana).click();
            }

            page.locator(inputPhoneNumber).waitFor(waitForOptions);
            if (page.locator(inputPhoneNumber).isVisible()) {
                page.locator(inputPhoneNumber).fill(phoneNumber.replaceFirst("0", ""));
                page.locator(buttonSubmitPhoneNumber).click();
                page.locator(inputPin).fill(pin);
            }
            page.locator(buttonPay).click();

            Thread.sleep(5000);
        } catch (InterruptedException e) {
            throw new RuntimeException(e);
        }
    }
}
