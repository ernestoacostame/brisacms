# Creating a Custom FluxCMS Theme

## Quick Start

1. Copy the `/themes/default/` folder and rename it to your theme name (e.g. `/themes/my-theme/`)
2. Edit `theme.json` with your theme's info
3. Customize the PHP templates
4. Activate your theme from Admin → Themes

---

## File Structure

```
themes/
└── my-theme/
    ├── theme.json      ← Required: theme metadata
    ├── header.php      ← HTML <head>, navigation
    ├── footer.php      ← Footer, closing </body>
    ├── index.php       ← Blog listing page
    ├── single.php      ← Article & page view
    ├── search.php      ← Search results
    ├── 404.php         ← Not found page
    └── style.css       ← Optional external stylesheet
```

## theme.json

```json
{
  "label": "My Theme",
  "description": "A short description",
  "author": "Your Name",
  "version": "1.0.0"
}
```

---

## Available PHP Variables

These are automatically available in every template:

| Variable        | Type    | Description                        |
|-----------------|---------|------------------------------------|
| `$site_title`   | string  | Site title from settings           |
| `$theme_color`  | string  | Hex accent color (e.g. `#6366f1`)  |
| `$base`         | string  | Base URL (no trailing slash)       |
| `$config`       | array   | Full site config array             |

**index.php only:**
| Variable   | Type  | Description                          |
|------------|-------|--------------------------------------|
| `$posts`   | array | `['items'=>[], 'total'=>0, 'pages'=>0, 'page'=>1]` |
| `$category`| string| Current category filter (if set)    |
| `$tag`     | string| Current tag filter (if set)         |

**single.php only:**
| Variable  | Type   | Description                             |
|-----------|--------|-----------------------------------------|
| `$post`   | array  | Full post data                          |
| `$type`   | string | `'articles'` or `'pages'`              |

**search.php only:**
| Variable   | Type   | Description              |
|------------|--------|--------------------------|
| `$results` | array  | Array of matching posts  |
| `$query`   | string | Search query string      |

---

## Post Array Structure

```php
$post = [
    'title'          => 'Article Title',
    'slug'           => 'article-title',
    'content'        => '<p>HTML content…</p>',
    'excerpt'        => 'Short summary',
    'status'         => 'published',  // or 'draft'
    'categories'     => ['Tech', 'Life'],
    'tags'           => ['php', 'cms'],
    'featured_image' => 'https://example.com/img.jpg',
    'created_at'     => '2025-01-15T10:30:00+00:00',
    'updated_at'     => '2025-01-20T14:00:00+00:00',
];
```

---

## CSS Variables

FluxCMS injects these CSS variables automatically based on the active accent color:

```css
:root {
  --accent:        #6366f1;   /* The chosen accent color */
  --accent-rgb:    99,102,241; /* RGB components */
  --accent-light:  rgba(99,102,241,0.1); /* Light tint */
  --accent-medium: rgba(99,102,241,0.3); /* Medium tint */
}
```

Use `get_theme_css_vars()` in your `<head>` to inject them:

```php
<style>
<?= get_theme_css_vars() ?>
/* your styles here */
</style>
```

---

## Helper Functions

```php
// Build a URL to a theme asset
theme_asset_url('style.css')  // → /themes/my-theme/style.css

// Get active theme name
active_theme()  // → 'my-theme'

// Build URLs
base_url()      // → 'https://example.com'
```

---

## Example: Minimal Theme header.php

```php
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($post) ? htmlspecialchars($post['title']).' — ' : '' ?><?= htmlspecialchars($site_title) ?></title>
<link rel="stylesheet" href="<?= theme_asset_url('style.css') ?>">
<style><?= get_theme_css_vars() ?></style>
</head>
<body>
<header>
  <a href="<?= $base ?>/"><?= htmlspecialchars($site_title) ?></a>
  <nav>
    <?php
    $nav_pages = list_content('pages', true, 1, 20);
    foreach ($nav_pages['items'] as $np):
    ?>
    <a href="<?= $base ?>/page/<?= htmlspecialchars($np['slug']) ?>"><?= htmlspecialchars($np['title']) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main>
```

---

## Tips

- The `header.php` opens `<main>` — your templates render inside it, and `footer.php` closes it.
- Use `htmlspecialchars()` on all user-provided data when outputting.
- The `$post['content']` field contains raw HTML — output it with `<?= $post['content'] ?>` (no escaping).
- To display the accent color in inline styles: `style="color: var(--accent)"` works anywhere.
