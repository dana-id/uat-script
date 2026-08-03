package id.dana.widget;

import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.databind.SerializationFeature;
import com.fasterxml.jackson.databind.node.ObjectNode;
import id.dana.interceptor.ReplaceRequestBodyInterceptor;
import id.dana.invoker.auth.DanaAuth;
import id.dana.paymentgateway.PaymentPGUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.WidgetPaymentRequest;
import id.dana.widget.v1.model.WidgetPaymentResponse;
import java.io.File;
import java.io.IOException;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;

/**
 * Raw JSON + body-replacement helpers so sandbox SDK amount validation does not block
 * intentional API-error fixtures (same approach as DisbursementHttpUtil).
 */
final class WidgetHttpUtil {

  private static final ObjectMapper OBJECT_MAPPER = new ObjectMapper();
  private static final MediaType JSON = MediaType.parse("application/json; charset=utf-8");

  private WidgetHttpUtil() {}

  static JsonNode getRawRequest(String jsonPathFile, String title, String caseName)
      throws IOException {
    JsonNode requestNode =
        OBJECT_MAPPER.readTree(new File(jsonPathFile)).path(title).path(caseName).path("request");
    return TestUtil.replaceTemplateValues(requestNode);
  }

  static String compactJsonForSnap(JsonNode node) throws JsonProcessingException {
    ObjectMapper sorted =
        new ObjectMapper().configure(SerializationFeature.ORDER_MAP_ENTRIES_BY_KEYS, true);
    Object tree = OBJECT_MAPPER.treeToValue(node, Object.class);
    return sorted.writeValueAsString(tree);
  }

  /**
   * Calls widgetPayment with a validation-safe shell request while sending {@code caseName}
   * fixture JSON on the wire (signed by DanaAuth after body replacement).
   */
  static WidgetPaymentResponse widgetPaymentWithFixtureBody(
      String jsonPathFile,
      String caseName,
      String partnerReferenceNo,
      String merchantId,
      long validUpToOffsetMinutes)
      throws IOException {
    JsonNode bodyNode = getRawRequest(jsonPathFile, "Payment", caseName);
    ObjectNode objectNode = (ObjectNode) bodyNode;
    objectNode.put("partnerReferenceNo", partnerReferenceNo);
    objectNode.put("merchantId", merchantId);
    objectNode.put("validUpTo", PaymentPGUtil.generateDateWithOffset(validUpToOffsetMinutes));
    String payload = compactJsonForSnap(bodyNode);

    WidgetPaymentRequest shell =
        TestUtil.getRequest(jsonPathFile, "Payment", "PaymentSuccess", WidgetPaymentRequest.class);
    shell.setPartnerReferenceNo(partnerReferenceNo);
    shell.setMerchantId(merchantId);
    shell.setValidUpTo(PaymentPGUtil.generateDateWithOffset(validUpToOffsetMinutes));

    WidgetApi api =
        new WidgetApi(
            new OkHttpClient.Builder()
                .addInterceptor(new ReplaceRequestBodyInterceptor(payload, JSON))
                .addInterceptor(new DanaAuth())
                .build());
    return api.widgetPayment(shell);
  }
}
