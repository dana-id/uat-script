package id.dana.interceptor;

import java.io.IOException;
import java.util.Map;
import okhttp3.Interceptor;
import okhttp3.Request;
import okhttp3.Response;

public class CustomHeaderInterceptor implements Interceptor {

  private final Map<String, String> customHeaders;

  public CustomHeaderInterceptor(Map<String, String> customHeaders) {
    this.customHeaders = customHeaders;
  }

  @Override
  public Response intercept(Chain chain) throws IOException {
    Request request = chain.request();
    Request.Builder requestBuilder = request.newBuilder();

    for (Map.Entry<String, String> entry : customHeaders.entrySet()) {
      requestBuilder.header(entry.getKey(), entry.getValue());
    }

    return chain.proceed(requestBuilder.build());
  }

}
