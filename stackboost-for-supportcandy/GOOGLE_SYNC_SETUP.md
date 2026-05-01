# Setting up Google Contacts Auto Sync

To allow your users to automatically sync the staff directory with their personal Google Workspace Contacts, you must configure a Google Cloud project and generate an OAuth 2.0 Client ID.

## 1. Create a Google Cloud Project
1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Click the project dropdown in the top-left corner and select **New Project**.
3. Name your project (e.g., "StackBoost Directory Sync") and click **Create**.

## 2. Enable the Google People API
1. In your new project, navigate to **APIs & Services > Library**.
2. Search for "Google People API".
3. Click on the **Google People API** result and click **Enable**.

## 3. Configure the OAuth Consent Screen
1. Navigate to **APIs & Services > OAuth consent screen**.
2. Select **Internal** (if this is only for users within your Google Workspace organization) or **External** (if public users will use it), then click **Create**.
3. Fill out the required fields (App name, User support email, Developer contact information).
4. Click **Save and Continue**.
5. On the **Scopes** page, click **Add or Remove Scopes**.
6. Manually paste the following scope into the "Manually add scopes" text box and click Add: `https://www.googleapis.com/auth/contacts`
7. Click **Save and Continue** until you reach the summary, then click **Back to Dashboard**.

## 4. Create OAuth 2.0 Credentials
1. Navigate to **APIs & Services > Credentials**.
2. Click **Create Credentials** at the top of the screen and select **OAuth client ID**.
3. Select **Web application** as the Application type.
4. Name the client (e.g., "Directory Sync Web Client").
5. Under **Authorized JavaScript origins**, click **Add URI**. Enter the base URL of your WordPress website (e.g., `https://yourwebsite.com`). Note: This must be an exact match and cannot contain wildcards or paths.
6. Under **Authorized redirect URIs**, you can leave this blank as the plugin uses the JavaScript popup flow.
7. Click **Create**.
8. A modal will appear displaying your **Client ID**. Copy this string.

## 5. Configure StackBoost
1. Log into your WordPress admin dashboard.
2. Navigate to **SupportCandy > StackBoost**.
3. Go to the **Directory** tab, and then the **Settings** sub-tab.
4. Under **Export Settings**, check **Enable Auto Sync API**.
5. Paste the **Client ID** you copied from Google into the **Google Client ID** field.
6. Click **Save Settings**.

Your users will now see the option to sync contacts when viewing the directory shortcode.
