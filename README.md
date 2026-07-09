# DANA Self Testing Scenario

Automated UAT scripts for the DANA Sandbox. Run them with your testing credentials, then check progress in **Merchant Portal → Mandatory API Testing**.

Supported languages: **Python**, **Go**, **Node**, **PHP**, **Java**

Client library docs: [Python](https://github.com/dana-id/dana-python) · [Go](https://github.com/dana-id/dana-go) · [Node](https://github.com/dana-id/dana-node) · [PHP](https://github.com/dana-id/dana-php) · [Java](https://github.com/dana-id/dana-java)

---

## Quick start

**1. Clone and configure**

```bash
git clone git@github.com:dana-id/self_testing_scenario.git
cd self_testing_scenario
cp .env-example .env
# Edit .env with your Sandbox credentials from Merchant Portal
```

**2. Pick a language** (examples below use `python` — swap for `go`, `node`, `php`, or `java`)

**3. Run mandatory scenarios** for the product you are integrating (see tables below)

```bash
./run-test.sh python payment_gateway
```

Mandatory runs retry failed tests automatically (up to 5 attempts, failed tests only).

---

## Command format

```bash
./run-test.sh <language> [module] [test_case]
```

| Part | Required | Description |
|------|----------|-------------|
| `language` | Yes | `python` · `go` · `node` · `php` · `java` |
| `module` | No | Business module folder (see tables) |
| `test_case` | No | Single API / test file for **additional** scenarios |

**Examples**

```bash
./run-test.sh python                          # All modules — mandatory scenarios only
./run-test.sh python payment_gateway          # Payment Gateway — mandatory only
./run-test.sh python payment_gateway consult_pay_test   # One additional API
./run-test.sh list python payment_gateway     # List available test cases
./run-test.sh help                            # Full command reference
```

> **Java folder names** use no underscore: `paymentgateway`, `widget`, `disbursement`  
> Example: `./run-test.sh java paymentgateway CreateOrderTest`

---

## Merchant Portal → commands

Match the scenario in your portal to the command below. Replace `python` with your language.

### Payment Gateway

#### Mandatory (required for production credentials)

| Portal scenario | Progress | Command |
|-----------------|----------|---------|
| Payment Gateway Payment **(mandatory)** | 0 of 5 | `./run-test.sh python payment_gateway` |
| General Payment finish notify **(mandatory)** | 0 of 3 | *(included in the command above)* |

#### Additional (recommended)

| Portal scenario | Command |
|-----------------|---------|
| Payment Gateway Consult Pay | `./run-test.sh python payment_gateway consult_pay_test` |
| Payment Gateway Debit Status | `./run-test.sh python payment_gateway query_payment_test` |
| Payment Gateway Refund Order | `./run-test.sh python payment_gateway refund_order_test` |
| Payment Gateway Cancel Order | `./run-test.sh python payment_gateway cancel_order_test` |

---

### Widget

#### Mandatory

| Portal scenario | Progress | Command |
|-----------------|----------|---------|
| Direct Debit Payment – Cashier Pay **(mandatory)** | 0 of 7 | `./run-test.sh python widget` |
| General Payment finish notify **(mandatory)** | 0 of 3 | *(included in the command above)* |

#### Additional

| Portal scenario | Command |
|-----------------|---------|
| Apply OTT | `./run-test.sh python widget apply_ott_test` |
| Account Unbinding | `./run-test.sh python widget account_unbinding_test` |
| Query Order Widget | `./run-test.sh python widget query_order_test` |
| Apply Token B2B2C | `./run-test.sh python widget apply_token_test` |
| Transaction History List | `./run-test.sh python widget transaction_list_test` |
| Get OAuth 2.0 | `./run-test.sh python widget get_auth_2_test` |
| Cancel Order | `./run-test.sh python widget cancel_order_test` |
| Balance Inquiry | `./run-test.sh python widget balance_inquiry_test` |
| Refund Order | `./run-test.sh python widget refund_order_test` |

---

### Disbursement (e-money TopUp)

#### Mandatory

| Portal scenario | Progress | Command |
|-----------------|----------|---------|
| Dana Disbursement TopUp **(mandatory)** | 0 of 7 | `./run-test.sh python disbursement` |
| Dana Disbursement Bank Top Up **(mandatory)** | 0 of 7 | *(included in the command above)* |
| General Payment finish notify **(mandatory)** | 0 of 3 | *(included in the command above)* |

#### Additional

| Portal scenario | Command |
|-----------------|---------|
| Dana Disbursement TopUp Inquiry | `./run-test.sh python disbursement dana_account_inquiry_test` |
| Dana Disbursement TopUp Status Inquiry | `./run-test.sh python disbursement transfer_to_dana_inquiry_status_test` |
| DANA Disbursement Bank Account Inquiry | `./run-test.sh python disbursement bank_account_inquiry_test` |

---

## Test case names by language

The `test_case` argument maps each Portal API to a test file or class. For **(mandatory)** APIs, run the module command without `test_case` (see tables above). To run a single API on its own, use the names below.

| Portal API | Python · Go · Node | PHP | Java |
|------------|-------------------|-----|------|
| Create Order / Payment **(mandatory)** | `create_order_test` | `CreateOrderTest` | `CreateOrderTest` |
| General Payment finish notify **(mandatory)** | `finish_notify_test` | `FinishNotifyTest` | `FinishNotifyTest` |
| Consult Pay | `consult_pay_test` | `ConsultPayTest` | `ConsultPayTest` |
| Query Payment / Debit Status | `query_payment_test` | `QueryPaymentTest` | `QueryOrderTest` |
| Refund Order | `refund_order_test` | `RefundOrderTest` | `RefundOrderTest` |
| Cancel Order | `cancel_order_test` | `CancelOrderTest` | `CancelOrderTest` |
| Widget Cashier Pay **(mandatory)** | `payment_test` | `PaymentTest` | `PaymentTest` |
| Apply OTT | `apply_ott_test` | `ApplyOttTest` | `ApplyOttTest` |
| Account Unbinding | `account_unbinding_test` | `AccountUnbindingTest` | `AccountUnbindingTest` |
| Query Order Widget | `query_order_test` | `QueryOrderTest` | `QueryOrderTest` |
| Apply Token B2B2C | `apply_token_test` | `ApplyTokenTest` | `ApplyToken` |
| Transaction History List | `transaction_list_test` | `TransactionListTest` | — |
| Get OAuth 2.0 | `get_auth_2_test` *(Go: `get_auth_test`)* | `GetAuth2Test` | `GetOauthUrl` |
| Balance Inquiry | `balance_inquiry_test` | `BalanceInquiryTest` | `BalanceInquiryTest` |
| TopUp to Dana **(mandatory)** | `transfer_to_dana_test` | `TransferToDanaTest` | `TransferToDanaTest` |
| TopUp to Bank **(mandatory)** | `disbursement_to_bank_test` *(Go · Node: `transfer_to_bank_test`)* | `TransferToBankTest` | `TransferToBankTest` |
| Dana Account Inquiry | `dana_account_inquiry_test` | `DanaAccountInquiryTest` | `DanaAccountInquiryTest` |
| TopUp Status Inquiry | `transfer_to_dana_inquiry_status_test` | `TransferToDanaInquiryStatusTest` | `TransferToDanaInquiryStatusTest` |
| Bank Account Inquiry | `bank_account_inquiry_test` | `BankAccountInquiryTest` | `BankAccountInquiryTest` |

Use `./run-test.sh list <language> <module>` to see exact names in your checkout.

---

## Requirements

Install the language SDK and tools for the stack you run:

| Language | Prerequisites |
|----------|---------------|
| Python | Python 3, `pip` |
| Go | Go 1.21+, `jq` (for retry) |
| Node | Node.js, `npm`, `jq` (for retry) |
| PHP | PHP, Composer; Chrome + Selenium for browser tests |
| Java | JDK, Maven |

**OS:** macOS and Linux — run `./run-test.sh` directly.  
Scripts require a Unix shell (`bash` / `sh`).

---

## Repository layout

- `test/` — UAT scenarios per language and module
- `resource/` — Request templates and fixtures
- `runners/` — Test runner scripts (local mandatory + retry)

---

## Note on test data

Scenarios load request bodies from JSON fixtures and convert them with the client library (e.g. `ConsultPayRequest.from_dict(...)` in Python). That pattern is for UAT only; in production, build request objects directly as shown in each [client library README](#dana-self-testing-scenario).
