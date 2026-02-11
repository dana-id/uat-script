module uat-script

go 1.22

toolchain go1.22.4

// Direct dependencies (v2 module path for proxy.golang.org)
require (
	github.com/dana-id/dana-go/v2 v2.0.0
	github.com/google/uuid v1.6.0
)

require (
	// github.com/dana-id/go_client v0.1.12 // indirect
	github.com/mitchellh/mapstructure v1.5.0 // indirect
	gopkg.in/validator.v2 v2.0.1 // indirect
)

require github.com/playwright-community/playwright-go v0.5200.1

require (
	github.com/deckarep/golang-set/v2 v2.8.0 // indirect
	github.com/go-jose/go-jose/v3 v3.0.4 // indirect
	github.com/go-stack/stack v1.8.1 // indirect
)
