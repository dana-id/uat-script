package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import com.fasterxml.jackson.databind.node.ObjectNode;
import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.TransferToBankRequest;
import id.dana.disbursement.v1.model.TransferToBankResponse;
import id.dana.disbursement.v1.model.TransferToDanaResponse;
import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.util.ConfigUtil;
import id.dana.util.RetryTestUtil;
import id.dana.util.TestUtil;

import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import okhttp3.OkHttpClient;
import org.apache.commons.lang3.StringUtils;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

/**
 * @author Kevin Veros Hamonangan <kevin.veros@dana.id>
 * @version $Id: TransferToBankTest.java, v 0.1 2025‐08‐13 10.06 kevin.veros Exp $$
 */
class TransferToBankTest extends AbstractDisbursementTest {

  private static final Logger log = LoggerFactory.getLogger(TransferToBankTest.class);

  private static final String titleCase = "TransferToBank";
  private static final String jsonPathFile = TransferToBankTest.class.getResource(
      "/request/components/Disbursement.json").getPath();
  private DisbursementApi api;

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

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankValidAccount() throws IOException {
    String caseName = "DisbursementBankValidAccount";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
 log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankInsufficientFund() throws IOException {
    String caseName = "DisbursementBankInsufficientFund";
    String partnerReferenceNo = UUID.randomUUID().toString();
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response =
        DisbursementHttpUtil.transferToBankWithFixtureBody(jsonPathFile, caseName, partnerReferenceNo);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankValidAccountInProgress() throws IOException {
    String caseName = "DisbursementBankValidAccountInProgress";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
 log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankInactiveAccount() throws IOException {
    assertTransferToBankErrorWithFixtureBody("DisbursementBankInactiveAccount");
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankUnauthorizedSignature() throws IOException {
    String caseName = "DisbursementBankUnauthorizedSignature";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
        "DisbursementBankValidAccount", TransferToBankRequest.class);

    String partnerReferenceNo = UUID.randomUUID().toString();
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    try {
      Map<String, String> customHeaders = new HashMap<>();
      customHeaders.put(
          DanaHeader.X_SIGNATURE,
          "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5");
      OkHttpClient client = new OkHttpClient.Builder()
          .addInterceptor(new DanaAuth())
          .addInterceptor(new CustomHeaderInterceptor(customHeaders))
          .build();
      DisbursementApi apiWithCustomHeader = new DisbursementApi(client);

      TransferToBankResponse response = apiWithCustomHeader.transferToBank(requestData);
      String status = response.getResponseCode().substring(0, 3).trim();

      if (TestUtil.isSuccessful(status)) {
        log.error("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
        fail("Expected an error but the API call succeeded");
      } else if (StringUtils.equals(status, "401")) {
        TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
      } else {
        log.error("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
        fail("Expected unauthorized failed but got status code: " + status);
      }
    } catch (Exception e) {
      log.error("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
      log.error("Transfer to bank unauthorized signature test failed:", e);
      fail("Transfer to bank unauthorized signature test failed: " + e.getMessage());
    }
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankInvalidMandatoryFieldFormat() throws IOException {
    Map<String, String> customHeaders = new HashMap<>();
    String caseName = "DisbursementBankInvalidMandatoryFieldFormat";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
 log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    customHeaders.put(
            DanaHeader.X_SIGNATURE,
            "");
    OkHttpClient client = new OkHttpClient.Builder()
            .addInterceptor(new DanaAuth())
            .addInterceptor(new CustomHeaderInterceptor(customHeaders))
            .build();

    DisbursementApi apiCustomHeader = new DisbursementApi(client);

    TransferToBankResponse response = apiCustomHeader.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankMissingMandatoryField() throws IOException {
    String caseName = "DisbursementBankMissingMandatoryField";
    String partnerReferenceNo = UUID.randomUUID().toString();
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response =
        DisbursementHttpUtil.transferToBankWithFixtureBody(jsonPathFile, caseName, partnerReferenceNo);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankInvalidFieldFormat() throws IOException {
    String caseName = "DisbursementBankInvalidFieldFormat";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
 log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankInconsistentRequest() throws IOException {
    String caseName = "DisbursementBankInconsistentRequest";
    String partnerReferenceNo = UUID.randomUUID().toString();
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    DisbursementHttpUtil.transferToBankWithFixtureBody(jsonPathFile, caseName, partnerReferenceNo);

    ObjectNode bodyNode =
        (ObjectNode) DisbursementHttpUtil.getRawRequest(jsonPathFile, titleCase, caseName);
    bodyNode.put("partnerReferenceNo", partnerReferenceNo);
    ((ObjectNode) bodyNode.get("amount")).put("value", "2000.00");
    String payload = DisbursementHttpUtil.compactJsonForSnap(bodyNode);
    TransferToBankResponse response =
        DisbursementHttpUtil.transferToBankWithPayload(jsonPathFile, payload, partnerReferenceNo);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankSuspectedFraud() throws IOException {
    assertTransferToBankErrorWithFixtureBody("DisbursementBankSuspectedFraud");
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankGeneralError() throws IOException {
    assertTransferToBankErrorWithFixtureBody("DisbursementBankGeneralError");
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testDisbursementBankUnknownError() throws IOException {
    assertTransferToBankErrorWithFixtureBody("DisbursementBankUnknownError");
  }

  private void assertTransferToBankErrorWithFixtureBody(String caseName) throws IOException {
    String partnerReferenceNo = UUID.randomUUID().toString();
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response =
        DisbursementHttpUtil.transferToBankWithFixtureBody(
            jsonPathFile, caseName, partnerReferenceNo);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }
}