package payment_gateway

import (
	"context"
	"fmt"
	"log"
	"time"

	"github.com/chromedp/chromedp"
)

func PayOrder(phoneNumber, pin, redirectUrl string) interface{} {
	// Create a custom allocator with non-headless mode
	opts := append(chromedp.DefaultExecAllocatorOptions[:],
		chromedp.Flag("headless", false),    // Set to false for visible browser
		chromedp.Flag("disable-gpu", false), // Enable GPU for better rendering
		chromedp.WindowSize(1280, 800),      // Set a reasonable window size
	)

	// Create allocator context with options
	allocCtx, cancel := chromedp.NewExecAllocator(context.Background(), opts...)
	defer cancel()

	// Create browser context from the allocator
	ctx, cancel := chromedp.NewContext(allocCtx)
	defer cancel()

	// Set the phone number and pin in the input fields
	var inputPhoneNumber = "//*[contains(@class,\"desktop-input\")]//input"
	var buttonSubmitPhoneNumber = "//*[contains(@class,\"agreement__button\")]//button"
	var inputPin = "//*[contains(@class,\"input-pin\")]//input"
	var buttonPay = "//*[contains(@class,\"btn-pay\")]"
	var flagPageSuccess = "//*[contains(h1,\"404\")]"

	log.Print("==================================================================")
	log.Print("Starting Payment automation ...")
	log.Print("Url redirect: ", redirectUrl)

	if err := chromedp.Run(ctx,
		chromedp.Navigate(redirectUrl),
	); err != nil {
		return fmt.Errorf("failed redirect to link: %v", err)
	}

	// Input phone number and pin
	log.Print("Input phone number: ", phoneNumber)
	if err := chromedp.Run(ctx,
		chromedp.WaitVisible(inputPhoneNumber, chromedp.BySearch),
		chromedp.SendKeys(inputPhoneNumber, phoneNumber[1:], chromedp.BySearch),
		chromedp.Click(buttonSubmitPhoneNumber, chromedp.BySearch),
		chromedp.SendKeys(inputPin, pin, chromedp.BySearch),
	); err != nil {
		return fmt.Errorf("failed redirect to link: %v", err)
	}

	log.Print("Click button pay ... ")
	if err := chromedp.Run(ctx,
		chromedp.Click(buttonPay, chromedp.BySearch)); err != nil {
		return fmt.Errorf("error hit  payment: %v", err)
	}

	log.Print("Wait until payment success")
	if err := chromedp.Run(ctx, chromedp.WaitVisible(flagPageSuccess, chromedp.BySearch)); err != nil {
		return fmt.Errorf("error hit  payment: %v", err)
	}
	log.Print("==================================================================")
	chromedp.Sleep(5 * time.Second)
	log.Print("Payment success")
	return nil
}
