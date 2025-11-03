package helper

import (
	"bufio"
	"os"
	"path/filepath"
	"strings"
	"sync"

	dana "github.com/dana-id/dana-go"
	"github.com/dana-id/dana-go/config"
)

var (
	// ApiInstance is a singleton instance of the PaymentGatewayApi
	ApiClient *dana.APIClient
	once      sync.Once
)

// loadEnvFile loads environment variables from .env file if they're not already set
func loadEnvFile() {
	// Try to find .env file in current directory or up to 3 levels up
	envPaths := []string{
		".env",
		"../.env",
		"../../.env",
		"../../../.env",
	}

	var envFile string
	for _, path := range envPaths {
		if absPath, err := filepath.Abs(path); err == nil {
			if _, err := os.Stat(absPath); err == nil {
				envFile = absPath
				break
			}
		}
	}

	if envFile == "" {
		return // No .env file found
	}

	file, err := os.Open(envFile)
	if err != nil {
		return
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}

		parts := strings.SplitN(line, "=", 2)
		if len(parts) != 2 {
			continue
		}

		key := strings.TrimSpace(parts[0])
		value := strings.TrimSpace(parts[1])

		// Remove quotes if present
		if (strings.HasPrefix(value, "'") && strings.HasSuffix(value, "'")) ||
			(strings.HasPrefix(value, "\"") && strings.HasSuffix(value, "\"")) {
			value = value[1 : len(value)-1]
		}

		// Only set if not already set
		if os.Getenv(key) == "" {
			os.Setenv(key, value)
		}
	}
}

func init() {
	once.Do(func() {
		// Load .env file if environment variables are not set
		loadEnvFile()

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
