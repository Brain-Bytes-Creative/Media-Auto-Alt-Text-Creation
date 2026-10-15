# Media Auto Alt Text Creation

A WordPress plugin that automatically adds a title and alt text to images when they are uploaded to the Media Library, built from the file name (`summer_beach-sunset-1024x768.jpg` becomes "Summer beach sunset").

## Features

- Pick which fields to fill: title, alt text, caption, description.
- Pick capitalization: Sentence case (default), Title Case, lowercase, UPPERCASE, or leave as is.
- Optionally rename uploads to clean, lowercase, hyphenated file names (images only, all uploads, or off).
- Regenerate existing images from the media modal, the image edit screen, the Alt text column in the Media Library list view, or the "Generate title & alt text" bulk action.
- Choose whether regenerating overwrites existing values or only fills empty ones.

Settings live under **Settings > Media Alt Text**. The generated text can be changed with the `maatc_generated_text` filter.
