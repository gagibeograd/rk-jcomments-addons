# Changelog

## 1.0.0

First public release. Everything in the package has run in production on www.radnickaklasa.com since September 2026 with JComments 5.0.3 to 5.0.6 on Joomla 5.4 and 6.2.

- `plg_jcomments_rkavatar` 1.0.0: avatar gallery picker, stable fallback avatar, choice remembered in the browser, form reset clears it.
- `plg_jcomments_rkreactions` 1.0.0: emoji reactions, one per visitor and comment, optional member names in the summary, optional hiding of the core vote.
- `plg_jcomments_rkpick` 1.0.0: editor's pick on the site and (optional override) in the administrator list, picked threads first.
- `plg_jcomments_rkbadges` 1.0.0: badge levels by comment count, staff badge by user group.
- `plg_jcomments_rkvoice` 1.0.0: in-browser voice recording, upload ahead of the comment, magic-byte container check, player in the comment.
- `plg_jcomments_rkpurge` 1.0.0: Joomla and LiteSpeed cache purge of the article and category pages on comment changes.
- `plg_system_rkjccount` 1.0.0: assets and captcha bridge for the form loaded on click; comment-count placeholder for article cards (class and icon configurable).
- `com_rkjcomments` 1.0.0: Add-ons page in the JComments menu with switches.
- `files_rkjcomments` 1.0.0: the `rk` JComments template with emoji picker (texts from the language file, English keywords from the Unicode names) and 35 avatars.

Compared with the site version: default labels, badge names and emoji sets come from the language files instead of being hard-coded in Serbian; plugin CSS uses the template's CSS variables with neutral fallbacks; the template is a JComments template instead of a site-template override; the count placeholder class and icon are parameters.
