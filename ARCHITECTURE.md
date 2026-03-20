# Architecture: extension-installer

## Purpose

A Composer plugin that automatically discovers and registers PHPStan extensions. When PHPStan extensions are installed via Composer, this plugin generates a `GeneratedConfig.php` file listing all discovered extensions so PHPStan loads them without manual `includes` configuration.

## Directory Structure

```
src/
  Plugin.php            — Composer plugin: subscribes to post-install/update events,
                          scans installed packages, generates GeneratedConfig.php
  GeneratedConfig.php   — Stub file committed to the repo; overwritten at install time

e2e/
  integration/          — End-to-end test: installs a real PHPStan extension via Composer
  ignore/               — End-to-end test: validates extension ignore configuration
```

## Key Design Decisions

- **Composer plugin API v2** — uses `PluginInterface` + `EventSubscriberInterface` from `composer-plugin-api: ^2.0`.
- **Stable stub file** — `GeneratedConfig.php` contains an empty stub so projects using `--no-scripts` don't break. The plugin overwrites it at install time.
- **Ignore list** — projects can suppress specific extensions via `extra.phpstan/extension-installer.ignore` in their `composer.json`, giving teams opt-out control.
- **Hash-based change detection** — the plugin computes `md5_file()` of the current generated config and skips regeneration if nothing changed, avoiding unnecessary file writes.
- **Relative-path safety** — extension paths are stored relative to the vendor directory so the project can be renamed/moved without invalidating config.

## Extension Points

- Extensions declare themselves via `"type": "phpstan-extension"` or `extra.phpstan` in their `composer.json`.
- No public extension points in the plugin itself; behaviour is configured via `composer.json` only.

## Dependency Flow

```
Composer post-install-cmd / post-update-cmd
  └── Plugin::regenerate_config()
        ├── scan installed packages for PHPStan extensions
        ├── resolve extension neon file paths
        ├── apply ignore list from root package extra config
        └── write src/GeneratedConfig.php
              └── PHPStan reads at analysis time via phpstan.neon includes
```
