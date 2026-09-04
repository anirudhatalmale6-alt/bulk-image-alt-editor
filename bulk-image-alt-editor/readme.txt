=== Bulk Image ALT Editor ===
Contributors: anirudhatalmale
Tags: alt text, media library, accessibility, seo, bulk edit
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Overwrite the ALT text of many Media Library images at once. Type the text, tick the images, press Apply.

== Description ==

A small, native WordPress screen for one job: replacing the ALT text on a batch
of images you choose yourself.

* One text field for the ALT text you want to apply.
* A checkbox picker listing your images, with thumbnail, filename, title and the
  ALT text each image currently has, so you can see exactly what you are about
  to overwrite.
* Apply writes your text as the complete new ALT value. No prefix, no suffix, no
  merge with the old value. Images you did not tick are not touched.

Built with WordPress's own admin list table. No React, no Bootstrap, no jQuery
plugins, no external requests, no Gutenberg dependency. Two small CSS/JS files,
around 25 KB of code in total.

== Installation ==

1. In the WordPress admin go to **Plugins > Add New > Upload Plugin**.
2. Choose `bulk-image-alt-editor.zip` and press **Install Now**.
3. Press **Activate Plugin**.
4. The new screen appears at **Media > Bulk Alt Editor**.

You need the `upload_files` capability to see the screen - Administrators,
Editors and Authors have it by default. Individual images are checked separately:
you can only change ALT text on images you are allowed to edit, so an Author
cannot overwrite another user's images.

== Usage ==

**Media > Bulk Alt Editor**

1. Type the ALT text you want in the **New ALT text** field at the top.
2. Tick the images that should receive it. The checkbox in the table header
   selects every image on the current page.
3. Press **Apply to selected images**.

Useful extras on that screen:

* **All images / Missing ALT / Has ALT** - filter the list. "Missing ALT" is the
  quick way to find the images that still need attention.
* **Search** - matches image titles, captions, descriptions and filenames.
* **Select all N images matching this filter** - appears once you tick the header
  checkbox and there is more than one page. This applies your text to every image
  matching the current search and filter, not to the whole library. You are asked
  to confirm first.
* **Undo this change** - the success message carries an Undo button that puts the
  previous ALT values back. The undo snapshot lasts 24 hours or until used.
* **Images per page** - links above the table for 20, 50, 100, 200 or 500 at a
  time. Your choice sticks between visits. Screen Options (top right) sets the
  same thing if you prefer, and accepts any number up to 500.

**Media > Library (list mode)**

The same thing is available as a normal bulk action. Switch the Media Library to
list mode, tick some images, choose **Set ALT text** from the Bulk actions
dropdown, type the text in the field that appears, and press Apply.

**Clearing ALT text**

Leaving the text field empty and pressing Apply clears the ALT text on the
selected images. You are asked to confirm before that happens.

== Uninstall ==

1. **Plugins > Installed Plugins**, then **Deactivate** under Bulk Image ALT Editor.
2. Press **Delete**, then confirm.

Deleting the plugin removes the plugin folder, the per-user "Images per page"
screen option, and any unused undo snapshots.

It deliberately does **not** revert ALT text. The values you applied are stored
in WordPress's own `_wp_attachment_image_alt` post meta - the exact field the
Media Library uses - so they stay in place, keep working in your themes and
plugins, and remain editable from the normal Media Library after this plugin is
gone. Nothing about your images depends on the plugin staying installed.

== Frequently Asked Questions ==

= Does it change the whole library by mistake? =

No. Nothing happens without an explicit selection. If you press Apply with
nothing ticked, the plugin refuses and tells you so.

= Does it append to the existing ALT text? =

No. The value you type replaces the existing value completely.

= Does it touch the image title, caption or description? =

No. Only the ALT text.

= Does it work with WooCommerce / page builders / my theme? =

Yes. It writes to the standard WordPress ALT field, so anything that reads ALT
text the normal way picks the new value straight up.

= Does it need Gutenberg? =

No. It has no block editor dependency and no block assets.

= Does it phone home? =

No. There are no external requests, no tracking and no settings to configure.

== Changelog ==

= 1.0.1 =
* Added "Images per page" quick links above the table (20 / 50 / 100 / 200 /
  500), so larger pages no longer have to be found under Screen Options.

= 1.0.0 =
* First release.
