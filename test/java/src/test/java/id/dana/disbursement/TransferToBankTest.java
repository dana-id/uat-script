package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.TransferToBankRequest;
import id.dana.disbursement.v1.model.TransferToBankResponse;
import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;
import org.apache.commons.lang3.StringUtils;
import org.junit.jupiter.api.BeforeEach;
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
  void testTransferToBank() {
    String caseName = "TransferToBankSuccessful";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    try {
      TransferToBankResponse response = api.transferToBank(requestData);

      String status = response.getResponseCode().substring(0, 3).trim();
      boolean isRequestInProgress = StringUtils.equals(status, "202");

      variableDict.put("responseCode", isRequestInProgress ? "2024300" : "2004300");
      variableDict.put("responseMessage", isRequestInProgress ? "Request In Progress" : "Success");
      variableDict.put("referenceNo", response.getReferenceNo());
      variableDict.put("referenceNumber", response.getReferenceNumber());
      variableDict.put("transactionDate", response.getTransactionDate());
      variableDict.put("additionalInfo", response.getAdditionalInfo());
      TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    } catch (Exception e) {
      log.error("Transfer to bank test failed:", e);
      fail("Transfer to bank test failed: " + e.getMessage());
    }
  }

  @Test
  void testTransferToBankInsufficientFund() {
    String caseName = "TransferToBankInsufficientFund";
    TransferToBankRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        TransferToBankRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    try {
      TransferToBankResponse response = api.transferToBank(requestData);

      String status = response.getResponseCode().substring(0, 3).trim();

      if (TestUtil.isSuccessful(status)) {
        fail("Expected an error but the API call succeeded");
      } else {
        if (StringUtils.equals(status, "403")) {
          variableDict.put("additionalInfo", response.getAdditionalInfo());
          TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
        } else {
          fail("Expected bad request failed but got status code: " + status);
        }
      }
    } catch (Exception e) {
      log.error("Transfer to bank test failed:", e);
      fail("Transfer to bank test failed: " + e.getMessage());
    }
  }

}
