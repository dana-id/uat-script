package helper

import (
	"bytes"
	"context"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/dana-id/dana-go/v2/merchant_management/v1"
)

const (
	danaSandboxBaseURL          = "https://api.sandbox.dana.id"
	bniVATopUpMerchantPath      = "/ifcsupergw/bni/topup/merchant/request.htm"
	defaultBNIVATopUpClientID   = "910"
	defaultBNIVATopUpKey  = "9546d5f69af2ed3bc603834446985628"
	merchantVAAccountAssetType      = "VA_ACCOUNT"
	merchantBNIVAInstID             = "BNIC1ID"
	merchantDepositAccountType      = "MERCHANT_DEPOSIT_ACCOUNT"
	merchantQueryLoginTypeRole      = "ROLE"
	merchantDepositTopUpThreshold   = int64(1_000_000)
)

var (
	merchantTopUpOnce sync.Once
	merchantTopUpErr  error
)

// EnsureMerchantBNIVATopUp tops up merchant deposit balance via BNI VA before disbursement tests.
func EnsureMerchantBNIVATopUp() error {
	merchantTopUpOnce.Do(func() {
		merchantTopUpErr = bniVATopUpMerchantOnce()
	})
	return merchantTopUpErr
}

func bniVATopUpMerchantOnce() error {
	depositBalance, err := queryMerchantDepositTotalAmount()
	if err != nil {
		return err
	}
	if depositBalance >= merchantDepositTopUpThreshold {
		fmt.Printf(
			"Skipping BNI VA top-up: merchant deposit balance=%d >= threshold=%d\n",
			depositBalance, merchantDepositTopUpThreshold,
		)
		return nil
	}
	fmt.Printf(
		"Merchant deposit balance=%d < threshold=%d; proceeding with BNI VA top-up\n",
		depositBalance, merchantDepositTopUpThreshold,
	)

	virtualAccount, err := queryBNIMerchantVirtualAccount()
	if err != nil {
		return err
	}

	now := time.Now()
	trxID := now.Format("20060102150405")
	datetimePayment := now.Format("2006-01-02 15:04:05")
	datetimePaymentISO := now.Format("2006-01-02T15:04:05-07:00")

	integrationBody := map[string]string{
		"trx_amount":                  "1000",
		"trx_id":                      trxID,
		"virtual_account":             virtualAccount,
		"customer_name":               "rudy",
		"payment_amount":              "1000000",
		"cumulative_payment_amount":   "1000",
		"payment_ntb":                 "233171",
		"datetime_payment":            datetimePayment,
		"datetime_payment_iso8601":    datetimePaymentISO,
	}

	return postBNIVATopUpMerchant(integrationBody)
}

func queryMerchantDepositTotalAmount() (int64, error) {
	merchantID := os.Getenv("MERCHANT_ID")
	if merchantID == "" {
		return 0, fmt.Errorf("MERCHANT_ID is required to query merchant info")
	}

	isQueryAccount := true
	req := merchant_management.NewQueryMerchantInfoRequest(merchantID, merchantQueryLoginTypeRole)
	req.SetIsQueryAccount(isQueryAccount)

	ctx := context.Background()
	resp, httpRes, err := ApiClient.MerchantManagementAPI.
		QueryMerchantInfo(ctx).
		QueryMerchantInfoRequest(*req).
		Execute()
	if err != nil {
		return 0, fmt.Errorf("queryMerchantInfo: %w", err)
	}
	if httpRes != nil {
		defer httpRes.Body.Close()
	}
	if resp == nil {
		return 0, fmt.Errorf("queryMerchantInfo: empty response")
	}

	responseObj := resp.GetResponse()
	body := responseObj.GetBody()
	resultInfo := body.GetResultInfo()
	if resultInfo.GetResultStatus() != "S" {
		return 0, fmt.Errorf("queryMerchantInfo failed: status=%s code=%s msg=%s",
			resultInfo.GetResultStatus(), resultInfo.GetResultCodeId(), resultInfo.GetResultMsg())
	}

	merchantInfo, ok := body.GetMerchantInformationOk()
	if !ok || merchantInfo == nil {
		return 0, fmt.Errorf("queryMerchantInfo: merchantInformation missing")
	}

	for _, account := range merchantInfo.GetAccounts() {
		if account.GetAccountType() != merchantDepositAccountType {
			continue
		}
		amount, err := parseAccountMappedTotalAmount(account)
		if err != nil {
			return 0, fmt.Errorf("parse deposit account amount: %w", err)
		}
		return amount, nil
	}

	return 0, fmt.Errorf("queryMerchantInfo: %s account not found", merchantDepositAccountType)
}

func parseAccountMappedTotalAmount(account merchant_management.MerchantAccountInfo) (int64, error) {
	// Prefer mappedTotalAmount when present (API may return it even if not in SDK struct).
	if raw, err := json.Marshal(account); err == nil {
		var extra struct {
			MappedTotalAmount *struct {
				Amount json.RawMessage `json:"amount"`
			} `json:"mappedTotalAmount"`
		}
		if err := json.Unmarshal(raw, &extra); err == nil && extra.MappedTotalAmount != nil {
			return parseAmountValue(extra.MappedTotalAmount.Amount)
		}
	}

	return parseAccountTotalAmountJSON(account.GetTotalAmount())
}

func parseAccountTotalAmountJSON(totalAmountJSON string) (int64, error) {
	if totalAmountJSON == "" {
		return 0, fmt.Errorf("empty totalAmount")
	}
	var parsed struct {
		Amount json.RawMessage `json:"amount"`
	}
	if err := json.Unmarshal([]byte(totalAmountJSON), &parsed); err != nil {
		return 0, err
	}
	return parseAmountValue(parsed.Amount)
}

