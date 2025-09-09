package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.Money;
import id.dana.disbursement.v1.model.TransferToDanaRequest;
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
 * @version $Id: TransferToDanaTest.java, v 0.1 2025‐08‐13 10.06 kevin.veros Exp $$
 */
class TransferToDanaTest {

  private static final Logger log = LoggerFactory.getLogger(TransferToDanaTest.class);

  private static final String titleCase = "TransferToDana";
  private static final String jsonPathFile = TransferToDanaTest.class.getResource(
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
  void testTopUpCustomerValid() throws IOException {
    String caseName = "TopUpCustomerValid";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToDanaResponse response = api.transferToDana(requestData);
    variableDict.put("referenceNo", response.getReferenceNo());
    TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testTopUpCustomerInsufficientFund() throws IOException {
    String caseName = "TopUpCustomerInsufficientFund";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToDanaResponse response = api.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  @Disabled
  void testTopUpCustomerTimeout() throws IOException {
    String caseName = "TopUpCustomerTimeout";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToDanaResponse response = api.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testTopUpCustomerIdempotent() throws InterruptedException {
    String caseName = "TopUpCustomerIdempotent";

    int numberOfThreads = 10;
    ExecutorService executor = Executors.newFixedThreadPool(numberOfThreads);
    CountDownLatch latch = new CountDownLatch(numberOfThreads);

    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    for (int i = 0; i < numberOfThreads; i++) {
      executor.submit(() -> {
        try {
          TransferToDanaResponse response = api.transferToDana(requestData);
          TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
          System.out.println("Thread: " + Thread.currentThread().getId()
                  + " - Status: " + response.getResponseCode());
        } catch (IOException e) {
            throw new RuntimeException(e);
        } finally {
          latch.countDown();
        }
      });
    }
    // Wait for all threads to complete
    latch.await();
    executor.shutdown();
  }

  @Test
  void testTopUpCustomerFrozenAccount() throws IOException {
    String caseName = "TopUpCustomerFrozenAccount";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToDanaResponse response = api.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testTopUpCustomerExceedAmountLimit() throws IOException {
    String caseName = "TopUpCustomerExceedAmountLimit";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToDanaResponse response = api.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testTopUpCustomerMissingMandatoryField() throws IOException {
    String caseName = "TopUpCustomerMissingMandatoryField";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToDanaResponse response = api.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testTopUpCustomerUnauthorizedSignature() throws IOException {
    Map<String, String> customHeaders = new HashMap<>();
    String caseName = "TopUpCustomerUnauthorizedSignature";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

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

    TransferToDanaResponse response = apiCustomHeader.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testTopUpCustomerInvalidFieldFormat() throws IOException {
    String caseName = "TopUpCustomerInvalidFieldFormat";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToDanaResponse response = api.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testTopUpCustomerInconsistentRequest() throws IOException {
    String caseName = "TopUpCustomerInconsistentRequest";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    Money money = new Money();
    api.transferToDana(requestData);
    money.setCurrency("IDR");
    money.setValue("2000.00");
    requestData.setAmount(money);

    TransferToDanaResponse response = api.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testTopUpCustomerInternalServerError() throws IOException {
    String caseName = "TopUpCustomerInternalServerError";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToDanaResponse response = api.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testTopUpCustomerInternalGeneralError() throws IOException {
    String caseName = "TopUpCustomerInternalGeneralError";
    TransferToDanaRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            TransferToDanaRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    TransferToDanaResponse response = api.transferToDana(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }
}
