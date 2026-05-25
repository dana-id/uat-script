package id.dana.util;

import java.util.Base64;

public final class BNIHashUtil {

  private BNIHashUtil() {}

  public static String hashData(String jsonData, String clientId, String secretKey) {
    String time = String.valueOf(System.currentTimeMillis());
    time = time.substring(0, Math.min(time.length(), 10));
    time = new StringBuilder(time).reverse().toString();
    return doubleEncrypt(time + "." + jsonData, clientId, secretKey);
  }

  private static String doubleEncrypt(String input, String clientId, String secretKey) {
    byte[] encrypted = encrypt(input.getBytes(), clientId);
    encrypted = encrypt(encrypted, secretKey);
    return Base64.getEncoder().encodeToString(encrypted)
        .replaceAll("=+$", "")
        .replace('+', '-')
        .replace('/', '_');
  }

  private static byte[] encrypt(byte[] data, String key) {
    byte[] result = new byte[data.length];
    int keyLen = key.length();
    for (int i = 0; i < data.length; i++) {
      char keyChar = key.charAt((i + keyLen - 1) % keyLen);
      result[i] = (byte) ((data[i] + keyChar) % 128);
    }
    return result;
  }
}
