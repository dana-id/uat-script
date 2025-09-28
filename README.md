# DANA Self Testing Scenario

## Description

This repository contains scenarios that can be run for UAT in Sandbox and E2E scenarios using DANA Client library with your choice of programming language (Python, Golang, PHP, Node). You can use it to:

- **Automate running UAT** scenarios in Sandbox with your credentials and the result can be downloaded in your Merchant Portal dashboard
- **Find examples of E2E** scenarios for each business solution

For documentation of each DANA Client library visit:
- Python: https://github.com/dana-id/dana-python
- Golang: https://github.com/dana-id/dana-go
- Node: https://github.com/dana-id/dana-node
- PHP: https://github.com/dana-id/dana-php
- Java: https://github.com/dana-id/dana-java

## How to Use

1. **Set up your environment:**
   
   Clone this repo to your deployed sandbox system (can be in Kubernetes, VM, etc.)
   ```bash
   git clone git@github.com:dana-id/uat-script.git
   cd uat-script
   ```

2. **Configure your credentials:**

   Change the `.env-example` file name to `.env` and fill the data with your credentials
   ```bash
   cp .env-example .env
   ```
   Edit the `.env` file with your credential information
   
   > **Note:** You can fill `PRIVATE_KEY` and `PRIVATE_KEY_PATH` simultaneously, but if you fill both and the key values are different, we will prioritize the key in `PRIVATE_KEY_PATH`. The same applied to `DANA_PUBLIC_KEY` and `DANA_PUBLIC_KEY_PATH`. The `CLIENT_SECRET` env is for `disbursement` business solution.

3. **Run the tests:**
   
   Run the command with your preferred programming language.
   ```bash
   sh run-test.sh python
   ```
   You can run the command with your specific business solution.
   ```bash
   sh run-test.sh python payment_gateway
   ```
   You can run the command with your specific API in business solution.
   ```bash
   sh run-test.sh python payment_gateway create_order_test
   ```

   View the results in your terminal and in your Merchant Portal dashboard

   > **Note:** You can find specific API solution that you choose with this command
   ```bash
   sh run-test.sh --list python
   ```


## Supported Languages

Currently, the following programming languages are supported:

- Python
- Golang
- Node
- PHP
- Java

Additional language support will be added in future updates.

## Structure

- `test/`: Contains test scenarios for different business solutions
- `resource/`: Contains request templates and fixtures

## Note

The test scenarios in this repository use a JSON-based approach for loading test data, which means the way API requests are made here may differ slightly from how you would use the libraries in your actual implementation (detailed in the [library documentation](#description)):

**Example in Python:**
- **In this repository:** Requests are loaded from JSON files and converted using `.from_dict()` method
  ```python
  # Test scenario approach
  json_dict = get_request(json_path_file, title_case, case_name)
  consult_pay_request_obj = ConsultPayRequest.from_dict(json_dict)
  api_instance.consult_pay(consult_pay_request_obj)
  ```

- **In your actual implementation:** You would directly create request objects as shown in the library documentation
  ```python
  # Direct implementation approach
  request = ConsultPayRequest(
      merchant_id="YOUR_MERCHANT_ID",
      amount={"value": "10000.00", "currency": "IDR"},
      additional_info={...}
  )
  api_instance.consult_pay(request)
  ```