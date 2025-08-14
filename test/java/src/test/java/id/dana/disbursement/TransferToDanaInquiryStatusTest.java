package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.TransferToDanaInquiryStatusRequest;
import id.dana.disbursement.v1.model.TransferToDanaInquiryStatusResponse;
import id.dana.disbursement.v1.model.TransferToDanaRequest;
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
 * @version $Id: TransferToDanaInquiryStatusTest.java, v 0.1 2025‐08‐13 10.06 kevin.veros Exp
 * $$
 */
class TransferToDanaInquiryStatusTest {

  private static final Logger log = LoggerFactory.getLogger(
      TransferToDanaInquiryStatusTest.class);

  private static final String transferToDanaTitleCase = "TransferToDana";
  private static final String titleCase = "TransferToDanaInquiryStatus";
  private static final String jsonPathFile = TransferToDanaInquiryStatusTest.class.getResource(
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
  void testTransferToDanaInquiryStatus() {
    String transferToDanaCaseName = "TransferToDanaSuccessful";
    String caseName = "TransferToDanaInquiryStatusSuccessful";

    TransferToDanaRequest transferToDanaRequest = TestUtil.getRequest(jsonPathFile,
        transferToDanaTitleCase, transferToDanaCaseName, TransferToDanaRequest.class);

    TransferToDanaInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
        caseName, TransferToDanaInquiryStatusRequest.class);

    // Assign unique reference
    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    transferToDanaRequest.setPartnerReferenceNo(originalPartnerReferenceNo);
    requestData.setOriginalPartnerReferenceNo(originalPartnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", originalPartnerReferenceNo);

    try {
      api.transferToDana(transferToDanaRequest);
      TransferToDanaInquiryStatusResponse response = api.transferToDanaInquiryStatus(requestData);
      variableDict.put("originalReferenceNo", response.getOriginalReferenceNo());
      TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
    } catch (Exception e) {
      log.error("Transfer to DANA inquiry status test failed:", e);
      fail("Transfer to DANA inquiry status test failed: " + e.getMessage());
    }
  }

  @Test
  void testTransferToDanaInquiryStatusTransactionNotFound() {
    String caseName = "TransferToDanaInquiryStatusTransactionNotFound";
    TransferToDanaInquiryStatusRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
        caseName, TransferToDanaInquiryStatusRequest.class);

    // Assign unique reference
    String originalPartnerReferenceNo = UUID.randomUUID().toString();
    requestData.setOriginalPartnerReferenceNo(originalPartnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("originalPartnerReferenceNo", originalPartnerReferenceNo);

    try {
      TransferToDanaInquiryStatusResponse response = api.transferToDanaInquiryStatus(requestData);

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
      log.error("Transfer to DANA inquiry status test failed:", e);
      fail("Transfer to DANA inquiry status test failed: " + e.getMessage());
    }
  }

}
