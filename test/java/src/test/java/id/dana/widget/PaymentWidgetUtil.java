package id.dana.widget;

import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.paymentgateway.CreateOrderTest;
import id.dana.paymentgateway.PaymentPGUtil;
import id.dana.util.BrowserTestSupport;
import id.dana.util.ConfigUtil;
import id.dana.util.TestUtil;
import id.dana.widget.v1.api.WidgetApi;
import id.dana.widget.v1.model.*;
import org.junit.jupiter.api.Assertions;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.io.IOException;
import java.util.*;

public class PaymentWidgetUtil {
    private static final String titleCase = "Payment";
    private static final Logger log = LoggerFactory.getLogger(CreateOrderTest.class);
    private static final String jsonPathFile = PaymentTest.class.getResource(
            "/request/components/Widget.json").getPath();
    private static WidgetApi widgetApi;
    private static final String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "216620010016033632482");

    public static void payOrder(String phoneNumber, String pin, String redirectUrlPay) {
        BrowserTestSupport.widgetPayOrder(phoneNumber, pin, redirectUrlPay);
    }

    public static List<String> createPayment(String paymentOrigin) {
        List<String> dataOrder = new ArrayList<>();

        DanaConfig.Builder danaConfigBuilder = new DanaConfig.Builder();
        danaConfigBuilder
                .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
                .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
                .origin(ConfigUtil.getConfig("ORIGIN", ""))
                .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));

        DanaConfig.getInstance(danaConfigBuilder);

        widgetApi = Dana.getInstance().getWidgetApi();

        WidgetPaymentRequest requestData = TestUtil.getRequest(jsonPathFile, titleCase,
                paymentOrigin, WidgetPaymentRequest.class);

        // Assign unique reference and merchant ID
        String partnerReferenceNo = UUID.randomUUID().toString();
        requestData.setPartnerReferenceNo(partnerReferenceNo);
        requestData.setMerchantId(merchantId);
        requestData.setValidUpTo(PaymentPGUtil.generateDateWithOffset(30));

        Map<String, Object> variableDict = new HashMap<>();
        variableDict.put("partnerReferenceNo", partnerReferenceNo);

        WidgetPaymentResponse response = widgetApi.widgetPayment(requestData);
        Assertions.assertTrue(response.getResponseCode().contains("200"),
                "Response code is not 200, actual: " + response.getResponseCode());

        //Index 0 is partnerReferenceNo
        dataOrder.add(partnerReferenceNo);
        //Index 1 is the web redirect URL
        dataOrder.add(response.getWebRedirectUrl());

        return dataOrder;
    }
}
