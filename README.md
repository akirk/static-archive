# Static Archive

Contributors: akirk  
Tags: archive, backup, static, html, markdown  
Requires at least: 5.0  
Tested up to: 6.8  
Stable tag: 1.0.0  
Requires PHP: 7.0  
License: GPL-2.0-or-later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Generate a self-contained static HTML archive of your posts in the uploads directory.

## Description

A WordPress plugin that generates a static HTML archive of your posts and pages, stored directly in the uploads directory alongside your images. If you ever lose access to WordPress — or simply don't want to maintain a PHP and MySQL stack to read your old content — the archive is right there: plain HTML files you can open in any browser.

### Why

WordPress backups typically require restoring a database and running PHP to see your content again. That's fine today, but years from now you might not have a WordPress environment handy. By generating HTML files into the same directory where your images already live, this plugin turns your uploads folder into a self-contained archive. Copy it to a USB drive, a NAS, or cloud storage, and your content remains readable without any software beyond a web browser.

You can also generate Markdown files alongside (or instead of) HTML — useful for feeding content into LLMs, migrating to other platforms, or simply having a future-proof plain-text copy of everything you've written.

### How it works

Each published post or page gets its own file placed in the uploads directory. Posts go into year folders, pages into a `pages/` folder. Image URLs are rewritten to relative paths, so the entire uploads directory is self-contained — just copy it and everything works.

### Output structure

```
uploads/                          (or uploads/sites/{id}/ on multisite)
├── style.css
├── archive-{suffix}.html         (main index, grouped by year)
├── archive-{suffix}.md           (if Markdown enabled)
├── pages/
│   ├── page-10-{suffix}.html
│   └── page-10-{suffix}.md
├── 2024/
│   ├── archive-{suffix}.html     (year archive, oldest first)
│   ├── latest-{suffix}.html      (year archive, newest first)
│   ├── post-123-{suffix}.html
│   ├── post-123-{suffix}.md
│   ├── 01/                       (existing image uploads)
│   ├── 02/
│   └── ...
├── 2025/
│   └── ...
```

### Automatic updates

When you publish, update, or delete a post or page, the plugin automatically regenerates:
- The individual post's HTML and/or Markdown file
- The main index
- The year archive for that post's year (posts only)

### Filename suffix

All generated filenames include a configurable random suffix (e.g. `-keT1KxmG`) to prevent the archive from being discoverable via URL guessing. This can be changed or cleared in the settings.

### Admin UI

<img width="1640" height="1732" alt="Screenshot 2026-03-10 at 15 04 52" src="https://github.com/user-attachments/assets/b21b79f6-e39b-4b5a-81ca-b0912c682e7a" />

Go to **Tools → Static Archive** to:

- See archive status (total entries, archived, missing, outdated, orphaned)
- **Verify** the archive against your published content
- **Generate All** to rebuild everything (processes in batches to avoid timeouts)
- **Delete All Files** to remove all generated files (can be regenerated at any time)
- Choose which **post types** to archive (posts and pages by default)
- Choose the **output format**: HTML, Markdown, or both
- Configure the filename suffix

A link to the admin page is also available on the Plugins list page.

### Features

- Archive posts, pages, and custom post types
- Output as HTML, Markdown, or both
- Works on single-site and multisite WordPress installations
- Posts without titles fall back to excerpt or content snippet in listings
- Author displayed on each post and in the index
- Previous/next navigation between posts
- Year archives available in both chronological and reverse order
- Year navigation at the top of the main index
- Pages listed in a separate section of the index
- Markdown files include YAML frontmatter (title, date, author)
- Clean, responsive HTML design with system fonts
- No external dependencies — just plain HTML and CSS

### How is this different from other static site plugins?

Most WordPress static site plugins are designed to replace WordPress with a static frontend, or to create a full themed mirror of your site. Static Archive solves a different problem: making your content survive independently of WordPress.

