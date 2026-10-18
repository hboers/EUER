// Eigene Browser-Oberfläche laden und die native Fenster-Titelleiste behalten.
user_pref("toolkit.legacyUserProfileCustomizations.stylesheets", true);
user_pref("browser.tabs.inTitlebar", 0);

// Das temporäre Profil ohne Sprach- oder Erststartdialog auf Deutsch starten.
user_pref("intl.locale.requested", "de");
user_pref("intl.accept_languages", "de-DE, de, en-US, en");
user_pref("intl.multilingual.enabled", false);
user_pref("intl.multilingual.downloadEnabled", false);
user_pref("privacy.spoof_english", 1);
user_pref("browser.aboutwelcome.enabled", false);
user_pref("browser.shell.checkDefaultBrowser", false);
user_pref("browser.startup.homepage_override.mstone", "ignore");
user_pref("startup.homepage_welcome_url", "");
user_pref("startup.homepage_welcome_url.additional", "");
