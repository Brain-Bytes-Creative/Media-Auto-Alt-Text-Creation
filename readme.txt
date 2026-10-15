=== Media Auto Alt Text Creation ===
Contributors: brainbytescreative
Tags: alt text, image title, media library, accessibility, seo
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically sets a clean title and alt text from the file name when an image is uploaded to the Media Library.

== Description ==

When an image is uploaded, its file name is turned into readable text and saved to the fields you choose.
For example, `summer_beach-sunset-1024x768.jpg` becomes "Summer Beach Sunset".

* Choose which fields to fill: title, alt text, caption and description.
* Choose capitalization: Title Case, Sentence case, lowercase, UPPERCASE, or leave as is.
* Optionally rename uploaded files to clean, lowercase, hyphenated names (images only, all uploads, or off).
* Regenerate existing images with a button in the media modal or on the image edit screen, from the Alt text column in the Media Library list view, or with the "Generate title & alt text" bulk action.
* Choose whether regenerating overwrites existing values or only fills empty fields.

Settings are under Settings > Media Alt Text.

Developers can adjust the generated text with the `maatc_generated_text` filter.

== Changelog ==

= 1.0.0 =
* Initial release.
