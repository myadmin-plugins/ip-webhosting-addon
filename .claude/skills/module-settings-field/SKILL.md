---
name: module-settings-field
description: Adds a new settings field inside getSettings() using add_text_setting or similar method on the MyAdmin\Settings object. Use when user says 'add setting', 'new config option', 'add cost field', 'register a setting', or extends the webhosting.settings handler. Always wraps label/description in _() for i18n and calls setTarget('module') before and setTarget('global') after. Do NOT use for reading settings at runtime or for non-settings configuration.
---
# Module Settings Field

## Critical
- **Always** call `$settings->setTarget('module')` before adding fields and `$settings->setTarget('global')` after — omitting either corrupts the settings target for other plugins.
- **Always** wrap every human-readable string (group name, label, description) in `_()` for gettext i18n.
- The `getSettings` method receives a `GenericEvent` — extract the subject with `$event->getSubject()` to get the `\MyAdmin\Settings` instance.
- The default value **must** use `(defined(CONST_NAME) ? CONST_NAME : fallback)` — never reference an undefined constant directly, as it causes a fatal error.
- This method is a hook handler; it must be registered in `getHooks()` under `self::$module.'.settings'`.

## Instructions

1. **Verify the hook is registered** in `getHooks()` in `src/Plugin.php`:
   ```php
   self::$module.'.settings' => [__CLASS__, 'getSettings'],
   ```
   If missing, add it to the returned array before proceeding.

2. **Open `src/Plugin.php`** and locate the `getSettings(GenericEvent $event)` method. All field additions happen inside this method only.

3. **Insert the new field** between the existing `setTarget('module')` and `setTarget('global')` calls, following this exact signature:
   ```php
   $settings->add_text_setting(
       self::$module,                     // module slug, always self::$module
       _('Group Name'),                   // settings group label — wrap in _()
       'snake_case_key',                  // config key: lowercase, underscores, no prefix
       _('Field Label'),                  // human label — wrap in _()
       _('One sentence description.'),    // help text — wrap in _()
       (defined('CONST_NAME') ? CONST_NAME : default_value)  // safe default
   );
   ```

4. **Define the matching constant** (if introducing a new cost/limit). Add the constant name to the module's constants file or to the relevant `defines.php`. The constant name must be `UPPER_SNAKE_CASE` and match the key used in `add_text_setting`.

5. **Verify** the full `getSettings` method still opens with `setTarget('module')` and closes with `setTarget('global')` with **no other `setTarget` calls** in between.

6. **Run tests** to confirm no fatal errors:
   ```bash
   vendor/bin/phpunit tests/ -v
   ```

## Examples

**User says:** "Add a setting for the maximum number of dedicated IPs per account."

**Actions taken:**
1. Confirm `self::$module.'.settings' => [__CLASS__, 'getSettings']` exists in `getHooks()` in `src/Plugin.php`.
2. Add constant `WEBSITE_IP_MAX` to the defines file with default `5`.
3. In `getSettings()`, insert after the existing `add_text_setting` call:
   ```php
   $settings->add_text_setting(self::$module, _('Costs & Limits'), 'website_ip_max', _('Max Dedicated IPs'), _('Maximum number of dedicated IPs allowed per webhosting account.'), (defined('WEBSITE_IP_MAX') ? WEBSITE_IP_MAX : 5));
   ```
4. Confirm `setTarget('module')` is first and `setTarget('global')` is last.

**Result — complete `getSettings` method:**
```php
public static function getSettings(GenericEvent $event)
{
    /** @var \MyAdmin\Settings $settings **/
    $settings = $event->getSubject();
    $settings->setTarget('module');
    $settings->add_text_setting(self::$module, _('Costs & Limits'), 'website_ip_cost', _('Dedicated IP Cost'), _('This is the cost for purchasing an additional IP on top of a Website.'), (defined('WEBSITE_IP_COST') ? WEBSITE_IP_COST : 3));
    $settings->add_text_setting(self::$module, _('Costs & Limits'), 'website_ip_max', _('Max Dedicated IPs'), _('Maximum number of dedicated IPs allowed per webhosting account.'), (defined('WEBSITE_IP_MAX') ? WEBSITE_IP_MAX : 5));
    $settings->setTarget('global');
}
```

## Common Issues

- **`PHP Fatal error: Undefined constant 'WEBSITE_IP_MAX'`**: You referenced the constant directly as the default value. Fix: always guard with `(defined('CONST_NAME') ? CONST_NAME : fallback)`.
- **Settings from other plugins appear under the wrong module**: You forgot `setTarget('global')` at the end of `getSettings()`. The target remains `'module'` for all subsequent plugin settings registrations. Add `$settings->setTarget('global');` as the final line.
- **Label/description appears as raw PHP string (not translated)**: You omitted `_()` around a string. Wrap every human-readable argument in `_()`.
- **`Call to undefined method ... add_text_setting`**: `$event->getSubject()` returned something other than `\MyAdmin\Settings`. Confirm the hook key in `getHooks()` is exactly `self::$module.'.settings'` (not `'webhosting_settings'` or similar).
- **New field not visible in admin UI**: The hook was not registered. Verify `getHooks()` in `src/Plugin.php` returns `self::$module.'.settings' => [__CLASS__, 'getSettings']` and that the plugin is listed in `include/config/plugins.json` in the parent MyAdmin install.
