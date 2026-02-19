package id.dana.util;

import io.github.cdimascio.dotenv.Dotenv;
import org.apache.commons.lang3.StringUtils;

public final class ConfigUtil {

  private static final Dotenv DOTENV = Dotenv.configure().ignoreIfMissing().load();

  private ConfigUtil() {

  }

  public static String getConfig(String key, String defaultValue) {
    String value = TestUtil.getEnvVar(key);
    if (StringUtils.isNotEmpty(value)) {
      return value;
    }
    value = DOTENV.get(key);
    if (StringUtils.isNotEmpty(value)) {
      return value;
    }
    value = System.getenv(key);
    if (StringUtils.isNotEmpty(value)) {
      return value;
    }
    value = System.getProperty(key);
    return StringUtils.defaultIfEmpty(value, defaultValue);
  }

}
