# Bulk Image ALT Editor

A small WordPress plugin that lives inside the Media Library and lets you
overwrite the ALT text on many images at once.

- One text field for the ALT text you want to apply.
- A checkbox picker listing your images with thumbnail, filename, title and the
  ALT text each one currently has, so you can see exactly what you are about to
  overwrite.
- **Apply** writes your text as the complete new ALT value. No prefix, no
  suffix, no merge with the old value. Images you did not tick are not touched.

Native WordPress admin UI, built on core's own `WP_List_Table`. No React, no
Bootstrap, no jQuery plugins, no external requests, no Gutenberg dependency.
Two small CSS/JS files.

## Install

> **Download the zip from [Releases](../../releases)** — the file named
> `bulk-image-alt-editor.zip`. Do **not** use the green "Code → Download ZIP"
> button: that wraps everything in an extra folder and WordPress will reject it.

1. Download `bulk-image-alt-editor.zip` from
   [Releases](../../releases).
2. WordPress admin > **Plugins > Add New > Upload Plugin** > choose the zip >
   **Install Now** > **Activate Plugin**.
3. The screen appears at **Media > Bulk Alt Editor**.

## Usage

**Media > Bulk Alt Editor**

1. Type the ALT text in the **New ALT text** field.
2. Tick the images that should receive it.
3. Press **Apply to selected images**.

Extras on that screen:

| Control | What it does |
| --- | --- |
| All images / Missing ALT / Has ALT | Filters the list. "Missing ALT" finds the images still without one. |
| Search | Matches titles, captions, descriptions and filenames. |
| Select all N images matching this filter | Appears once you tick the header checkbox and there is more than one page. Applies to every image matching the current search and filter — never to the whole library unless that *is* your current filter. Asks for confirmation. |
| Undo this change | In the success notice. Restores the previous ALT values. Valid for 24 hours or one use. |
| Images per page | Quick links above the table: 20 / 50 / 100 / 200 / 500. The choice is saved per user. Screen Options sets the same value and accepts anything up to 500. |

**Media > Library (list mode)** also gains a **Set ALT text** bulk action, with
an inline text field, driven by the same code.

Leaving the field empty and pressing Apply clears the ALT text on the selected
images, behind a confirmation prompt.

## Permissions

The screen requires the `upload_files` capability (Administrator, Editor,
Author). Every individual write is additionally checked with
`current_user_can( 'edit_post', $id )`, so an Author can only change ALT text on
their own uploads; anything they cannot edit is shown with a lock instead of a
checkbox and is refused server-side even if the request is forged.

## What it stores

Only the ALT text itself, in WordPress's own `_wp_attachment_image_alt` post
meta — the exact field the Media Library uses — plus a per-user "images per
page" screen option and a short-lived undo snapshot.

Deleting the plugin removes the screen option and any leftover undo snapshots.
It deliberately does **not** revert ALT text: those values belong to WordPress
and keep working with or without this plugin.

## Structure

```
bulk-image-alt-editor.php              plugin header, constants, bootstrap
includes/class-wpbiae-updater.php      all writes; capability checks; undo
includes/class-wpbiae-query.php        the image queries behind the picker
includes/class-wpbiae-list-table.php   the picker (extends WP_List_Table)
includes/class-wpbiae-admin-page.php   the Media > Bulk Alt Editor screen
includes/class-wpbiae-media-bulk-action.php   the Media Library bulk action
assets/admin.css, admin.js, media-bulk.js
uninstall.php
```

## Requirements

WordPress 6.0+ (developed and tested against 7.1), PHP 7.2+.

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).
