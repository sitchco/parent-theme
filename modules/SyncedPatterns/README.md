# Synced Patterns Module

This module synchronizes version-controlled WordPress block patterns from the child theme's `/patterns/` directory to the database as reusable synced patterns.

## How It Works

Patterns in the child theme's `/patterns/` directory with the `Synced: true` header are automatically registered as `wp_block` posts in WordPress. This gives you:

- **Version Control**: Patterns live in Git with full history
- **Synced Behavior**: Edit once in the CMS, updates propagate everywhere
- **Cross-Environment Deployment**: Patterns deploy with the theme
- **Code Review**: Pattern changes go through PR review

## Creating a Pattern

1. Build your layout in the WordPress editor
2. Copy the block markup (Options menu → Code editor)
3. Create a PHP file in your child theme's `/patterns/` directory:

```php
<?php
/**
 * Title: My Pattern Name
 * Slug: mytheme/my-pattern-name
 * Categories: featured
 * Description: Brief description of the pattern.
 * Keywords: keyword1, keyword2
 * Synced: true
 */
?>
<!-- wp:kadence/rowlayout {...} -->
<!-- Your block markup here -->
<!-- /wp:kadence/rowlayout -->
```

4. Run `wp synced-patterns sync` to create the synced pattern

## Pattern Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Title` | Yes | Display name in the editor |
| `Slug` | Yes | Unique identifier (e.g., `mytheme/my-pattern`) |
| `Categories` | No | Comma-separated categories (e.g., `featured, headers`) |
| `Description` | No | Brief description shown in pattern selector |
| `Keywords` | No | Comma-separated search keywords |
| `Synced` | Yes* | Set to `true` to enable database sync |
| `Sync-Strategy` | No | How to handle updates (see below) |

*Required for synced patterns. Patterns without `Synced: true` are registered as regular (non-synced) theme patterns.

## Sync Strategies

Control how theme updates affect existing CMS content:

| Strategy | Behavior |
|----------|----------|
| `overwrite` | (Default) Theme always wins - CMS edits are overwritten on sync |
| `preserve` | Only create if missing - never overwrite CMS edits |
| `manual` | Require `--force` flag to sync |

Example:
```php
/**
 * Sync-Strategy: preserve
 */
```

## WP-CLI Commands

```bash
# List all synced patterns from theme files
wp synced-patterns list

# Show sync status (current, outdated, not synced)
wp synced-patterns status

# Sync all patterns to database
wp synced-patterns sync

# Sync a specific pattern
wp synced-patterns sync mytheme/my-pattern

# Force sync (ignore preserve/manual strategy)
wp synced-patterns sync --force
```

## Workflow

### Initial Setup
1. Create pattern file with `Synced: true` in child theme's `/patterns/` directory
2. Run `wp synced-patterns sync`
3. Pattern appears in CMS under Patterns → My Patterns

### Updating a Pattern
1. Edit the pattern PHP file
2. Commit and deploy
3. Run `wp synced-patterns sync` (or auto-syncs on theme activation)
4. All instances of the pattern reflect the update

### Using in the Editor
1. Open the block inserter
2. Go to Patterns tab
3. Find your pattern under "My Patterns" or search by name
4. Insert - it behaves as a synced pattern
