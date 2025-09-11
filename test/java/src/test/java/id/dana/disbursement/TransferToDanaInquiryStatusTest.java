package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.*;
import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;

import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;

import okhttp3.OkHttpClient;
import org.apache.commons.lang3.StringUtils;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

/**
 * @author Kevin Veros Hamonangan <kevin.veros@dana.id>
 * @version $Id: TransferToDanaInquiryStatusTest.java, v 0.1 2025‐08‐13 10.06 kevin.veros Exp
 * $$
 */
class TransferToDanaInquiryStatusTest {

  private static final Logger log = LoggerFactory.getLogger(
      TransferToDanaInquiryStatusTest.class);

  private static final String titleCase = "TransferToDanaInquiryStatus";
  private static final String jsonPathFile = TransferToDanaInquiryStatusTest.class.getResource(
      "/request/components/Disbursement.json").getPath();
  private DisbursementApi api;

  private String partnerReferencePaid, partnerReferenceFailed;

  @BeforeEach
  void setUp() {
    DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
    danaConfigBuilder
        .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
        .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
        .origin(ConfigUtil.getConfig("ORIGIN", ""))
        .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

    DanaConfig.getInstance(danaConfigBuilder);

    api = Dana.getInstance().getDisbursementApi();
  }

  private String prepareTransferSuccessPaid() {
    TransferToDanaRequest transferToDanaRequest = TestUtil.getRequest(
            jsonPathFile, "TransferToDana", "TopUpCustomerValid", TransferToDanaRequest.class);

    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    transferToDanaRequest.setPartnerReferenceNo(originalPartnerReferenceNo);
    TransferToDanaResponse transferToDana = api.transferToDana(transferToDanaRequest);
    return originalPartnerReferenceNo;
  }

  private String prepareTransferSuccessFail() {
    TransferToDanaRequest transferToDanaRequest = TestUtil.getRequest(
            jsonPathFile, "TransferToDana", "TopUpCustomerValid", TransferToDanaRequest.class);

    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    transferToDanaRequest.setPartnerReferenceNo(originalPartnerReferenceNo);
    transferToDanaRequest.setCustomerNumber("6281298055138");
    Money feeAmount = new Money();
    feeAmount.setCurrency("IDR");
    feeAmount.setValue("1.00");
    transferToDanaRequest.setAmount(feeAmount);
    transferToDanaRequest.setFeeAmount(feeAmount);
    TransferToDanaResponse transferToDana = api.transferToDana(transferToDanaRequest);
    return originalPartnerReferenceNo;
  }

  @Test
  void testInquiryTopUpStatusValidPaid() throws IOException {
    partnerReferencePaid = prepareTransferSuccessPaid();
    String caseName = "InquiryTopUpStatusValidPaid";

    TransferToDanaInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
        caseName, TransferToDanaInquiryStatusRequest.class);

    // Assign unique reference
    requestData.setOriginalPartnerReferenceNo(partnerReferencePaid);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", partnerReferencePaid);

    TransferToDanaInquiryStatusResponse response = api.transferToDanaInquiryStatus(requestData);
    variableDict.put("originalReferenceNo", response.getOriginalReferenceNo());
    TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @Disabled
  void testInquiryTopUpStatusValidFail() throws IOException {
    partnerReferenceFailed = prepareTransferSuccessFail();
    String caseName = "InquiryTopUpStatusValidFail";

    TransferToDanaInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
            caseName, TransferToDanaInquiryStatusRequest.class);

    // Assign unique reference
    requestData.setOriginalPartnerReferenceNo(partnerReferenceFailed);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", partnerReferenceFailed);

    TransferToDanaInquiryStatusResponse response = api.transferToDanaInquiryStatus(requestData);
    variableDict.put("originalReferenceNo", response.getOriginalReferenceNo());
    TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testInquiryTopUpStatusNotFoundTransaction() throws IOException {
    String caseName = "InquiryTopUpStatusNotFoundTransaction";
    TransferToDanaInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
        caseName, TransferToDanaInquiryStatusRequest.class);

    // Assign unique reference
    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    requestData.setOriginalPartnerReferenceNo(originalPartnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", originalPartnerReferenceNo);

    TransferToDanaInquiryStatusResponse response = api.transferToDanaInquiryStatus(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testInquiryTopUpStatusInvalidFieldFormat() throws IOException {
    String caseName = "InquiryTopUpStatusInvalidFieldFormat";
    TransferToDanaInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
            caseName, TransferToDanaInquiryStatusRequest.class);

    // Assign unique reference
    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    requestData.setOriginalPartnerReferenceNo(originalPartnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", originalPartnerReferenceNo);

    TransferToDanaInquiryStatusResponse response = api.transferToDanaInquiryStatus(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testInquiryTopUpStatusMissingMandatoryField() throws IOException {
    Map<String, String> customHeaders = new HashMap<>();
    String caseName = "InquiryTopUpStatusMissingMandatoryField";
    TransferToDanaInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
            caseName, TransferToDanaInquiryStatusRequest.class);

    // Assign unique reference
    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    requestData.setOriginalPartnerReferenceNo(originalPartnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", originalPartnerReferenceNo);

    customHeaders.put(
            DanaHeader.X_TIMESTAMP,
            "");
    OkHttpClient client = new OkHttpClient.Builder()
            .addInterceptor(new DanaAuth())
            .addInterceptor(new CustomHeaderInterceptor(customHeaders))
            .build();

    DisbursementApi apiCustomHeader = new DisbursementApi(client);

    TransferToDanaInquiryStatusResponse response = apiCustomHeader.transferToDanaInquiryStatus(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testInquiryTopUpStatusUnauthorizedSignature() throws IOException {
    Map<String, String> customHeaders = new HashMap<>();
    String caseName = "InquiryTopUpStatusUnauthorizedSignature";
    TransferToDanaInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
            caseName, TransferToDanaInquiryStatusRequest.class);

    // Assign unique reference
    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    requestData.setOriginalPartnerReferenceNo(originalPartnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", originalPartnerReferenceNo);

    customHeaders.put(
            DanaHeader.X_SIGNATURE,
            "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5");
    OkHttpClient client = new OkHttpClient.Builder()
            .addInterceptor(new DanaAuth())
            .addInterceptor(new CustomHeaderInterceptor(customHeaders))
            .build();

    DisbursementApi apiCustomHeader = new DisbursementApi(client);

    TransferToDanaInquiryStatusResponse response = apiCustomHeader.transferToDanaInquiryStatus(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }
}
