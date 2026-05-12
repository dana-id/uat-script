package id.dana.util;

import java.lang.reflect.InvocationTargetException;
import java.lang.reflect.Method;

/**
 * Dispatches to Playwright-based helpers compiled from {@code src/test/java-browser} when the
 * {@code with-playwright} Maven profile is active. When that profile is off, those classes are absent
 * and callers get a clear {@link IllegalStateException}.
 */
public final class BrowserTestSupport {

    private BrowserTestSupport() {
    }

    public static void paymentGatewayPayOrder(String phoneNumber, String pin, String redirectUrlPay) {
        invokeVoid(
                "id.dana.paymentgateway.PaymentPGBrowser",
                "payOrder",
                new Class<?>[] {String.class, String.class, String.class},
                phoneNumber,
                pin,
                redirectUrlPay);
    }

    public static void widgetPayOrder(String phoneNumber, String pin, String redirectUrlPay) {
        invokeVoid(
                "id.dana.widget.PaymentWidgetBrowser",
                "payOrder",
                new Class<?>[] {String.class, String.class, String.class},
                phoneNumber,
                pin,
                redirectUrlPay);
    }

    public static String oauthGetOauthViaView(String urlRedirectLinkAuthCode, String phoneNumber, String pin) {
        Object out = invoke(
                "id.dana.widget.OauthBrowser",
                "getOauthViaView",
                new Class<?>[] {String.class, String.class, String.class},
                urlRedirectLinkAuthCode,
                phoneNumber,
                pin);
        return (String) out;
    }

    private static void invokeVoid(String className, String methodName, Class<?>[] paramTypes, Object... args) {
        invoke(className, methodName, paramTypes, args);
    }

    private static Object invoke(String className, String methodName, Class<?>[] paramTypes, Object... args) {
        try {
            Class<?> cl = Class.forName(className);
            Method m = cl.getMethod(methodName, paramTypes);
            return m.invoke(null, args);
        } catch (ClassNotFoundException e) {
            throw new IllegalStateException(
                    "Playwright browser helpers are not on the test classpath. "
                            + "Run Maven with default profiles (omit -P '!with-playwright') for browser automation.",
                    e);
        } catch (InvocationTargetException e) {
            Throwable t = e.getTargetException();
            if (t instanceof RuntimeException) {
                throw (RuntimeException) t;
            }
            if (t instanceof Error) {
                throw (Error) t;
            }
            throw new RuntimeException(t);
        } catch (ReflectiveOperationException e) {
            throw new RuntimeException(e);
        }
    }
}
