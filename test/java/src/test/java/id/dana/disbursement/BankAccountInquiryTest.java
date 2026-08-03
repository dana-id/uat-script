package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.BankAccountInquiryRequest;
import id.dana.disbursement.v1.model.BankAccountInquiryResponse;
import id.dana.interceptor.CustomHeaderInterceptor;
import id.dana.invoker.Dana;
import id.dana.invoker.auth.DanaAuth;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.DanaHeader;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.util.ConfigUtil;
import id.dana.util.DisbursementCustomerRetry;
import id.dana.util.RetryTestUtil;
import id.dana.util.TestUtil;

import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;

import okhttp3.OkHttpClient;
import org.apache.commons.lang3.StringUtils;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

/**
 * @author Kevin Veros Hamonangan <kevin.veros@dana.id>
 * @version $Id: BankAccountInquiryTest.java, v 0.1 2025‐08-13 10.06 kevin.veros Exp $$
 */
class BankAccountInquiryTest extends AbstractDisbursementTest {

  private static final Logger log = LoggerFactory.getLogger(BankAccountInquiryTest.class);

  private static final String titleCase = "BankAccountInquiry";
  private static final String jsonPathFile = BankAccountInquiryTest.class.getResource(
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
  void testInquiryBankAccountValidDataAmount() throws Exception {
    String caseName = "InquiryBankAccountValidDataAmount";
    String partnerReferenceNo = UUID.randomUUID().toString();
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    DisbursementCustomerRetry.RetryResult<BankAccountInquiryResponse> retryResult =
        DisbursementCustomerRetry.withCustomerNumberRetry(
            customerNumber -> {
              BankAccountInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
                  caseName, BankAccountInquiryRequest.class);
              requestData.setPartnerReferenceNo(partnerReferenceNo);
              requestData.setCustomerNumber(customerNumber);
              BankAccountInquiryResponse response = api.bankAccountInquiry(requestData);
              variableDict.put("referenceNo", response.getReferenceNo());
              return response;
            },
            BankAccountInquiryResponse::getResponseCode);

    TestUtil.assertResponse(jsonPathFile, titleCase, caseName, retryResult.result(), variableDict);
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testInquiryBankAccountInsufficientFund() throws IOException {
    assertBankAccountInquiryErrorWithFixtureBody("InquiryBankAccountInsufficientFund");
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testInquiryBankAccountUnauthorizedSignature() throws IOException {
    String caseName = "InquiryBankAccountUnauthorizedSignature";
    BankAccountInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        BankAccountInquiryRequest.class);

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

      BankAccountInquiryResponse response = apiWithCustomHeader.bankAccountInquiry(requestData);
      String status = response.getResponseCode().substring(0, 3).trim();

      if (TestUtil.isSuccessful(status)) {
        log.error("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
        fail("Expected an error but the API call succeeded");
      } else if (StringUtils.equals(status, "401")) {
        variableDict.put("referenceNo", response.getReferenceNo());
        TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
      } else {
        log.error("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
        fail("Expected unauthorized failed but got status code: " + status);
      }
    } catch (Exception e) {
      log.error("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
      log.error("Bank account inquiry unauthorized signature test failed:", e);
      fail("Bank account inquiry unauthorized signature test failed: " + e.getMessage());
    }
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testInquiryBankAccountInactiveAccount() throws IOException {
    assertBankAccountInquiryErrorWithFixtureBody("InquiryBankAccountInactiveAccount");
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testInquiryBankAccountInvalidMerchant() throws IOException {
    assertBankAccountInquiryErrorWithFixtureBody("InquiryBankAccountInvalidMerchant");
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testInquiryBankAccountInvalidCard() throws IOException {
    assertBankAccountInquiryErrorWithFixtureBody("InquiryBankAccountInvalidCard");
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testInquiryBankAccountInvalidFieldFormat() throws IOException {
    String caseName = "InquiryBankAccountInvalidFieldFormat";
    BankAccountInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        BankAccountInquiryRequest.class);

    String partnerReferenceNo = UUID.randomUUID().toString();
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    BankAccountInquiryResponse response = api.bankAccountInquiry(requestData);
    variableDict.put("referenceNo", response.getReferenceNo());
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @RetryTestUtil.Retry(value = 3, waitMs = 2000)
  void testInquiryBankAccountMissingMandatoryField() throws IOException {
    assertBankAccountInquiryErrorWithFixtureBody("InquiryBankAccountMissingMandatoryField");
  }

  /**
   * Sends fixture JSON on the wire via {@link DisbursementHttpUtil} so SDK {@code customValidation}
   * runs against a valid shell request, not the intentional error payload.
   */
  private void assertBankAccountInquiryErrorWithFixtureBody(String caseName) throws IOException {
    String partnerReferenceNo = UUID.randomUUID().toString();
    log.info("[REF] case={} partnerReferenceNo={}", caseName, partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    BankAccountInquiryResponse response =
        DisbursementHttpUtil.bankAccountInquiryWithFixtureBody(
            jsonPathFile, caseName, partnerReferenceNo);
    variableDict.put("referenceNo", response.getReferenceNo());
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }
}
