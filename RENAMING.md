# Renaming / Rebranding the Plugin

This guide explains how to rename **Naano AI Website Builder** to your own brand.

---

## 1. Overview

The plugin uses the following name tokens throughout the codebase:

| Token type | Example |
|------------|---------|
| PHP constants | `NAANO_VERSION`, `NAANO_PLUGIN_DIR` |
| PHP class names | `Naano_Admin_Page`, `Naano_LLM_Router` |
| PHP functions / hooks / options / meta keys | `naano_init`, `naano_provider`, `_naano_sections` |
| Slugs / CSS classes / JS handles | `naano-ai-builder`, `.naano-card`, `naano-builder` |
| Plugin display name | `Naano AI Website Builder`, `Naano AI Builder` |
| JS global objects | `NaanoBuilder`, `NaanoPreview`, `naanoBuilderData` |
| Text domain | `naano-ai-website-builder` |
| Folder & main file name | `naano-ai-website-builder/` |

---

## 2. String Replacement Map

| Find | Replace With | Where |
|------|-------------|-------|
| `NAANO_` | `YOURPREFIX_` | PHP constants (uppercase) |
| `Naano_` | `Yourprefix_` | PHP class names (PascalCase) |
| `naano_` | `yourprefix_` | Functions, options, meta keys, AJAX actions |
| `naano-` | `yourprefix-` | Slugs, CSS classes, JS script handles, text domain |
| `Naano AI Website Builder` | `Your Plugin Name` | Plugin header, display names |
| `Naano AI Builder` | `Your Builder Name` | Menu titles |
| `NaanoBuilder` | `YourprefixBuilder` | JS objects |
| `NaanoPreview` | `YourprefixPreview` | JS objects |
| `naanoBuilderData` | `yourprefixBuilderData` | JS localized data object |
| `naano-ai-website-builder` | `your-plugin-slug` | Folder name, main PHP file name, text domain |

---

## 3. Files to Rename

1. **Plugin folder**: `naano-ai-website-builder/` → `your-plugin-slug/`
2. **Main PHP file**: `naano-ai-website-builder.php` → `your-plugin-slug.php`

---

## 4. Automated Find & Replace

### Linux / macOS (bash + sed)

```bash
# Run from the parent directory of the plugin folder.
# Replace "yourprefix" and "your-plugin-slug" with your actual values.

OLD_UPPER="NAANO"
NEW_UPPER="YOURPREFIX"

OLD_PASCAL="Naano"
NEW_PASCAL="Yourprefix"

OLD_LOWER="naano"
NEW_LOWER="yourprefix"

OLD_SLUG="naano-ai-website-builder"
NEW_SLUG="your-plugin-slug"

OLD_JS="naano"
NEW_JS="yourprefix"

PLUGIN_DIR="./${OLD_SLUG}"

# 1. Find & replace all strings in PHP, JS, CSS, and PHP template files.
find "${PLUGIN_DIR}" -type f \( -name "*.php" -o -name "*.js" -o -name "*.css" \) | while read -r file; do
  sed -i \
    -e "s/${OLD_UPPER}_/${NEW_UPPER}_/g" \
    -e "s/${OLD_PASCAL}_/${NEW_PASCAL}_/g" \
    -e "s/${OLD_LOWER}_/${NEW_LOWER}_/g" \
    -e "s/${OLD_SLUG}/${NEW_SLUG}/g" \
    -e "s/Naano AI Website Builder/Your Plugin Name/g" \
    -e "s/Naano AI Builder/Your Builder Name/g" \
    -e "s/NaanoBuilder/YourprefixBuilder/g" \
    -e "s/NaanoPreview/YourprefixPreview/g" \
    -e "s/naanoBuilderData/yourprefixBuilderData/g" \
    "$file"
done

# 2. Rename PHP class files.
for f in "${PLUGIN_DIR}"/includes/*.php; do
  newname=$(echo "$f" | sed "s/${OLD_LOWER}-/${NEW_LOWER}-/g")
  [ "$f" != "$newname" ] && mv "$f" "$newname"
done

# 3. Rename main plugin file.
mv "${PLUGIN_DIR}/${OLD_SLUG}.php" "${PLUGIN_DIR}/${NEW_SLUG}.php"

# 4. Rename the plugin folder itself.
mv "${PLUGIN_DIR}" "./${NEW_SLUG}"

echo "Done! Remember to update the database (see Section 5)."
```

### Windows (PowerShell)

```powershell
$OldSlug    = "naano-ai-website-builder"
$NewSlug    = "your-plugin-slug"
$OldUpper   = "NAANO"
$NewUpper   = "YOURPREFIX"
$OldPascal  = "Naano"
$NewPascal  = "Yourprefix"
$OldLower   = "naano"
$NewLower   = "yourprefix"

$PluginDir = ".\$OldSlug"

Get-ChildItem -Path $PluginDir -Recurse -Include "*.php","*.js","*.css" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    $content = $content -replace "${OldUpper}_", "${NewUpper}_"
    $content = $content -replace "${OldPascal}_", "${NewPascal}_"
    $content = $content -replace "${OldLower}_", "${NewLower}_"
    $content = $content -replace $OldSlug, $NewSlug
    $content = $content -replace "Naano AI Website Builder", "Your Plugin Name"
    $content = $content -replace "Naano AI Builder", "Your Builder Name"
    $content = $content -replace "NaanoBuilder", "YourprefixBuilder"
    $content = $content -replace "NaanoPreview", "YourprefixPreview"
    $content = $content -replace "naanoBuilderData", "yourprefixBuilderData"
    Set-Content $_.FullName $content
}

# Rename main PHP file.
Rename-Item "$PluginDir\$OldSlug.php" "$NewSlug.php"

# Rename plugin folder.
Rename-Item $PluginDir $NewSlug

Write-Host "Done! Remember to update the database (see Section 5)."
```

---

## 5. WordPress Database Migration

Run this as a **WP-CLI command** or as a temporary mu-plugin after renaming.

```php
<?php
/**
 * Naano → YourPrefix database migration.
 * Drop this file in /wp-content/mu-plugins/ and load your site ONCE,
 * then delete the file.
 */

add_action( 'init', function () {
    global $wpdb;

    // --- Options ---
    $option_map = [
        'naano_provider'  => 'yourprefix_provider',
        'naano_api_key'   => 'yourprefix_api_key',
        'naano_model'     => 'yourprefix_model',
        'naano_variables' => 'yourprefix_variables',
    ];

    foreach ( $option_map as $old => $new ) {
        $value = get_option( $old );
        if ( false !== $value ) {
            update_option( $new, $value );
            delete_option( $old );
        }
    }

    // --- Post meta ---
    $meta_map = [
        '_naano_sections'     => '_yourprefix_sections',
        '_naano_references'   => '_yourprefix_references',
        '_naano_conversation' => '_yourprefix_conversation',
    ];

    foreach ( $meta_map as $old_key => $new_key ) {
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
                $new_key,
                $old_key
            )
        );
    }

    wp_die( 'Migration complete. Delete this mu-plugin file now.' );
} );
```

---

## 6. Important Warnings

- **Deactivate** the plugin before renaming files/folders.
- **Backup your database** before running the migration snippet.
- **Test on a staging site** before applying to production.
- Search your theme and any custom code for hardcoded references to `naano_` or `naano-`.
- If you have existing generated pages saved as WP pages, their content does not need migration — it is plain HTML.
