package id.dana.util;

import id.dana.invoker.model.exception.DanaException;
import java.util.Arrays;
import java.util.Collections;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public final class DisbursementCustomerRetry {

  public static final List<String> CUSTOMER_NUMBERS = Collections.unmodifiableList(
      Arrays.asList(
          "62811742234",
          "62817345544",
          "62817345545"
      )
  );

  private static final Pattern RESPONSE_CODE_PATTERN =
      Pattern.compile("\"responseCode\"\\s*:\\s*\"((?:403|404)[^\"]*)\"");

  private DisbursementCustomerRetry() {}

  public static boolean isForbiddenResponseCode(String code) {
    return code != null && (code.startsWith("403") || code.startsWith("404"));
  }

  public static boolean isForbiddenException(Throwable exception) {
    String message = exception.getMessage();
    if (message == null) {
      return false;
    }
    Matcher matcher = RESPONSE_CODE_PATTERN.matcher(message);
    if (matcher.find()) {
      return isForbiddenResponseCode(matcher.group(1));
    }
    return message.contains("403") || message.contains("404");
  }

  @FunctionalInterface
  public interface CustomerNumberOperation<T> {
    T apply(String customerNumber) throws Exception;
  }

  @FunctionalInterface
  public interface ResponseCodeExtractor<T> {
    String extract(T result);
  }

  public static <T> RetryResult<T> withCustomerNumberRetry(
      CustomerNumberOperation<T> operation,
      ResponseCodeExtractor<T> responseCodeExtractor
  ) throws Exception {
    Exception lastException = null;
    for (String customerNumber : CUSTOMER_NUMBERS) {
      try {
        T result = operation.apply(customerNumber);
        String responseCode = responseCodeExtractor.extract(result);
        if (isForbiddenResponseCode(responseCode)) {
          lastException = new DanaException("responseCode=" + responseCode);
          continue;
        }
        return new RetryResult<>(result, customerNumber);
      } catch (Exception exception) {
        if (isForbiddenException(exception)) {
          lastException = exception;
          continue;
        }
        throw exception;
      }
    }
    if (lastException != null) {
      throw lastException;
    }
    throw new IllegalStateException("All customer numbers returned 403/404");
  }

  public static final class RetryResult<T> {
    private final T result;
    private final String customerNumber;

    public RetryResult(T result, String customerNumber) {
      this.result = result;
      this.customerNumber = customerNumber;
    }

    public T result() {
      return result;
    }

    public String customerNumber() {
      return customerNumber;
    }
  }
}
