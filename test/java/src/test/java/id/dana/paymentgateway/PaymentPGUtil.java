package id.dana.paymentgateway;

import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.databind.SerializationFeature;
import com.fasterxml.jackson.databind.node.ObjectNode;
import id.dana.paymentgateway.v1.model.*;
import id.dana.util.BrowserTestSupport;
import id.dana.util.TestUtil;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.File;
import java.io.IOException;
import java.io.OutputStream;
import java.lang.reflect.Field;
import java.lang.reflect.Modifier;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.time.ZoneId;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

public class PaymentPGUtil {
    private static final Logger log = LoggerFactory.getLogger(CreateOrderTest.class);

    private static final String SANDBOX_TOOLS_EXECUTE_URL =
            "https://dashboard-sandbox.dana.id/merchant-portal-app/api/sandbox-tools/execute";
    private static final String TRANSFER_VA_PAYMENT_ENDPOINT = "/v1.0/transfer-va/payment.htm";

    public static void payOrder(String phoneNumber, String pin, String redirectUrlPay) {
        BrowserTestSupport.paymentGatewayPayOrder(phoneNumber, pin, redirectUrlPay);
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

    /**
     * Raw JSON request for cases that must not be deserialized to {@link CreateOrderByApiRequest}
     * (SDK validation would reject invalid payloads before HTTP, e.g. CreateOrderInvalidFieldFormat).
     */
    public static JsonNode getCreateOrderRawRequest(String jsonPathFile, String title, String caseName)
            throws IOException {
        JsonNode requestNode = objectMapper.readTree(new File(jsonPathFile)).path(title).path(caseName).path("request");
        // Same as Python get_request for this case: template replace only (no validUpTo refresh).
        return TestUtil.replaceTemplateValues(requestNode);
    }

    /**
     * Canonical JSON string for SNAP body hash (sorted keys at each object), aligned with Go/Python direct calls.
     */
    public static String compactJsonForSnap(JsonNode node) throws JsonProcessingException {
        ObjectMapper sorted = new ObjectMapper()
                .configure(SerializationFeature.ORDER_MAP_ENTRIES_BY_KEYS, true);
        Object tree = objectMapper.treeToValue(node, Object.class);
        return sorted.writeValueAsString(tree);
    }

    public static String paymentCodeFromCreateOrderResponse(CreateOrderResponse response) {
        if (response == null
                || response.getAdditionalInfo() == null
                || response.getAdditionalInfo().getPaymentCode() == null
                || response.getAdditionalInfo().getPaymentCode().isEmpty()) {
            throw new IllegalStateException("paymentCode missing in create order response");
        }
        return response.getAdditionalInfo().getPaymentCode();
    }

    public static void payVirtualAccountSandbox(String virtualAccountNo) throws IOException {
        String payload = String.format(
                "{\"urlEndpoint\":\"%s\",\"requestBody\":{\"virtualAccountNo\":\"%s\"}}",
                TRANSFER_VA_PAYMENT_ENDPOINT,
                virtualAccountNo);

        URL url = new URL(SANDBOX_TOOLS_EXECUTE_URL);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("POST");
        conn.setDoOutput(true);
        conn.setConnectTimeout(30000);
        conn.setReadTimeout(30000);
        conn.setRequestProperty("accept", "application/json");
        conn.setRequestProperty("accept-language", "en,id-ID;q=0.9,id;q=0.8,en-US;q=0.7");
        conn.setRequestProperty("content-type", "application/json");
        conn.setRequestProperty("origin", "https://dashboard.dana.id");
        conn.setRequestProperty("referer", "https://dashboard.dana.id/");

        try (OutputStream os = conn.getOutputStream()) {
            os.write(payload.getBytes(StandardCharsets.UTF_8));
        }

        int responseCode = conn.getResponseCode();
        if (responseCode < 200 || responseCode >= 300) {
            throw new IOException("sandbox VA payment failed: HTTP " + responseCode);
        }
        conn.disconnect();
    }
}
