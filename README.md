# Static Archive

A WordPress plugin that generates a self-contained static HTML archive of your site's posts, stored directly in the uploads directory alongside your images. The result is a portable, WordPress-independent backup you can browse offline or host anywhere.

## How it works

Each published post gets its own HTML file placed in the corresponding year folder within your uploads directory. Image URLs are rewritten to relative paths, so the entire uploads directory is self-contained — just copy it and everything works.

### Output structure

```
uploads/                          (or uploads/sites/{id}/ on multisite)
├── style.css
├── archive-{suffix}.html         (main index, grouped by year)
├── 2024/
│   ├── archive-{suffix}.html     (year archive, oldest first)
│   ├── latest-{suffix}.html      (year archive, newest first)
│   ├── post-123-{suffix}.html
│   ├── post-456-{suffix}.html
│   ├── 01/                       (existing image uploads)
│   ├── 02/
│   └── ...
├── 2025/
│   └── ...
```

### Automatic updates

When you publish, update, or delete a post, the plugin automatically regenerates:
- The individual post's HTML file
- The main index
- The year archive for that post's year

### Filename suffix

All generated filenames include a configurable random suffix (e.g. `-keT1KxmG`) to prevent the archive from being discoverable via URL guessing. This can be changed or cleared in the settings.

## Admin UI

Go to **Tools → Static Archive** to:

- See archive status (total posts, archived, missing, outdated, orphaned)
- **Verify** the archive against your published posts
- **Generate All** to rebuild everything (processes in batches to avoid timeouts)
- Configure the filename suffix

A link to the admin page is also available on the Plugins list page.

## WP-CLI

```
# Generate all posts + index + year archives
wp static-archive generate

# Generate a single post
wp static-archive generate --post_id=123

# Check for missing, outdated, or orphaned files
wp static-archive verify
```

On multisite, add `--url=yoursite.example.com` to target a specific site.

## Features

- Works on single-site and multisite WordPress installations
- Posts without titles fall back to excerpt or content snippet in listings
- Author displayed on each post and in the index
- Previous/next navigation between posts
- Year archives available in both chronological and reverse order
- Year navigation at the top of the main index
- Clean, responsive design with system fonts
- No external dependencies — just plain HTML and CSS

## Installation

1. Copy the `static-archive` directory to `wp-content/plugins/`
2. Activate the plugin on the site(s) you want to archive
3. Go to **Tools → Static Archive** and click **Generate All**

## How is this different from other static site plugins?

There are several WordPress plugins that export sites to static HTML, but they solve a different problem:

| Plugin | What it does | How Static Archive differs |
|--------|-------------|--------------------------|
| [Simply Static](https://wordpress.org/plugins/simply-static/) | Crawls the live site and exports a full themed mirror with all CSS/JS | Exports to a separate location or ZIP. Much heavier output, not designed for portable backups within the uploads directory. |
| [Export WP Pages to Static HTML](https://wordpress.org/plugins/export-wp-page-to-static-html/) | Manual page-by-page export with bundled assets | Not designed for ongoing automatic archiving of all posts. |
| [WP2Static](https://github.com/leonstafford/wp2static) | Crawls site and deploys to S3, GitHub Pages, etc. | Focused on replacing WordPress with a static site, not creating a portable backup alongside it. |
| [Serve Static](https://wordpress.org/plugins/serve_static/) | Generates cached static copies for performance | A performance cache, not an archiving tool. |

Static Archive is purpose-built for **archiving content portably within your existing WordPress install**, not for converting your site to static hosting. Key differences:

- HTML files live inside the uploads directory alongside images, with relative paths — the whole directory is one self-contained, backupable unit
- Automatic archiving on publish, no manual export step
- Minimal clean HTML with its own lightweight CSS, not a themed mirror
- Verify/audit tool to detect missing, outdated, or orphaned files
- Non-guessable filenames for private sites

## Requirements

- WordPress 5.0+
- PHP 7.0+
- Write access to the uploads directory
