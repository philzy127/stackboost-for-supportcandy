import asyncio
from playwright.async_api import async_playwright, expect

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        page = await browser.new_page()

        # Navigate to the provided URL
        await page.goto("https://stackboost.net/company-directory/")

        # Wait for the custom wrapper to be visible
        dropdown_wrapper = page.locator(".stackboost-dt-length-wrapper")
        await expect(dropdown_wrapper).to_be_visible(timeout=15000)

        # Take a screenshot of the dropdown area
        await dropdown_wrapper.screenshot(path="jules-scratch/verification/verification.png")

        await browser.close()

        print("\nVerification screenshot taken successfully.")


if __name__ == "__main__":
    asyncio.run(main())