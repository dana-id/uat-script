package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.Money;
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
class TransferToBankTest {

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
  void testDisbursementBankValidAccount() throws IOException {
    String caseName = "DisbursementBankValidAccount";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankInsufficientFund() throws IOException {
    String caseName = "DisbursementBankInsufficientFund";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankValidAccountInProgress() throws IOException {
    String caseName = "DisbursementBankValidAccountInProgress";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankInactiveAccount() throws IOException {
    String caseName = "DisbursementBankInactiveAccount";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankUnauthorizedSignature() throws IOException {
    Map<String, String> customHeaders = new HashMap<>();
    String caseName = "DisbursementBankUnauthorizedSignature";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    customHeaders.put(
            DanaHeader.X_SIGNATURE,
            "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5");
    OkHttpClient client = new OkHttpClient.Builder()
            .addInterceptor(new DanaAuth())
            .addInterceptor(new CustomHeaderInterceptor(customHeaders))
            .build();

    DisbursementApi apiCustomHeader = new DisbursementApi(client);

    TransferToBankResponse response = apiCustomHeader.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankMissingMandatoryField() throws IOException {
    String caseName = "DisbursementBankMissingMandatoryField";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankInvalidFieldFormat() throws IOException {
    String caseName = "DisbursementBankInvalidFieldFormat";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankInconsistentRequest() throws IOException {
    String caseName = "DisbursementBankInconsistentRequest";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    api.transferToBank(requestData);
    Money money = new Money();
    money.setCurrency("IDR");
    money.setValue("2000.00");
    requestData.setAmount(money);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankSuspectedFraud() throws IOException {
    String caseName = "DisbursementBankSuspectedFraud";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankGeneralError() throws IOException {
    String caseName = "DisbursementBankGeneralError";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDisbursementBankUnknownError() throws IOException {
    String caseName = "DisbursementBankUnknownError";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToBankResponse response = api.transferToBank(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }
}