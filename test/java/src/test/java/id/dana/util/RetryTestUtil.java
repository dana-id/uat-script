package id.dana.util;

import org.junit.jupiter.api.extension.*;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.lang.annotation.ElementType;
import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;
import java.lang.annotation.Target;

public class RetryTestUtil {
    private static final Logger log = LoggerFactory.getLogger(RetryTestUtil.class);

    @Retention(RetentionPolicy.RUNTIME)
    @Target(ElementType.METHOD)
    @ExtendWith(RetryExtension.class)
    public @interface Retry {
        int value() default 3;  // Default retry count
        long waitMs() default 1000;  // Default wait time between retries
    }

    static class RetryExtension implements TestExecutionExceptionHandler {
        @Override
        public void handleTestExecutionException(ExtensionContext context, Throwable throwable) throws Throwable {
            Retry retry = context.getRequiredTestMethod().getAnnotation(Retry.class);
            if (retry == null) {
                throw throwable;
            }

            int maxRetries = retry.value();
            long waitMs = retry.waitMs();
            int attempts = 1;

            while (attempts < maxRetries) {
                try {
                    log.warn("Test '{}' failed (attempt {}/{}). Retrying in {} ms...",
                            context.getTestMethod().get().getName(),
                            attempts,
                            maxRetries,
                            waitMs);
                    Thread.sleep(waitMs);

                    // Retry the test
                    context.getRequiredTestMethod().invoke(context.getRequiredTestInstance());
                    return; // Test passed, exit

                } catch (Exception e) {
                    attempts++;
                    if (attempts >= maxRetries) {
                        log.error("Test '{}' failed after {} attempts",
                                context.getTestMethod().get().getName(),
                                maxRetries);
                        throw throwable; // Throw original error if all retries failed
                    }
                }
            }
        }
    }
}