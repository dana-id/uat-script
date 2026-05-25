package id.dana.disbursement;

import org.junit.jupiter.api.BeforeAll;

/**
 * Ensures merchant BNI VA top-up runs once before any disbursement test class in the JVM.
 */
public abstract class AbstractDisbursementTest {

  @BeforeAll
  static void ensureMerchantBniVaTopUp() throws Exception {
    DisbursementMerchantTopUp.ensure();
  }
}
