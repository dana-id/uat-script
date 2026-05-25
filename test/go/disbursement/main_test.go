package disbursement_test

import (
	"os"
	"testing"
	"uat-script/helper"
)

func TestMain(m *testing.M) {
	if err := helper.EnsureMerchantBNIVATopUp(); err != nil {
		_, _ = os.Stderr.WriteString("merchant BNI VA top-up failed: " + err.Error() + "\n")
		os.Exit(1)
	}
	os.Exit(m.Run())
}
