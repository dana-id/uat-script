package id.dana.util;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.databind.node.ArrayNode;
import com.fasterxml.jackson.databind.node.ObjectNode;
import io.restassured.path.json.JsonPath;
import io.restassured.response.Response;
import java.io.File;
import java.io.IOException;
import java.util.ArrayList;
import java.util.Iterator;
import java.util.List;
import java.util.Map;
import java.util.Objects;
import org.apache.commons.collections4.CollectionUtils;
import org.apache.commons.collections4.MapUtils;
import org.apache.commons.lang3.StringUtils;
import org.junit.jupiter.api.Assertions;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public final class TestUtil {

  private static final Logger log = LoggerFactory.getLogger(TestUtil.class);

  private static final ObjectMapper objectMapper = new ObjectMapper();

  public static <T> T getRequest(String jsonPathFile, String title, String caseName,
      Class<T> clazz) {
    return getData(jsonPathFile, title, caseName, "request", clazz);
  }

  public static <T> T getResponse(String jsonPathFile, String title, String caseName,
      Class<T> clazz) {
    return getData(jsonPathFile, title, caseName, "response", clazz);
  }

  private static <T> T getData(String jsonPathFile, String title, String caseName, String nodeKey,
      Class<T> clazz) {
    try {
      JsonNode jsonData = objectMapper.readTree(new File(jsonPathFile));
      JsonNode requestNode = jsonData.path(title).path(caseName).path(nodeKey);
      return objectMapper.treeToValue(requestNode, clazz);
    } catch (IOException e) {
      log.error("Error reading {} data from {}: {}", nodeKey, jsonPathFile, e.getMessage());
      try {
        return clazz.getDeclaredConstructor().newInstance();
      } catch (Exception ex) {
        throw new RuntimeException("Failed to create instance of " + clazz.getName(), ex);
      }
    }
  }

  public static void compareJsonObjects(JsonNode expected, JsonNode actual, String currentPath,
      List<Difference> diffPaths) {
    // Special server value
    if (expected.isTextual() && "${valueFromServer}".equals(expected.asText())) {
      if (actual == null || actual.isNull()) {
        diffPaths.add(new Difference(currentPath, expected, actual));
      }
      return;
    }

    if (expected == null || actual == null) {
      if (!Objects.equals(expected, actual)) {
        diffPaths.add(new Difference(currentPath, expected, actual));
      }
      return;
    }

    if (!expected.getNodeType().equals(actual.getNodeType())) {
      diffPaths.add(new Difference(currentPath, expected, actual));
      return;
    }

    if (expected.isArray()) {
      if (!actual.isArray() || expected.size() != actual.size()) {
        diffPaths.add(new Difference(currentPath, expected, actual));
        return;
      }
      for (int i = 0; i < expected.size(); i++) {
        compareJsonObjects(expected.get(i), actual.get(i), currentPath + "[" + i + "]", diffPaths);
      }
      return;
    }

    if (expected.isObject()) {
      Iterator<String> fieldNames = expected.fieldNames();
      while (fieldNames.hasNext()) {
        String key = fieldNames.next();
        String newPath = StringUtils.isEmpty(currentPath) ? key : currentPath + "." + key;
        if (!actual.has(key)) {
          diffPaths.add(new Difference(newPath, expected.get(key), null));
          continue;
        }
        compareJsonObjects(expected.get(key), actual.get(key), newPath, diffPaths);
      }
      return;
    }

    if (!expected.equals(actual)) {
      diffPaths.add(new Difference(currentPath, expected, actual));
    }
  }

  public static JsonNode replaceVariables(JsonNode data, Map<String, Object> variableDict) {
    if (MapUtils.isEmpty(variableDict)) {
      return data;
    }
    if (data.isArray()) {
      ArrayNode result = objectMapper.createArrayNode();
      for (JsonNode item : data) {
        result.add(replaceVariables(item, variableDict));
      }
      return result;
    } else if (data.isObject()) {
      Iterator<Map.Entry<String, JsonNode>> fields = data.fields();
      ObjectNode result = objectMapper.createObjectNode();
      while (fields.hasNext()) {
        Map.Entry<String, JsonNode> entry = fields.next();
        result.set(entry.getKey(), replaceVariables(entry.getValue(), variableDict));
      }
      return result;
    } else if (data.isTextual()) {
      String text = data.asText();
      if (text.startsWith("${") && text.endsWith("}")) {
        String key = text.substring(2, text.length() - 1);
        if (variableDict.containsKey(key)) {
          return objectMapper.valueToTree(variableDict.get(key));
        }
      }
      return data;
    }
    return data;
  }

  public static <T> boolean assertFailResponse(String jsonPathFile, String title, String caseName,
      T errorBody, Map<String, Object> variableDict) throws IOException {
    return processAssertResponse(jsonPathFile, title, caseName, errorBody, variableDict,
        "error response");
  }

  public static <T> boolean assertResponse(String jsonPathFile, String title, String caseName,
      T responseBody, Map<String, Object> variableDict) throws IOException {
    return processAssertResponse(jsonPathFile, title, caseName, responseBody, variableDict,
        "response");
  }

  private static <T> boolean processAssertResponse(String jsonPathFile, String title, String data,
      T responseBody, Map<String, Object> variableDict, String bodyType) throws IOException {
    JsonNode expectedData = getResponse(jsonPathFile, title, data, JsonNode.class);
    JsonNode processedExpectedData = replaceVariables(expectedData, variableDict);

    JsonNode actualResponse;

    log.info("Processing response\n");

    if (responseBody instanceof String) {
      actualResponse = objectMapper.readTree((String) responseBody);
    } else if (responseBody instanceof JsonNode) {
      actualResponse = (JsonNode) responseBody;
    } else {
      actualResponse = objectMapper.valueToTree(responseBody);
    }

    List<Difference> diffPaths = new ArrayList<>();
    compareJsonObjects(processedExpectedData, actualResponse, "", diffPaths);

    if (CollectionUtils.isNotEmpty(diffPaths)) {
      StringBuilder errorMsg = new StringBuilder("Assertion failed. Differences found in ").append(
          bodyType).append(":\n");
      for (Difference diff : diffPaths) {
        errorMsg.append("Path: ").append(diff.path).append("\n").append("\tExpected: ")
            .append(diff.expected).append("\n").append("\tActual: ").append(diff.actual)
            .append("\n");
      }
      throw new AssertionError(errorMsg.toString());
    }
    log.info("Assertion passed \n Actual Response: \n{} \nExpected Response: \n{}",
            actualResponse, processedExpectedData);

    return true;
  }

  public static void assertResponse(String jsonPathFile, Response response, String title) {
    JsonPath jsonPath = JsonPath.from(new File(jsonPathFile));
    Map<String, String> assertion = jsonPath.get(title + ".response");

    log.info("Assertion: {}", assertion);
    log.info("Response: {}", response.getBody().asString());

    Assertions.assertTrue(response.getBody().asString().contains(assertion.get("responseCode")),
        "Response does not contain expected assertion: " + assertion.toString()
            + "\nActual response: " + response.getBody().asString());

    Assertions.assertTrue(response.getBody().asString().contains(assertion.get("responseMessage")),
        "Response does not contain expected assertion: " + assertion.toString()
            + "\nActual response: " + response.getBody().asString());
  }

  public static class Difference {

    public final String path;
    public final JsonNode expected;
    public final JsonNode actual;

    public Difference(String path, JsonNode expected, JsonNode actual) {
      this.path = path;
      this.expected = expected;
      this.actual = actual;
    }

  }

  public static boolean isUnsuccessful(String status) {
    return !isSuccessful(status);
  }

  public static boolean isSuccessful(String status) {
    if (status == null || StringUtils.length(status) != 3) {
      return false;
    }
    int code;
    try {
      code = Integer.parseInt(status);
    } catch (NumberFormatException e) {
      return false;
    }
    return code >= 200 && code < 300;
  }

  public static void delay(int timeout) {
    try {
      Thread.sleep(timeout);
    } catch (InterruptedException ie) {
      Thread.currentThread().interrupt();
    }
  }

}
