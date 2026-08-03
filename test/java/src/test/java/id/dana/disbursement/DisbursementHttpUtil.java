package id.dana.disbursement;

import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.databind.SerializationFeature;
import com.fasterxml.jackson.databind.node.ObjectNode;
import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.TransferToBankRequest;
import id.dana.disbursement.v1.model.TransferToBankResponse;
import id.dana.disbursement.v1.model.TransferToDanaRequest;
import id.dana.disbursement.v1.model.TransferToDanaResponse;
import id.dana.disbursement.v1.model.BankAccountInquiryRequest;
import id.dana.disbursement.v1.model.BankAccountInquiryResponse;
import id.dana.disbursement.v1.model.DanaAccountInquiryRequest;
import id.dana.disbursement.v1.model.DanaAccountInquiryResponse;
import id.dana.interceptor.ReplaceRequestBodyInterceptor;
import id.dana.invoker.auth.DanaAuth;
import id.dana.util.TestUtil;
import java.io.File;
import java.io.IOException;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;

/**
 * Raw JSON + body-replacement helpers so sandbox SDK amount/beneficiary validation does not
 * block intentional API-error fixtures (same approach as Payment Gateway invalid-field tests).
 */
final class DisbursementHttpUtil {

  private static final ObjectMapper OBJECT_MAPPER = new ObjectMapper();
  private static final MediaType JSON = MediaType.parse("application/json; charset=utf-8");

  private DisbursementHttpUtil() {}

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
   * Calls transferToDana with a validation-safe shell request while sending {@code caseName}
   * fixture JSON on the wire (signed by DanaAuth after body replacement).
   */
  static TransferToDanaResponse transferToDanaWithFixtureBody(
      String jsonPathFile, String caseName, String partnerReferenceNo) throws IOException {
    JsonNode bodyNode = getRawRequest(jsonPathFile, "TransferToDana", caseName);
    ((ObjectNode) bodyNode).put("partnerReferenceNo", partnerReferenceNo);
    String payload = compactJsonForSnap(bodyNode);

    TransferToDanaRequest shell =
        TestUtil.getRequest(
            jsonPathFile, "TransferToDana", "TopUpCustomerValid", TransferToDanaRequest.class);
    shell.setPartnerReferenceNo(partnerReferenceNo);

    DisbursementApi api =
        new DisbursementApi(
            new OkHttpClient.Builder()
                .addInterceptor(new ReplaceRequestBodyInterceptor(payload, JSON))
                .addInterceptor(new DanaAuth())
                .build());
    return api.transferToDana(shell);
  }

  /**
   * Calls transferToBank with a validation-safe shell request while sending {@code caseName}
   * fixture JSON on the wire.
   */
  static TransferToBankResponse transferToBankWithFixtureBody(
      String jsonPathFile, String caseName, String partnerReferenceNo) throws IOException {
    JsonNode bodyNode = getRawRequest(jsonPathFile, "TransferToBank", caseName);
    ((ObjectNode) bodyNode).put("partnerReferenceNo", partnerReferenceNo);
    return transferToBankWithPayload(jsonPathFile, compactJsonForSnap(bodyNode), partnerReferenceNo);
  }

  static TransferToBankResponse transferToBankWithPayload(
      String jsonPathFile, String payload, String partnerReferenceNo) throws IOException {
    TransferToBankRequest shell =
        TestUtil.getRequest(
            jsonPathFile,
            "TransferToBank",
            "DisbursementBankValidAccount",
            TransferToBankRequest.class);
    shell.setPartnerReferenceNo(partnerReferenceNo);

    DisbursementApi api =
        new DisbursementApi(
            new OkHttpClient.Builder()
                .addInterceptor(new ReplaceRequestBodyInterceptor(payload, JSON))
                .addInterceptor(new DanaAuth())
                .build());
    return api.transferToBank(shell);
  }

  static BankAccountInquiryResponse bankAccountInquiryWithFixtureBody(
      String jsonPathFile, String caseName, String partnerReferenceNo) throws IOException {
    JsonNode bodyNode = getRawRequest(jsonPathFile, "BankAccountInquiry", caseName);
    ((ObjectNode) bodyNode).put("partnerReferenceNo", partnerReferenceNo);
    String payload = compactJsonForSnap(bodyNode);

    BankAccountInquiryRequest shell =
        TestUtil.getRequest(
            jsonPathFile,
            "BankAccountInquiry",
            "InquiryBankAccountValidDataAmount",
            BankAccountInquiryRequest.class);
    shell.setPartnerReferenceNo(partnerReferenceNo);

    DisbursementApi api =
        new DisbursementApi(
            new OkHttpClient.Builder()
                .addInterceptor(new ReplaceRequestBodyInterceptor(payload, JSON))
                .addInterceptor(new DanaAuth())
                .build());
    return api.bankAccountInquiry(shell);
  }

  static DanaAccountInquiryResponse danaAccountInquiryWithFixtureBody(
      String jsonPathFile, String caseName, String partnerReferenceNo) throws IOException {
    JsonNode bodyNode = getRawRequest(jsonPathFile, "DanaAccountInquiry", caseName);
    ((ObjectNode) bodyNode).put("partnerReferenceNo", partnerReferenceNo);
    String payload = compactJsonForSnap(bodyNode);

    DanaAccountInquiryRequest shell =
        TestUtil.getRequest(
            jsonPathFile,
            "DanaAccountInquiry",
            "InquiryCustomerValidData",
            DanaAccountInquiryRequest.class);
    shell.setPartnerReferenceNo(partnerReferenceNo);

    DisbursementApi api =
        new DisbursementApi(
            new OkHttpClient.Builder()
                .addInterceptor(new ReplaceRequestBodyInterceptor(payload, JSON))
                .addInterceptor(new DanaAuth())
                .build());
    return api.danaAccountInquiry(shell);
  }
}
