package id.dana.disbursement;

import static org.junit.jupiter.api.Assertions.fail;

import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.PaymentPGUtil;
import id.dana.paymentgateway.v1.api.PaymentGatewayApi;
import id.dana.paymentgateway.v1.model.CreateOrderByApiRequest;
import id.dana.paymentgateway.v1.model.CreateOrderResponse;
import id.dana.paymentgateway.v1.model.Money;
import id.dana.paymentgateway.v1.model.PayOptionDetail;
import id.dana.paymentgateway.v1.model.PayOptionDetail.PayMethodEnum;
import id.dana.paymentgateway.v1.model.PayOptionDetail.PayOptionEnum;
import id.dana.paymentgateway.v1.model.UrlParam;
import id.dana.util.ConfigUtil;
import id.dana.util.RetryTestUtil.Retry;
import id.dana.util.TestUtil;

import java.util.Collections;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class FinishNotifyTest extends AbstractDisbursementTest {

  private static final Logger log = LoggerFactory.getLogger(FinishNotifyTest.class);

  private static final String titleCase = "CreateOrder";
  private static final String createOrderRequestCaseFinishNotify = "CreateOrderApi";
  private static final String createOrderAssertCaseFinishNotify = "CreateOrderApi";
  private static final String notificationN8nURL =
      "https://n8n.automation.dana.id/webhook/3676a08f-b06e-416c-b6cd-bea04f71c4d5";
  private static final long finishNotifyDefaultValidUpToOffsetSeconds = 360;
  private static final long finishNotifyValidUpToOffsetExpiredSeconds = 2 * 60 + 15;
  private static final String jsonPathFile = FinishNotifyTest.class.getResource(
      "/request/components/PaymentGateway.json").getPath();
  private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");

  private PaymentGatewayApi api;

  @BeforeEach
  void setUp() {
    DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
    danaConfigBuilder
        .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
        .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
        .origin(ConfigUtil.getConfig("ORIGIN", ""))
        .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

    DanaConfig.getInstance(danaConfigBuilder);
    api = Dana.getInstance().getPaymentGatewayApi();
  }

  private void patchCreateOrderAPIForFinishNotify(CreateOrderByApiRequest requestData, String amount) {
    if (requestData.getAmount() != null) {
      requestData.getAmount().setValue(amount);
    }
    Money transAmount = new Money();
    transAmount.setValue(amount);
    transAmount.setCurrency("IDR");
    PayOptionDetail vaDetail = new PayOptionDetail();
    vaDetail.setPayMethod(PayMethodEnum.VIRTUAL_ACCOUNT);
    vaDetail.setPayOption(PayOptionEnum.VIRTUAL_ACCOUNT_CIMB);
    vaDetail.setTransAmount(transAmount);
    requestData.setPayOptionDetails(Collections.singletonList(vaDetail));
    if (requestData.getUrlParams() != null) {
      for (UrlParam u : requestData.getUrlParams()) {
        if (u != null && "NOTIFICATION".equals(u.getType())) {
          u.setUrl(notificationN8nURL);
        }
      }
    }
  }

  private Map<String, Object> createOrderAPIFinishNotifyOnce(String amount, String validUpTo) {
    CreateOrderByApiRequest requestData = PaymentPGUtil.getCreateOrderApiRequest(
        jsonPathFile, titleCase, createOrderRequestCaseFinishNotify);

    String partnerReferenceNo = UUID.randomUUID().toString();
    requestData.setPartnerReferenceNo(partnerReferenceNo);
    requestData.setMerchantId(merchantId);
    if (validUpTo != null && !validUpTo.isEmpty()) {
      requestData.setValidUpTo(validUpTo);
    } else {
      requestData.setValidUpTo(PaymentPGUtil.generateDateWithOffsetSeconds(
          finishNotifyDefaultValidUpToOffsetSeconds));
    }
    patchCreateOrderAPIForFinishNotify(requestData, amount);

    try {
      CreateOrderResponse response = api.createOrder(requestData);
      Map<String, Object> result = new HashMap<>();
      result.put("partnerReferenceNo", partnerReferenceNo);
      result.put("response", response);
      return result;
    } catch (Exception e) {
      throw new RuntimeException(e);
    }
  }

  @Test
  @Retry(value = 3, waitMs = 2000)
  void testTransactionSuccessNotify() {
    runFinishNotifyTest("11011.00", "", true);
  }

  @Test
  @Retry(value = 3, waitMs = 2000)
  void testInternalServerErrorNotify() {
    runFinishNotifyTest("11012.00", "", true);
  }

  @Test
  @Retry(value = 3, waitMs = 2000)
  void testExpiredNotify() {
    String validUpTo = PaymentPGUtil.generateDateWithOffsetSeconds(
        finishNotifyValidUpToOffsetExpiredSeconds);
    runFinishNotifyTest("11013.00", validUpTo, false);
  }

  private void runFinishNotifyTest(String amount, String validUpTo, boolean payVA) {
    try {
      Map<String, Object> result = createOrderAPIFinishNotifyOnce(amount, validUpTo);
      String partnerReferenceNo = (String) result.get("partnerReferenceNo");
      CreateOrderResponse response = (CreateOrderResponse) result.get("response");

      Map<String, Object> variableDict = new HashMap<>();
      variableDict.put("partnerReferenceNo", partnerReferenceNo);

      TestUtil.assertResponse(jsonPathFile, titleCase, createOrderAssertCaseFinishNotify, response, variableDict);

      if (payVA) {
        PaymentPGUtil.payVirtualAccountSandbox(
            PaymentPGUtil.paymentCodeFromCreateOrderResponse(response));
      }
    } catch (Exception e) {
      log.error("Finish notify create order test failed:", e);
      fail("Finish notify create order test failed: " + e.getMessage());
    }
  }
}
