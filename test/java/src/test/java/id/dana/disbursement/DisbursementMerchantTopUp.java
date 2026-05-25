package id.dana.disbursement;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import id.dana.invoker.Dana;
import id.dana.invoker.model.DanaConfig;
import id.dana.invoker.model.constant.EnvKey;
import id.dana.invoker.model.enumeration.DanaEnvironment;
import id.dana.merchantmanagement.v1.api.MerchantManagementApi;
import id.dana.merchantmanagement.v1.model.QueryAssetCardListResponse;
import id.dana.merchantmanagement.v1.model.QueryMerchantInfoResponse;
import id.dana.util.BNIHashUtil;
import id.dana.util.ConfigUtil;
import id.dana.util.MerchantManagementTestHelper;
import java.io.IOException;
import java.time.ZoneId;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
import java.util.concurrent.TimeUnit;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;
import okhttp3.ResponseBody;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

/**
 * Tops up merchant deposit balance via BNI VA before disbursement integration tests.
 */
public final class DisbursementMerchantTopUp {

  private static final Logger log = LoggerFactory.getLogger(DisbursementMerchantTopUp.class);
  private static final ObjectMapper MAPPER = new ObjectMapper();

  private static final String DANA_SANDBOX_BASE_URL = "https://api.sandbox.dana.id";
  private static final String BNI_VA_TOP_UP_PATH = "/ifcsupergw/bni/topup/merchant/request.htm";

  private static final String DEFAULT_BNI_CLIENT_ID = "910";
  private static final String DEFAULT_BNI_SECRET_KEY = "9546d5f69af2ed3bc603834446985628";
  private static final String BNI_INST_ID = "BNIC1ID";
  private static final String MERCHANT_DEPOSIT_ACCOUNT_TYPE = "MERCHANT_DEPOSIT_ACCOUNT";
  private static final long MERCHANT_DEPOSIT_TOP_UP_THRESHOLD = 1_000_000L;

  private static volatile boolean done;
  private static volatile Exception failure;

  private DisbursementMerchantTopUp() {}

  public static void ensure() throws Exception {
    if (done) {
      if (failure != null) {
        throw failure;
      }
      return;
    }
    synchronized (DisbursementMerchantTopUp.class) {
      if (done) {
        if (failure != null) {
          throw failure;
        }
        return;
      }
      try {
        initDanaConfig();
        long depositBalance = queryMerchantDepositTotalAmount();
        if (depositBalance >= MERCHANT_DEPOSIT_TOP_UP_THRESHOLD) {
          log.info(
              "Skipping BNI VA top-up: merchant deposit balance={} >= threshold={}",
              depositBalance,
              MERCHANT_DEPOSIT_TOP_UP_THRESHOLD);
          return;
        }
        log.info(
            "Merchant deposit balance={} < threshold={}; proceeding with BNI VA top-up",
            depositBalance,
            MERCHANT_DEPOSIT_TOP_UP_THRESHOLD);

        String virtualAccount = queryBniMerchantVirtualAccount();
        postBniVaTopUpMerchant(virtualAccount);
        log.info("Merchant BNI VA top-up completed for virtual_account={}", virtualAccount);
      } catch (Exception e) {
        failure = e;
        throw e;
      } finally {
        done = true;
      }
    }
  }

  private static void initDanaConfig() {
    DanaConfig.Builder builder = new DanaConfig.Builder();
    builder
        .partnerId(ConfigUtil.getConfig("X_PARTNER_ID", ""))
        .privateKey(ConfigUtil.getConfig("PRIVATE_KEY", ""))
        .origin(ConfigUtil.getConfig("ORIGIN", ""))
        .clientSecret(ConfigUtil.getConfig(EnvKey.CLIENT_SECRET, ""))
        .env(DanaEnvironment.getByName(ConfigUtil.getConfig(EnvKey.ENV, "SANDBOX")));
    DanaConfig.getInstance(builder);
  }

  private static MerchantManagementApi merchantManagementApi() {
    return Dana.getInstance().getMerchantManagementApi();
  }

  private static long queryMerchantDepositTotalAmount() throws Exception {
    String merchantId = ConfigUtil.getConfig("MERCHANT_ID", "");
    if (merchantId.isEmpty()) {
      throw new IllegalStateException("MERCHANT_ID is required to query merchant info");
    }

    QueryMerchantInfoResponse response =
        merchantManagementApi().queryMerchantInfo(MerchantManagementTestHelper.queryMerchantInfoRequest());

    String status = response.getResponse().getBody().getResultInfo().getResultStatus().getValue();
    if (!"S".equals(status)) {
      throw new IOException("queryMerchantInfo failed: " + response.getResponse().getBody().getResultInfo());
    }

    if (response.getResponse().getBody().getMerchantInformation() == null
        || response.getResponse().getBody().getMerchantInformation().getAccounts() == null) {
      throw new IOException("queryMerchantInfo: merchantInformation.accounts missing");
    }

    return response.getResponse().getBody().getMerchantInformation().getAccounts().stream()
        .filter(account -> account.getAccountType() != null
            && MERCHANT_DEPOSIT_ACCOUNT_TYPE.equals(account.getAccountType().getValue()))
        .findFirst()
        .map(account -> {
          try {
            return parseAccountMappedTotalAmount(MAPPER.valueToTree(account));
          } catch (IOException e) {
            throw new IllegalStateException(e);
          }
        })
        .orElseThrow(() -> new IOException(
            "queryMerchantInfo: " + MERCHANT_DEPOSIT_ACCOUNT_TYPE + " account not found"));
  }

