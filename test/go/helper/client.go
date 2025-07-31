package helper

import (
	"os"
	"sync"

	dana "github.com/dana-id/dana-go"
	"github.com/dana-id/dana-go/config"
)

var (
	// ApiInstance is a singleton instance of the PaymentGatewayApi
	ApiClient *dana.APIClient
	once      sync.Once
)

func init() {
	once.Do(func() {
		// Initialize the SDK client once for all tests
		configuration := config.NewConfiguration()

		// Set API keys
		configuration.APIKey = &config.APIKey{
			ENV:           config.ENV_SANDBOX,
			X_PARTNER_ID:  os.Getenv("X_PARTNER_ID"),
			CHANNEL_ID:    os.Getenv("CHANNEL_ID"),
			PRIVATE_KEY:   os.Getenv("PRIVATE_KEY"),
			ORIGIN:        os.Getenv("ORIGIN"),
			CLIENT_SECRET: os.Getenv("CLIENT_SECRET"),
		}

		// Create API client with config
		ApiClient = dana.NewAPIClient(configuration)
	})
}
