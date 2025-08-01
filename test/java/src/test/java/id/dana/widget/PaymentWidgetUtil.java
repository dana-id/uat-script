package id.dana.widget;

import com.microsoft.playwright.*;
import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.*;
import org.junit.jupiter.api.Assertions;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.IOException;
import java.util.*;

public class PaymentWidgetUtil {
    private static final String titleCase = "Payment";
    private static final String jsonPathFile = PaymentTest.class.getResource(
            "/request/components/Widget.json").getPath();
    private static WidgetApi widgetApi;
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");
    public static String payOrderWithDana(
            String phoneNumber,
            String pin,
            String paymentOrigin) throws IOException {

        List<String> dataOrder = createPayment(paymentOrigin);

        String buttonDana = "//*[contains(@class,\"dana\")]/*[contains(@class,\"bank-title\")]";
        String inputPhoneNumber = "//*[contains(@class,\"desktop-input\")]//input";
        String buttonSubmitPhoneNumber = "//*[contains(@class,\"agreement__button\")]//button";
        String inputPin = "//*[contains(@class,\"input-pin\")]//input";
        String buttonPay = "//*[contains(@class,\"btn-pay\")]";

        try (Playwright playwright = Playwright.create()) {
            Browser browser = playwright.webkit().launch();
            playwright.firefox().launch(new BrowserType.LaunchOptions());
            Page page = browser.newPage();
//            Redirect to page payment
            page.navigate(dataOrder.get(1));

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
            Thread.sleep(10000);
        } catch (InterruptedException e) {
            throw new RuntimeException(e);
        }
        return dataOrder.get(0);
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

    public static String cancelOrder(String paymentOrigin) throws IOException {
        List<String> partnerReferenceNo = createPayment(paymentOrigin);
        CancelOrderRequest requestDataCancel = TestUtil.getRequest(jsonPathFile, "CancelOrder", "CancelOrderValidScenario",
                CancelOrderRequest.class);

        requestDataCancel.setOriginalPartnerReferenceNo(partnerReferenceNo.get(0));
        requestDataCancel.setMerchantId(merchantId);

        CancelOrderResponse responseCancel = widgetApi.cancelOrder(requestDataCancel);
        Assertions.assertTrue(responseCancel.getResponseCode().contains("200"));
        return partnerReferenceNo.get(0);
    }

    public static String refundOrder(
            String phoneNumber,
            String pin,
            String paymentOrigin) throws IOException {

        String partnerReferenceNo = payOrderWithDana(phoneNumber, pin, paymentOrigin);

        RefundOrderRequest requestRefund = TestUtil.getRequest(jsonPathFile, "RefundOrder", "RefundOrderValidScenario",
                RefundOrderRequest.class);

        requestRefund.setOriginalPartnerReferenceNo(partnerReferenceNo);
        requestRefund.setPartnerRefundNo(partnerReferenceNo);
        requestRefund.setMerchantId(merchantId);

        RefundOrderResponse responseRefund = widgetApi.refundOrder(requestRefund);
        Assertions.assertTrue(responseRefund.getResponseCode().contains("200"));
        return partnerReferenceNo;
    }
}