  private static long parseAccountMappedTotalAmount(JsonNode account) throws IOException {
    JsonNode mappedTotal = account.path("mappedTotalAmount");
    if (mappedTotal.has("amount")) {
      return parseAmountValue(mappedTotal.path("amount"));
    }

    String totalAmount = account.path("totalAmount").asText("");
    if (!totalAmount.isEmpty()) {
      JsonNode parsed = MAPPER.readTree(totalAmount);
      return parseAmountValue(parsed.path("amount"));
    }

    throw new IOException("deposit account amount missing in queryMerchantInfo response");
  }

  private static long parseAmountValue(JsonNode value) throws IOException {
    if (value.isTextual()) {
      return Long.parseLong(value.asText());
    }
    if (value.isIntegralNumber()) {
      return value.asLong();
    }
    if (value.isFloatingPointNumber()) {
      return value.asLong();
    }
    throw new IOException("unsupported amount format: " + value);
  }

  private static String queryBniMerchantVirtualAccount() throws Exception {
    String memberId = ConfigUtil.getConfig("MERCHANT_ID", "");
    if (memberId.isEmpty()) {
      throw new IllegalStateException("MERCHANT_ID is required to query merchant BNI VA");
    }

    QueryAssetCardListResponse response =
        merchantManagementApi().queryAssetCardList(MerchantManagementTestHelper.queryAssetCardListRequest(memberId));

    String status = response.getResponse().getBody().getResultInfo().getResultStatus().getValue();
    if (!"S".equals(status)) {
      throw new IOException("queryAssetCardList failed: " + response.getResponse().getBody().getResultInfo());
    }

    return response.getResponse().getBody().getAssetCardList().stream()
        .filter(card -> card.getAssetType() != null && "VA_ACCOUNT".equals(card.getAssetType().getValue()))
        .filter(card -> BNI_INST_ID.equals(card.getInstId()))
        .map(card -> card.getCardIndexNo())
        .filter(cardIndexNo -> cardIndexNo != null && !cardIndexNo.isEmpty())
        .findFirst()
        .orElseThrow(() -> new IOException("BNI VA card not found in assetCardList"));
  }

  private static void postBniVaTopUpMerchant(String virtualAccount) throws IOException {
    ZonedDateTime now = ZonedDateTime.now(ZoneId.of("Asia/Jakarta"));
    String trxId = now.format(DateTimeFormatter.ofPattern("yyyyMMddHHmmss"));
    String datetimePayment = now.format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss"));
    String datetimePaymentIso = now.format(DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ssXXX"));

    JsonNode integrationBody = MAPPER.createObjectNode()
        .put("trx_amount", "1000")
        .put("trx_id", trxId)
        .put("virtual_account", virtualAccount)
        .put("customer_name", "rudy")
        .put("payment_amount", "1000000000")
        .put("cumulative_payment_amount", "1000")
        .put("payment_ntb", "233171")
        .put("datetime_payment", datetimePayment)
        .put("datetime_payment_iso8601", datetimePaymentIso);

    String clientId = ConfigUtil.getConfig("BNI_VA_TOP_UP_CLIENT_ID", DEFAULT_BNI_CLIENT_ID);
    String secretKey = ConfigUtil.getConfig("BNI_VA_TOP_UP_SECRET_KEY", DEFAULT_BNI_SECRET_KEY);
    String integrationJson = MAPPER.writeValueAsString(integrationBody);
    String data = BNIHashUtil.hashData(integrationJson, clientId, secretKey);

    JsonNode payload = MAPPER.createObjectNode()
        .put("client_id", clientId)
        .put("data", data);

    OkHttpClient client = new OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .build();

    Request request = new Request.Builder()
        .url(DANA_SANDBOX_BASE_URL + BNI_VA_TOP_UP_PATH)
        .post(RequestBody.create(
            MediaType.parse("application/json"),
            MAPPER.writeValueAsString(payload)))
        .header("Content-Type", "application/json")
        .build();

    try (Response response = client.newCall(request).execute()) {
      ResponseBody responseBody = response.body();
      String bodyString = responseBody != null ? responseBody.string() : "";
      if (!response.isSuccessful()) {
        throw new IOException("BNI VA top-up failed: status=" + response.code() + " body=" + bodyString);
      }
      JsonNode parsed = MAPPER.readTree(bodyString);
      if (parsed.has("status") && !parsed.get("status").asText().isEmpty()
          && !"000".equals(parsed.get("status").asText())) {
        throw new IOException("BNI VA top-up rejected: " + bodyString);
      }
    }
  }
}