| Plugin | What it does | How Static Archive differs |
|--------|-------------|--------------------------|
| [Simply Static](https://wordpress.org/plugins/simply-static/) | Crawls the live site and exports a full themed mirror with all CSS/JS | Exports to a separate location or ZIP. Much heavier output, not designed for portable backups within the uploads directory. |
| [Export WP Pages to Static HTML](https://wordpress.org/plugins/export-wp-page-to-static-html/) | Manual page-by-page export with bundled assets | Not designed for ongoing automatic archiving of all posts. |
| [WP2Static](https://github.com/leonstafford/wp2static) | Crawls site and deploys to S3, GitHub Pages, etc. | Focused on replacing WordPress with a static site, not creating a portable backup alongside it. |
| [Serve Static](https://wordpress.org/plugins/serve_static/) | Generates cached static copies for performance | A performance cache, not an archiving tool. |

The key difference is where and why the files are generated. Static Archive places minimal, clean HTML (and optionally Markdown) directly into the uploads directory — the same place your images already live. The result is that a backup of your uploads folder (or even just your `wp-content` directory) gives you browsable content with no database, no PHP, and no WordPress required.

## Installation

1. Upload the `static-archive` directory to `wp-content/plugins/`
2. Activate the plugin
3. Go to **Tools → Static Archive** and click **Generate All**

## Frequently Asked Questions

### Where are the generated files stored?

In your WordPress uploads directory (usually `wp-content/uploads/`). Posts are organized in year folders, pages in a `pages/` folder.

### Will this slow down my site?

No. The plugin only runs when you publish, update, or delete a post. It generates static files in the background — your visitors never see any difference.

### Can I use this on multisite?

Yes. Each site gets its own archive in its own uploads directory (`wp-content/uploads/sites/{id}/`).

### What happens if I deactivate the plugin?

The generated files remain in place. They're just HTML files in your uploads directory — they don't depend on the plugin to be readable.

### Can I choose which post types to archive?

Yes. Go to **Tools → Static Archive** and select which post types to include. Posts and pages are enabled by default.

## Screenshots

1. The admin page showing archive status, generation controls, and settings.

## Changelog

### 1.0.0
- Initial release.

## WP-CLI

```
# Generate all posts + pages + index + year archives
wp static-archive generate

# Generate a single post or page
wp static-archive generate --post_id=123

# Check for missing, outdated, or orphaned files
wp static-archive verify
```

On multisite, add `--url=yoursite.example.com` to target a specific site.

## Plugin Integration

Static Archive archives real WordPress posts. Plugins can make additional post
types available to Static Archive and provide custom generated bodies for the
built-in HTML and Markdown output formats.

Static Archive uses the same filters for its own built-in `post` and `page`
support. It registers `post` and `page` through `static_archive_post_types`,
applies `the_content` through `static_archive_post_html`, and derives Markdown
through `static_archive_post_markdown`. It also provides previous/next
navigation for posts through the adjacent post filters.

### Add an Archive Post Type

Use `static_archive_post_types` to add post types that should be available in
**Tools -> Static Archive**. This is useful for non-public post types that should
not be exposed on the front end, but should still be included in the portable
archive.

```php
add_filter( 'static_archive_post_types', function( array $post_types ): array {
    $post_types[] = 'my_private_post_type';
    return $post_types;
} );
```

If no Static Archive settings have been saved yet, filtered post types are
selected by default along with posts and pages. Once settings are saved, the
saved selection is respected, including an empty post type selection.

### Render HTML

Use `static_archive_post_html` to replace the HTML body written for a post. The
starting value is the raw `post_content`; Static Archive's built-in post/page
filter applies `the_content`. The post lifecycle, filename, index updates, and
year archive updates are still handled by Static Archive.

```php
add_filter(
    'static_archive_post_html',
    function( string $html, WP_Post $post, Static_Archive_Generator $generator ): string {
        if ( 'my_private_post_type' !== $post->post_type ) {
            return $html;
        }

        return '<h2>Custom archive body</h2>';
    },
    10,
    3
);
```

The returned string is used as the body content inside Static Archive's normal
HTML post template.

### Render Markdown

Use `static_archive_post_markdown` to replace the Markdown body written for a
post. Return `null` to let Static Archive derive Markdown from the filtered HTML
body.

```php
add_filter(
    'static_archive_post_markdown',
    function(
        ?string $markdown,
        WP_Post $post,
        Static_Archive_Generator $generator,
        string $html
    ): ?string {
        if ( 'my_private_post_type' !== $post->post_type ) {
            return $markdown;
        }

        return "## Custom archive body\n\nPlain-text archive content.";
    },
    10,
    4
);
```

These render filters are format-specific so a plugin can provide exactly the
formats it understands. Future output formats can follow the same pattern.

### Adjacent Navigation

Use `static_archive_post_previous_post` and `static_archive_post_next_post` to
provide previous and next posts for HTML navigation. Static Archive's built-in
post/page integration provides these only for the `post` post type.

```php
add_filter(
    'static_archive_post_previous_post',
    function( ?WP_Post $previous, WP_Post $post, Static_Archive_Generator $generator ): ?WP_Post {
        if ( 'my_private_post_type' !== $post->post_type ) {
            return $previous;
        }

        return my_plugin_get_previous_archive_post( $post );
    },
    10,
    3
);
```

Return `null` when no adjacent post should be linked.

## Requirements

- WordPress 5.0+
- PHP 7.0+
- Write access to the uploads directory
