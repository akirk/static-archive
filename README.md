# Static Archive

A WordPress plugin that generates a static HTML archive of your posts and pages, stored directly in the uploads directory alongside your images. If you ever lose access to WordPress — or simply don't want to maintain a PHP and MySQL stack to read your old content — the archive is right there: plain HTML files you can open in any browser.

## Why

WordPress backups typically require restoring a database and running PHP to see your content again. That's fine today, but years from now you might not have a WordPress environment handy. By generating HTML files into the same directory where your images already live, this plugin turns your uploads folder into a self-contained archive. Copy it to a USB drive, a NAS, or cloud storage, and your content remains readable without any software beyond a web browser.

You can also generate Markdown files alongside (or instead of) HTML — useful for feeding content into LLMs, migrating to other platforms, or simply having a future-proof plain-text copy of everything you've written.

## How it works

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

## Admin UI

Go to **Tools → Static Archive** to:

- See archive status (total entries, archived, missing, outdated, orphaned)
- **Verify** the archive against your published content
- **Generate All** to rebuild everything (processes in batches to avoid timeouts)
- **Delete All Files** to remove all generated files (can be regenerated at any time)
- Choose which **post types** to archive (posts and pages by default)
- Choose the **output format**: HTML, Markdown, or both
- Configure the filename suffix

A link to the admin page is also available on the Plugins list page.

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

## Features

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

## Installation

1. Copy the `static-archive` directory to `wp-content/plugins/`
2. Activate the plugin on the site(s) you want to archive
3. Go to **Tools → Static Archive** and click **Generate All**

## How is this different from other static site plugins?

Most WordPress static site plugins are designed to replace WordPress with a static frontend, or to create a full themed mirror of your site. Static Archive solves a different problem: making your content survive independently of WordPress.

| Plugin | What it does | How Static Archive differs |
|--------|-------------|--------------------------|
| [Simply Static](https://wordpress.org/plugins/simply-static/) | Crawls the live site and exports a full themed mirror with all CSS/JS | Exports to a separate location or ZIP. Much heavier output, not designed for portable backups within the uploads directory. |
| [Export WP Pages to Static HTML](https://wordpress.org/plugins/export-wp-page-to-static-html/) | Manual page-by-page export with bundled assets | Not designed for ongoing automatic archiving of all posts. |
| [WP2Static](https://github.com/leonstafford/wp2static) | Crawls site and deploys to S3, GitHub Pages, etc. | Focused on replacing WordPress with a static site, not creating a portable backup alongside it. |
| [Serve Static](https://wordpress.org/plugins/serve_static/) | Generates cached static copies for performance | A performance cache, not an archiving tool. |

The key difference is where and why the files are generated. Static Archive places minimal, clean HTML (and optionally Markdown) directly into the uploads directory — the same place your images already live. The result is that a backup of your uploads folder (or even just your `wp-content` directory) gives you browsable content with no database, no PHP, and no WordPress required.

## Requirements

- WordPress 5.0+
- PHP 7.0+
- Write access to the uploads directory
