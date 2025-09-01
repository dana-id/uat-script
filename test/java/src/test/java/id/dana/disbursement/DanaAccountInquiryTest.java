package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.disbursement.v1.api.DisbursementApi;
import id.dana.disbursement.v1.model.DanaAccountInquiryRequest;
import id.dana.disbursement.v1.model.DanaAccountInquiryResponse;
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
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

/**
 * @author Kevin Veros Hamonangan <kevin.veros@dana.id>
 * @version $Id: DanaAccountInquiryTest.java, v 0.1 2025‐08‐13 10.06 kevin.veros Exp $$
 */
class DanaAccountInquiryTest {

  private static final Logger log = LoggerFactory.getLogger(DanaAccountInquiryTest.class);

  private static final String titleCase = "DanaAccountInquiry";
  private static final String jsonPathFile = DanaAccountInquiryTest.class.getResource(
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
  void testDanaAccountInquiryCustomerValidData() throws IOException {
    String caseName = "InquiryCustomerValidData";
    DanaAccountInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        DanaAccountInquiryRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    Map<String, Object> variableDict = new HashMap<>();
    variableDict.put("partnerReferenceNo", partnerReferenceNo);

    DanaAccountInquiryResponse response = api.danaAccountInquiry(requestData);
    TestUtil.assertResponse(jsonPathFile, titleCase, caseName, response, variableDict);
  }

  @Test
  void testDanaAccountInquiryFrozenAccount() throws IOException {
    String caseName = "InquiryCustomerFrozenAccount";
    DanaAccountInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
        DanaAccountInquiryRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    DanaAccountInquiryResponse response = api.danaAccountInquiry(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, null);
  }

  @Test
  void testDanaAccountInquiryCustomerUnregisteredAccount() throws IOException {
    String caseName = "InquiryCustomerUnregisteredAccount";
    DanaAccountInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            DanaAccountInquiryRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    DanaAccountInquiryResponse response = api.danaAccountInquiry(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, null);
  }

  @Test
  void testDanaAccountInquiryCustomerExceededLimit() throws IOException {
    String caseName = "InquiryCustomerExceededLimit";
    DanaAccountInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            DanaAccountInquiryRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    DanaAccountInquiryResponse response = api.danaAccountInquiry(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, null);
  }

  @Test
  void testDanaAccountInquiryCustomerUnauthorizedSignature() throws IOException {
    Map<String, String> customHeaders = new HashMap<>();
    String caseName = "InquiryCustomerUnauthorizedSignature";
    DanaAccountInquiryRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase, caseName,
            DanaAccountInquiryRequest.class);

    // Assign unique reference
    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);

    customHeaders.put(
            DanaHeader.X_SIGNATURE,
            "85be817c55b2c135157c7e89f52499bf0c25ad6eeebe04a986e8c862561b19a5");
    OkHttpClient client = new OkHttpClient.Builder()
            .addInterceptor(new DanaAuth())
            .addInterceptor(new CustomHeaderInterceptor(customHeaders))
            .build();

    DisbursementApi apiCustomHeader = new DisbursementApi(client);

    DanaAccountInquiryResponse response = apiCustomHeader.danaAccountInquiry(requestData);
    TestUtil.assertFailResponse(jsonPathFile, titleCase, caseName, response, null);
  }
}
