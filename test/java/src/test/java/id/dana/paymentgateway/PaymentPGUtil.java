package id.dana.paymentgateway;

import com.microsoft.playwright.*;
import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.paymentgateway.v1.model.*;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import org.junit.jupiter.api.Assertions;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.IOException;
import java.util.*;

public class PaymentPGUtil {
    private static final Logger log = LoggerFactory.getLogger(CreateOrderTest.class);
    public static void payOrder(
            String phoneNumber,
            String pin,
            String redirectUrlPay) {

        String buttonDana = "//*[contains(@class,\"dana\")]/*[contains(@class,\"bank-title\")]";
        String inputPhoneNumber = ".desktop-input>.txt-input-phone-number-field";
        String buttonSubmitPhoneNumber = ".agreement__button>.btn-continue";
        String inputPin = ".txt-input-pin-field";
        String buttonPay = ".btn.btn-primary";
        String urlSuccessPaid = "**/v1/test";

        try (Playwright playwright = Playwright.create()) {
            Browser browser = playwright.webkit().launch(new BrowserType.LaunchOptions().setHeadless(true));
            playwright.firefox().launch(new BrowserType.LaunchOptions().setHeadless(true));
            Page page = browser.newPage();

//            Redirect to page payment
            log.info("Redirect to page payment: {}", redirectUrlPay);
            page.navigate(redirectUrlPay);

//            Set timeout wait
            Locator.WaitForOptions waitForOptions = new Locator.WaitForOptions();
            waitForOptions.setTimeout(5000);

//            Input phone number and pin
            log.info("Input phone number: {} and pin: {}", phoneNumber, pin);
            page.locator(inputPhoneNumber).waitFor(waitForOptions);
            if (page.locator(inputPhoneNumber).isVisible()){
//                Do action input phone number
                page.locator(inputPhoneNumber).fill(phoneNumber.replaceFirst("0", ""));
                page.locator(buttonSubmitPhoneNumber).click();
//                Input pin
                page.locator(inputPin).fill(pin);
            }
            log.info("Click button pay");
//            Click button pay
            page.locator(buttonPay).click();
//            Wait payemnt success
            log.info("Wait for URL success payment: {}", urlSuccessPaid);
            page.waitForURL(urlSuccessPaid);
        }
    }
}
