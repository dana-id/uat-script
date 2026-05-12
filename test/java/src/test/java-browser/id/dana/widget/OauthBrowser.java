package id.dana.widget;

import com.microsoft.playwright.Browser;
import com.microsoft.playwright.BrowserType;
import com.microsoft.playwright.Page;
import com.microsoft.playwright.Playwright;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

/**
 * Playwright-based OAuth browser flow. Built only when the {@code with-playwright} Maven profile is active.
 */
public final class OauthBrowser {

    private static final Logger log = LoggerFactory.getLogger(TestUtil.class);
    private static final String REDIRECT_URL_OAUTH = ConfigUtil.getConfig("REDIRECT_URL_OAUTH", "https://google.com");

    private OauthBrowser() {
    }

    public static String getOauthViaView(String urlRedirectLinkAuthCode, String phoneNumber, String pin) {
        String authCode;
        try (Playwright playwright = Playwright.create()) {
            Browser browser = playwright.webkit().launch();
            playwright.firefox().launch(new BrowserType.LaunchOptions());
            Page page = browser.newPage();
            page.navigate(urlRedirectLinkAuthCode);

            Thread.sleep(5000);

            String inputPhoneNumber = ".desktop-input>.txt-input-phone-number-field";
            String buttonSubmitPhoneNumber = ".agreement__button>.btn-continue";
            String inputPin = ".txt-input-pin-field";

            if (page.locator(inputPhoneNumber).isVisible()) {
                page.locator(inputPhoneNumber).fill(phoneNumber);
            }
            page.locator(buttonSubmitPhoneNumber).click();

            page.locator(inputPin).fill(pin);

            page.waitForURL("**/**authCode**", new Page.WaitForURLOptions().setTimeout(15000));

            String currentUrl = page.url();
            String tempCurrentUrl = currentUrl.replace(REDIRECT_URL_OAUTH + "/?", "");

            authCode = tempCurrentUrl.split("authCode=")[1].split("&")[0];

            log.info("Auth Code: {}", authCode);
        } catch (InterruptedException e) {
            throw new RuntimeException(e);
        }
        return authCode;
    }
}
