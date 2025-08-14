package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.TransferToBankInquiryStatusRequest;
import id.dana.disbursement.v1.model.TransferToBankInquiryStatusResponse;
import id.dana.disbursement.v1.model.TransferToBankRequest;
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
 * @version $Id: TransferToBankInquiryStatusTest.java, v 0.1 2025‐08‐13 10.06 kevin.veros Exp
 * $$
 */
class TransferToBankInquiryStatusTest {

  private static final Logger log = LoggerFactory.getLogger(
      TransferToBankInquiryStatusTest.class);

  private static final String transferToBankTitleCase = "TransferToBank";
  private static final String titleCase = "TransferToBankInquiryStatus";
  private static final String jsonPathFile = TransferToBankInquiryStatusTest.class.getResource(
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
  void testTransferToBankInquiryStatus() {
    String transferToBankCaseName = "TransferToBankSuccessful";
    String caseName = "TransferToBankInquiryStatusSuccessful";

    TransferToBankRequest transferToBankRequest = TestUtil.getRequest(jsonPathFile,
        transferToBankTitleCase, transferToBankCaseName, TransferToBankRequest.class);

    TransferToBankInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
        caseName, TransferToBankInquiryStatusRequest.class);

    // Assign unique reference
    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    transferToBankRequest.setPartnerReferenceNo(originalPartnerReferenceNo);
    requestData.setOriginalPartnerReferenceNo(originalPartnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", originalPartnerReferenceNo);

    try {
      api.transferToBank(transferToBankRequest);
      TransferToBankInquiryStatusResponse response = api.transferToBankInquiryStatus(requestData);
      variableDict.put("originalReferenceNo", response.getOriginalReferenceNo());
      variableDict.put("latestTransactionStatus", response.getLatestTransactionStatus());
      variableDict.put("transactionStatusDesc", response.getTransactionStatusDesc());
      TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    } catch (Exception e) {
      log.error("Transfer to bank inquiry status test failed:", e);
      fail("Transfer to bank inquiry status test failed: " + e.getMessage());
    }
  }

  @Test
  void testTransferToBankInquiryStatusTransactionNotFound() {
    String caseName = "TransferToBankInquiryStatusTransactionNotFound";
    TransferToBankInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
        caseName, TransferToBankInquiryStatusRequest.class);

    // Assign unique reference
    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    requestData.setOriginalPartnerReferenceNo(originalPartnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", originalPartnerReferenceNo);

    try {
      TransferToBankInquiryStatusResponse response = api.transferToBankInquiryStatus(requestData);

      String status = response.getResponseCode().substring(0, 3).trim();

      if (TestUtil.isSuccessful(status)) {
        fail("Expected an error but the API call succeeded");
      } else {
        if (StringUtils.equals(status, "404")) {
          TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, variableDict);
        } else {
          fail("Expected bad request failed but got status code: " + status);
        }
      }
    } catch (Exception e) {
      log.error("Transfer to bank inquiry status test failed:", e);
      fail("Transfer to bank inquiry status test failed: " + e.getMessage());
    }
  }

}
