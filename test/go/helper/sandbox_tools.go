package helper

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"time"
)

const (
	sandboxToolsExecuteURL    = "https://dashboard-sandbox.dana.id/merchant-portal-app/api/sandbox-tools/execute"
	transferVAPaymentEndpoint = "/v1.0/transfer-va/payment.htm"
)

// PaymentCodeFromCreateOrderResponse extracts additionalInfo.paymentCode (VA number) from a CreateOrder JSON body.
func PaymentCodeFromCreateOrderResponse(responseJSON string) (string, error) {
	var resp map[string]interface{}
	if err := json.Unmarshal([]byte(responseJSON), &resp); err != nil {
		return "", fmt.Errorf("parse create order response: %w", err)
	}
	additionalInfo, ok := resp["additionalInfo"].(map[string]interface{})
	if !ok || additionalInfo == nil {
		return "", fmt.Errorf("additionalInfo missing in create order response")
	}
	paymentCode, ok := additionalInfo["paymentCode"].(string)
	if !ok || paymentCode == "" {
		return "", fmt.Errorf("paymentCode missing in create order response")
	}
	return paymentCode, nil
}

// PayVirtualAccountSandbox simulates VA payment via merchant portal sandbox tools.
func PayVirtualAccountSandbox(virtualAccountNo string) error {
	payload := map[string]interface{}{
		"urlEndpoint": transferVAPaymentEndpoint,
		"requestBody": map[string]string{
			"virtualAccountNo": virtualAccountNo,
		},
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	req, err := http.NewRequest(http.MethodPost, sandboxToolsExecuteURL, bytes.NewReader(body))
	if err != nil {
		return err
	}
	req.Header.Set("accept", "application/json")
	req.Header.Set("accept-language", "en,id-ID;q=0.9,id;q=0.8,en-US;q=0.7")
	req.Header.Set("content-type", "application/json")
	req.Header.Set("origin", "https://dashboard.dana.id")
	req.Header.Set("referer", "https://dashboard.dana.id/")

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("sandbox VA payment request: %w", err)
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("sandbox VA payment failed: status=%d body=%s", resp.StatusCode, string(respBody))
	}
	return nil
}
