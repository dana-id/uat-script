package id.dana.interceptor;

import java.io.IOException;
import okhttp3.Interceptor;
import okhttp3.MediaType;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

/**
 * Replaces the outgoing request body before {@link id.dana.invoker.auth.DanaAuth} runs, so SNAP
 * signs the same bytes sent on the wire. Used when the SDK model cannot represent an intentionally
 * invalid payload but headers must remain normal (contrast with {@link CustomHeaderInterceptor}).
 */
public class ReplaceRequestBodyInterceptor implements Interceptor {

  private final String body;
  private final MediaType mediaType;

  public ReplaceRequestBodyInterceptor(String body, MediaType mediaType) {
    this.body = body;
    this.mediaType = mediaType;
  }

  @Override
  public Response intercept(Chain chain) throws IOException {
    Request original = chain.request();
    Request.Builder b = original.newBuilder();
    b.method(original.method(), RequestBody.create(mediaType, body));
    return chain.proceed(b.build());
  }
}