func parseAmountValue(raw json.RawMessage) (int64, error) {
	if len(raw) == 0 {
		return 0, fmt.Errorf("empty amount")
	}

	var asString string
	if err := json.Unmarshal(raw, &asString); err == nil {
		return strconv.ParseInt(asString, 10, 64)
	}

	var asFloat float64
	if err := json.Unmarshal(raw, &asFloat); err == nil {
		return int64(asFloat), nil
	}

	var asInt int64
	if err := json.Unmarshal(raw, &asInt); err == nil {
		return asInt, nil
	}

	return 0, fmt.Errorf("unsupported amount format: %s", string(raw))
}

func queryBNIMerchantVirtualAccount() (string, error) {
	memberID := os.Getenv("MERCHANT_ID")
	if memberID == "" {
		return "", fmt.Errorf("MERCHANT_ID is required to query merchant BNI VA")
	}

	enableOnly := "true"
	req := merchant_management.NewQueryAssetCardListRequest(memberID)
	req.SetEnableOnly(enableOnly)
	req.SetAssetTypeList([]string{merchantVAAccountAssetType})

	ctx := context.Background()
	resp, httpRes, err := ApiClient.MerchantManagementAPI.
		QueryAssetCardList(ctx).
		QueryAssetCardListRequest(*req).
		Execute()
	if err != nil {
		return "", fmt.Errorf("queryAssetCardList: %w", err)
	}
	if httpRes != nil {
		defer httpRes.Body.Close()
	}

	if resp == nil {
		return "", fmt.Errorf("queryAssetCardList: empty response")
	}

	responseObj := resp.GetResponse()
	body := responseObj.GetBody()
	resultInfo := body.GetResultInfo()
	if resultInfo.GetResultStatus() != "S" {
		return "", fmt.Errorf("queryAssetCardList failed: status=%s code=%s msg=%s",
			resultInfo.GetResultStatus(), resultInfo.GetResultCodeId(), resultInfo.GetResultMsg())
	}

	cards := body.GetAssetCardList()
	for _, card := range cards {
		if card.AssetType == merchantVAAccountAssetType && card.InstId == merchantBNIVAInstID {
			if card.CardIndexNo != "" {
				return card.CardIndexNo, nil
			}
		}
	}

	return "", fmt.Errorf("BNI VA card not found in assetCardList (assetType=%s instId=%s)", merchantVAAccountAssetType, merchantBNIVAInstID)
}

func postBNIVATopUpMerchant(integrationBody map[string]string) error {
	clientID := envOrDefault("BNI_VA_TOP_UP_CLIENT_ID", defaultBNIVATopUpClientID)
	secretKey := envOrDefault("BNI_VA_TOP_UP_SECRET_KEY", defaultBNIVATopUpKey)

	integrationJSON, err := json.Marshal(integrationBody)
	if err != nil {
		return err
	}

	data, err := hashBNIData(string(integrationJSON), clientID, secretKey)
	if err != nil {
		return err
	}

	payload := map[string]string{
		"client_id": clientID,
		"data":      data,
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	url := danaSandboxBaseURL + bniVATopUpMerchantPath
	httpReq, err := http.NewRequest(http.MethodPost, url, bytes.NewReader(body))
	if err != nil {
		return err
	}
	httpReq.Header.Set("Content-Type", "application/json")

	client := &http.Client{Timeout: 30 * time.Second}
	httpRes, err := client.Do(httpReq)
	if err != nil {
		return fmt.Errorf("BNI VA top-up request: %w", err)
	}
	defer httpRes.Body.Close()

	respBody, _ := io.ReadAll(httpRes.Body)
	if httpRes.StatusCode < 200 || httpRes.StatusCode >= 300 {
		return fmt.Errorf("BNI VA top-up failed: status=%d body=%s", httpRes.StatusCode, string(respBody))
	}

	var parsed map[string]interface{}
	if err := json.Unmarshal(respBody, &parsed); err == nil {
		if status, ok := parsed["status"].(string); ok && status != "" && status != "000" {
			return fmt.Errorf("BNI VA top-up rejected: status=%s body=%s", status, string(respBody))
		}
	}

	return nil
}

func hashBNIData(jsonData, clientID, secretKey string) (string, error) {
	timeStr := fmt.Sprintf("%d", time.Now().UnixMilli())
	if len(timeStr) > 10 {
		timeStr = timeStr[:10]
	}
	runes := []rune(timeStr)
	for i, j := 0, len(runes)-1; i < j; i, j = i+1, j-1 {
		runes[i], runes[j] = runes[j], runes[i]
	}
	payload := string(runes) + "." + jsonData
	return doubleEncrypt(payload, clientID, secretKey), nil
}

func encrypt(data []byte, key string) []byte {
	result := make([]byte, len(data))
	keyLen := len(key)
	for i := range data {
		keyChar := key[(i+keyLen-1)%keyLen]
		result[i] = byte((int(data[i]) + int(keyChar)) % 128)
	}
	return result
}

func doubleEncrypt(input, clientID, secretKey string) string {
	encrypted := encrypt([]byte(input), clientID)
	encrypted = encrypt(encrypted, secretKey)
	encoded := base64.StdEncoding.EncodeToString(encrypted)
	encoded = strings.TrimRight(encoded, "=")
	encoded = strings.ReplaceAll(encoded, "+", "-")
	encoded = strings.ReplaceAll(encoded, "/", "_")
	return encoded
}

func envOrDefault(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
