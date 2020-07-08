UPGRADE FROM 6.2.x to 6.3
=======================

Table of content
----------------

* [Core](#core)

Core
----

* The `\Shopware\Core\System\Snippet\Files\SnippetFileInterface` is deprecated, please provide your snippet files in the right directory with the right name so shopware is able to autoload them.
Take a look at the `Autoloading of Storefront snippets` section in this guide: `Docs/Resources/current/30-theme-guide/40-snippets.md`, for more information.
After that you are able to delete your implementation of the `SnippetFileInterface`.
* The behaviour when uninstalling a plugin has changed: `keepMigrations` now has the same value as `keepUserData` in `\Shopware\Core\Framework\Plugin\Context\UninstallContext` by default.
    * From now on migrations will be removed if the user data should be removed, and kept if the user data should be kept.
    * The `enableKeepMigrations()` function is no longer to be used and will be removed along with `keepMigrations()` in v6.4.0.
    * Please note: In case of a complete uninstall all tables should be removed as well. Please verify the uninstall method of your plugin complies with this.
