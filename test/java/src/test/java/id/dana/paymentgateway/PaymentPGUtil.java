package id.dana.paymentgateway;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.databind.node.ObjectNode;
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

import java.io.File;
import java.io.IOException;
import java.lang.reflect.Field;
import java.lang.reflect.Modifier;
import java.time.ZoneId;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
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
        String textAlreadyPaid = "//*[contains(text(),'order is already paid.')]";

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

//            Payment with Dana
            if (page.locator(buttonDana).isVisible()) {
                page.locator(buttonDana).waitFor(waitForOptions);
                page.locator(buttonDana).click();
            }

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
//            Click button pay
            page.locator(buttonPay).click();
            waitForOptions.setTimeout(4000);

            page.navigate(redirectUrlPay);
            page.locator(buttonPay).click();
            page.locator(textAlreadyPaid).isVisible();
            log.info("Order already paid ...");
        }
    }

    /** Current time in Asia/Jakarta plus the given minutes. */
    public static String generateDateWithOffset(long offsetInMinutes) {
        ZonedDateTime zonedDateTime = ZonedDateTime.now(ZoneId.of("Asia/Jakarta")).plusMinutes(offsetInMinutes);
        return zonedDateTime.format(DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ssXXX"));
    }


    public static String generateDateWithOffsetSeconds(long offsetSeconds) {
        ZonedDateTime zonedDateTime = ZonedDateTime.now(ZoneId.of("Asia/Jakarta")).plusSeconds(offsetSeconds);
        return zonedDateTime.format(DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ssXXX"));
    }

    /**
     * Recursively sets any empty string ("") to null in the object and nested objects/lists.
     * Generic: no field-specific logic, so JSON-backed requests match API expectation (null vs "").
     */
    public static void emptyStringToNull(Object obj) {
        if (obj == null) return;
        Class<?> c = obj.getClass();
        if (c.isEnum() || c.isPrimitive() || c == String.class) return;
        if (c.getPackage() != null && (c.getPackage().getName().startsWith("java.") || c.getPackage().getName().startsWith("javax."))) return;

        if (obj instanceof List) {
            for (Object item : (List<?>) obj) emptyStringToNull(item);
            return;
        }
        if (obj instanceof Map) return;

        for (Field f : getAllFields(c)) {
            if (Modifier.isStatic(f.getModifiers())) continue;
            f.setAccessible(true);
            try {
                Object val = f.get(obj);
                if (f.getType() == String.class && "".equals(val)) {
                    f.set(obj, null);
                } else if (val != null && f.getType().isEnum()) {
                    // OpenAPI enums often have getValue(); empty string should become null
                    String enumStr = null;
                    try {
                        java.lang.reflect.Method getValue = val.getClass().getMethod("getValue");
                        Object v = getValue.invoke(val);
                        enumStr = v == null ? null : v.toString();
                    } catch (NoSuchMethodException e) {
                        enumStr = ((Enum<?>) val).name();
                    } catch (Exception e) {
                        enumStr = ((Enum<?>) val).name();
                    }
                    if (enumStr != null && enumStr.isEmpty()) {
                        f.set(obj, null);
                    }
                } else if (val != null && !val.getClass().isEnum() && !isSimpleType(val.getClass())) {
                    emptyStringToNull(val);
                } else if (val instanceof List) {
                    for (Object item : (List<?>) val) emptyStringToNull(item);
                }
            } catch (IllegalAccessException ignored) { }
        }
    }

    private static List<Field> getAllFields(Class<?> c) {
        List<Field> list = new ArrayList<>();
        while (c != null && c != Object.class) {
            for (Field f : c.getDeclaredFields()) list.add(f);
            c = c.getSuperclass();
        }
        return list;
    }

    private static boolean isSimpleType(Class<?> c) {
        return c.isPrimitive() || c == String.class || Number.class.isAssignableFrom(c)
                || c == Boolean.class || c == Character.class || c == java.util.Date.class
                || c.getPackage() != null && (c.getPackage().getName().startsWith("java.") || c.getPackage().getName().startsWith("javax."));
    }

    private static final ObjectMapper objectMapper = new ObjectMapper();

    /** Load CreateOrder API request: use TestUtil.replaceTemplateValues, patch validUpTo before deserialize, set additionalInfo if null. Payment Gateway only; TestUtil.getRequest would fail on validUpTo. */
    public static CreateOrderByApiRequest getCreateOrderApiRequest(String jsonPathFile, String title, String caseName) {
        try {
            JsonNode requestNode = objectMapper.readTree(new File(jsonPathFile)).path(title).path(caseName).path("request");
            JsonNode replacedNode = TestUtil.replaceTemplateValues(requestNode);
            if (replacedNode.isObject()) {
                ((ObjectNode) replacedNode).put("validUpTo", generateDateWithOffsetSeconds(600));
            }
            CreateOrderByApiRequest result = objectMapper.treeToValue(replacedNode, CreateOrderByApiRequest.class);
            if (result.getAdditionalInfo() == null) {
                result.setAdditionalInfo(defaultCreateOrderByApiAdditionalInfo());
            }
            return result;
        } catch (IOException e) {
            throw new RuntimeException("Failed to load CreateOrder API request from " + jsonPathFile, e);
        }
    }

    /** Load CreateOrder Redirect request: use TestUtil.replaceTemplateValues, patch validUpTo before deserialize, set additionalInfo if null. Payment Gateway only. */
    public static CreateOrderByRedirectRequest getCreateOrderRedirectRequest(String jsonPathFile, String title, String caseName) {
        try {
            JsonNode requestNode = objectMapper.readTree(new File(jsonPathFile)).path(title).path(caseName).path("request");
            JsonNode replacedNode = TestUtil.replaceTemplateValues(requestNode);
            if (replacedNode.isObject()) {
                ((ObjectNode) replacedNode).put("validUpTo", generateDateWithOffsetSeconds(600));
            }
            CreateOrderByRedirectRequest result = objectMapper.treeToValue(replacedNode, CreateOrderByRedirectRequest.class);
            if (result.getAdditionalInfo() == null) {
                result.setAdditionalInfo(defaultCreateOrderByRedirectAdditionalInfo());
            }
            return result;
        } catch (IOException e) {
            throw new RuntimeException("Failed to load CreateOrder Redirect request from " + jsonPathFile, e);
        }
    }

    private static CreateOrderByApiAdditionalInfo defaultCreateOrderByApiAdditionalInfo() {
        CreateOrderByApiAdditionalInfo info = new CreateOrderByApiAdditionalInfo();
        info.setMcc("5732");
        info.setEnvInfo(defaultEnvInfo());
        return info;
    }

    private static CreateOrderByRedirectAdditionalInfo defaultCreateOrderByRedirectAdditionalInfo() {
        CreateOrderByRedirectAdditionalInfo info = new CreateOrderByRedirectAdditionalInfo();
        info.setMcc("5732");
        info.setEnvInfo(defaultEnvInfo());
        return info;
    }

    private static EnvInfo defaultEnvInfo() {
        EnvInfo e = new EnvInfo();
        e.setSourcePlatform(EnvInfo.SourcePlatformEnum.IPG);
        e.setTerminalType(EnvInfo.TerminalTypeEnum.SYSTEM);
        e.setOrderTerminalType(EnvInfo.OrderTerminalTypeEnum.WEB);
        return e;
    }
}
