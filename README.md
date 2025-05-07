# DANA Self Testing Scenario

## Description

This repository contains scenarios that can be run for UAT in Sandbox and E2E scenarios using DANA Client with your choice of programming language. You can use it to:

- Automate running UAT scenarios in Sandbox with your credentials and the result can be downloaded in your Merchant Portal dashboard
- Find examples of E2E scenarios for each business solution

## How to Use

1. **Set up your environment:**
   - Clone this repo to your deployed sandbox system (can be in Kubernetes, VM, etc.)
   ```bash
   git clone https://gitlab.dana.id/automation/dana-self-integration-test.git
   cd dana-self-integration-test
   ```

2. **Configure your credentials:**
   - Change the `.env-example` file name to `.env` and fill the data with your credentials
   ```bash
   cp .env-example .env
   ```
   - Edit the `.env` file with your credential information
   
   > **Note:** You can fill `PRIVATE_KEY` and `PRIVATE_KEY_PATH` simultaneously, but if you fill both and the key values are different, we will prioritize the key in `PRIVATE_KEY_PATH`. Remember that the string in `PRIVATE_KEY` must be separated by `\n` for each new line.

3. **Run the tests:**
   - Run the command with your preferred programming language
   ```bash
   sh run-test.sh python
   ```
   - View the results in your terminal and in your Merchant Portal dashboard

## Supported Languages

Currently, the following programming languages are supported:

- Python

Additional language support will be added in future updates.

## Structure

- `test/`: Contains test scenarios for different business solutions
- `resource/`: Contains request templates and fixtures
- `runner/`: Contains runner scripts for different programming languages

