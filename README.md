# RK add-ons for JComments

Seven plugins, an Add-ons page and a comment template for [JComments 5](https://github.com/exstreme/Jcomments-4) by exstreme: avatar gallery, emoji reactions, editor's pick, commenter badges, voice comments, cache purge and a comment count for article cards. Nothing patches the JComments core: every add-on is a plugin of the `jcomments` group that hooks the events JComments already fires, keeps its data in its own table, and the layout is a JComments template (`rk`) you select in the options.

Version 1.0.0, GPL v2 or later. Built and used in production by Radnička Klasa (www.radnickaklasa.com).

## What is inside

| Extension | What it does |
|---|---|
| `plg_jcomments_rkavatar` | Guests and members pick an avatar from a gallery folder while writing a comment (modal picker, choice remembered in the browser). A comment without a choice gets a stable avatar derived from the author name. 35 avatars ship with the package. |
| `plg_jcomments_rkreactions` | Emoji reactions under a comment (like, love, haha, wow, sad, angry by default; configurable). One reaction per visitor and comment: members by user id, guests by a browser token. Optionally hides the core +/- vote. |
| `plg_jcomments_rkpick` | Editor's pick: a moderator stars a comment on the site (or in the administrator list with the optional override); the comment gets a badge and, when it is a top-level comment, its thread moves to the top. |
| `plg_jcomments_rkbadges` | A badge under the avatar by the number of comments (three levels by default, editable: icon, name, threshold) and a fixed staff badge for chosen user groups. Counts are live from `#__jcomments`. |
| `plg_jcomments_rkvoice` | Voice comments: the visitor records a clip in the browser (MediaRecorder), it is uploaded ahead of the comment, attached when the comment is stored and shown as a player once the comment is published. Container checked by magic bytes, size and length limits, guests on/off. |
| `plg_jcomments_rkpurge` | When a comment is published, unpublished, added, edited or deleted, cleans the Joomla site cache and, with the LiteSpeed Cache plugin enabled, purges the article and category pages through the `X-LiteSpeed-Purge` header. |
| `plg_system_rkjccount` | Required by the others: puts the add-ons' scripts, styles and the captcha on the article page so the comment form works when JComments loads it on click. Optionally fills a comment-count placeholder in article cards (see below). |
| `com_rkjcomments` | An "Add-ons" page in the JComments administrator menu: every RK plugin with an on/off switch, a description and a link to its settings. |
| `files_rkjcomments` | The JComments template `rk` (`components/com_jcomments/tpl/rk`) that displays all of the above plus an emoji picker in the editor, and the avatar gallery (`images/jcomment/avatars`). |

## Requirements

- Joomla 5 or 6, PHP 8.1 or newer
- JComments 5.0.5 or newer (the template uses the form config JComments introduced in 5.0.5)
- For the voice comments: a site on https (browsers allow the microphone only in a secure context)
- For the LiteSpeed purge: the Joomla LSCache plugin (`plg_system_lscache`); without it only the Joomla cache is cleaned

## Install

1. Install `pkg_rkjcomments-<version>.zip` through System: Install: Extensions. The package enables the seven plugins and puts the "Add-ons" item in the JComments menu.
2. Components: JComments: Options: View: **Template** = `rk`. Without this the add-ons store their data but nothing shows on the site.
3. Check the add-on settings from Components: JComments: Add-ons (emoji set, badge levels and staff groups, clip length, avatar folder, purge of category pages, count placeholder).

Each plugin can be switched off on the Add-ons page without touching the comments: its data stays in its own table and comes back when it is enabled again.

### Template

JComments loads the template from `components/com_jcomments/tpl/rk/`. Files it does not override (`tpl_index.php`, the e-mail and report templates) fall back to the `default` template. If your site template already has an override of the JComments template, copy the six files to `templates/<your template>/html/com_jcomments/rk/` instead and keep `rk` selected; JComments prefers the override.

Colours are CSS variables declared on `#jc` in `style.css` (`--rkjc-accent`, `--rkjc-dark`, `--rkjc-text`, `--rkjc-soft` and so on). To match your site, redeclare them in your own CSS, for example:

```css
#jc, .rkjc-avatar-modal { --rkjc-accent: #af9a7d; --rkjc-accent-ink: #8b7860; --rkjc-accent-soft: rgba(175, 154, 125, .14); }
```

The template prints what the plugins put on the comment object (`rkAvatarFile`, `rkReactionsHtml`, `rkPick`, `rkBadgeHtml`, `rkVoiceHtml`, `rkHideVote`); the element ids are the ones `jcomments-v4.0.js` expects, so do not rename them when you adapt the markup.

### Comment count in article cards

A template or a Gantry particle cannot ask JComments for a count while it renders a card. Print an empty placeholder instead:

```html
<span class="g-article-comments" data-rkjc="ARTICLE ID" data-rkjc-href="ARTICLE URL#jc"></span>
```

In a Gantry Twig particle that is `data-rkjc="{{ article.id }}" data-rkjc-href="{{ article.route }}#jc"`. After the page is rendered, `plg_system_rkjccount` replaces every placeholder with `<span class="..."><a href="URL">ICON COUNT</a></span>` for articles where comments are enabled (category on the JComments list, no `{jcomments off}` in the text) and removes it elsewhere. One query for the articles and one for the counts, whatever the number of cards. The class name and the icon HTML are plugin parameters; the count can be switched off while the bridge part of the plugin keeps running.

### Optional: star column in the administrator comments list

`dist/rkjcomments-admin-star-<version>.zip` (also in `extras/admin-star/`) holds an override of the JComments comments list for the Atum administrator template with an extra "Editor's pick" column. Copy `administrator/templates/atum/html/com_jcomments/comments/default.php` into your site. The override is a copy of the core file from JComments 5.0.6 with three inserted blocks; after a JComments update rebuild it from the new core file:

```bash
php extras/admin-star/make_override.php administrator/components/com_jcomments/views/comments/tmpl/default.php administrator/templates/atum/html/com_jcomments/comments/default.php
```

Without the override the star is still available on the site, in the moderator toolbar under each comment.

## How the add-ons talk to JComments

- Form additions (avatar picker, voice recorder) come through `onJCommentsFormAppend`.
- Comment data is attached in `onJCommentsCommentsPrepare` (one query per list) and `onJCommentsCommentAfterPrepare` (single comment after add, edit or on a permalink).
- Writes happen in `onJCommentsCommentAfterAdd`, `onJCommentsCommentAfterChange`, `onJCommentsCommentAfterPublish` and `onJCommentsCommentAfterDelete`; deleting a comment removes its avatar choice, reactions, pick and clip.
- AJAX endpoints use `com_ajax` (`index.php?option=com_ajax&group=jcomments&plugin=<name>`), with `X-LiteSpeed-Cache-Control: no-cache`.
- The `onRkjcPageAssets` event is our own: `plg_system_rkjccount` triggers it on the article page before the `<head>` is compiled, so an add-on that needs CSS or JS on the page when the form arrives later through AJAX adds it there. The add-on scripts watch the DOM with a MutationObserver.

Tables: `#__jcomments_rkavatar`, `#__jcomments_rkreactions`, `#__jcomments_rkpick`, `#__jcomments_rkvoice` (created on install, and on first use if missing). Files: `images/jcomment/avatars/` (gallery, parameter), `images/jcomment/voice/` (clips, parameter; pending clips in `tmp/`, removed after 24 hours).

Uninstalling the package removes the extensions, the template files and the 35 shipped avatars; the tables and the clips stay (drop or delete them by hand if you no longer need them).

## Repository layout

- `src/<extension>/`: one folder per extension, exactly as it is zipped
- `pkg/`: the package manifest and its install script
- `extras/admin-star/`: the optional administrator override and the script that rebuilds it
- `build.py`: PHP lint, JS syntax check, literal and language-key checks, then packs everything into `dist/`
- Ready-made zips and checksums are attached to each GitHub release

Languages: en-GB and sr-YU (Serbian, Latin script). Translations welcome: every string is in the `.ini` files, the emoji picker reads its texts from `files_rkjcomments.ini`.

## Credits

Made by Radnička Klasa (www.radnickaklasa.com) for its own site and published under the GNU GPL v2 or later. JComments is by Sergey M. Litvinov, exstreme and Vladimir Globulopolis.
