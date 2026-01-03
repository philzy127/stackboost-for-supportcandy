from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        # Determine path
        cwd = os.getcwd()
        url = f"file://{cwd}/test_frontend_order.html"

        page.goto(url)
        page.screenshot(path="/home/jules/verification/frontend_order_verify.png", full_page=True)
        browser.close()

if __name__ == "__main__":
    run()
