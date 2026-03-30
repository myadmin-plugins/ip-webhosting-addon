---
name: plugin-hook-registration
description: Adds a new Symfony EventDispatcher hook to src/Plugin.php in the myadmin-ip-webhosting-addon plugin. Use when user says 'add hook', 'register event', 'listen to webhosting event', 'add a new hook key to getHooks', or needs a new static handler method. Generates the getHooks() array entry and the corresponding static handler method with GenericEvent $event signature. Do NOT use for modifying existing hook handlers (getAddon, getSettings) or for non-Plugin.php event wiring.
---
# plugin-hook-registration

## Critical

- Hook keys MUST follow the pattern `self::$module . '.<event_name>'` — never hardcode the module name string.
- Handler methods MUST be `public static` and accept exactly one parameter: `GenericEvent $event` (fully-qualified import already present at top of file).
- `getHooks()` returns `array<string,string[]>` — each value is `[__CLASS__, 'methodName']`.
- After editing, run `vendor/bin/phpunit tests/ -v` and verify ALL existing hook tests still pass — especially `testGetHooksReturnsTwoEntries` (update count assertion if test exists for exact count).
- Tabs for indentation — never spaces.

## Instructions

1. **Identify the event name.** Determine the `<event_name>` suffix (e.g., `load_addons`, `settings`, `renewal`). The full hook key will be `self::$module . '.<event_name>'`.

2. **Add the hook entry to `getHooks()` in `src/Plugin.php`.**
   Open the return array and append a new line following the existing pattern:
   ```php
   public static function getHooks()
   {
       return [
           self::$module.'.load_addons' => [__CLASS__, 'getAddon'],
           self::$module.'.settings'    => [__CLASS__, 'getSettings'],
           self::$module.'.<event_name>' => [__CLASS__, '<handlerMethod>'],
       ];
   }
   ```
   Verify the key uses `self::$module` concatenation, not a hardcoded string, before proceeding.

3. **Add the static handler method to `src/Plugin.php`** after the last existing handler (after `getSettings()` closing brace, before the closing `}` of the class). Use this exact signature and docblock pattern from `getAddon` and `getSettings`:
   ```php
   /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
   public static function <handlerMethod>(GenericEvent $event)
   {
       /**
        * @var \<SubjectType> $<subject>
        */
       $<subject> = $event->getSubject();
       // handler logic here
   }
   ```
   - Use `$event->getSubject()` to retrieve the subject; annotate its type with a `@var` docblock.
   - For settings handlers: call `$settings->setTarget('module')`, add settings, then `$settings->setTarget('global')`.
   - For service/addon handlers: call `function_requirements(...)` before using non-autoloaded globals.

4. **Verify the handler method is public and static** by checking the method signature. Confirm `GenericEvent` is used without an import alias — the `use Symfony\Component\EventDispatcher\GenericEvent;` import is already at line 5.

5. **Update `testGetHooksReturnsTwoEntries` in `tests/PluginTest.php`** if that test asserts a fixed hook count — change `assertCount(2, $hooks)` to the new total. Also add:
   - A key-existence test following the pattern of `testGetHooksContainsLoadAddonsKey`.
   - A callable-mapping test following `testGetHooksLoadAddonsCallable`.

6. **Run tests** to confirm nothing is broken:
   ```bash
   vendor/bin/phpunit tests/ -v
   ```

## Examples

**User says:** "Add a hook for `webhosting.renewal` that logs the renewal event."

**Actions taken:**

_Step 2 — add to `getHooks()` in `src/Plugin.php`:_
```php
return [
    self::$module.'.load_addons' => [__CLASS__, 'getAddon'],
    self::$module.'.settings'    => [__CLASS__, 'getSettings'],
    self::$module.'.renewal'     => [__CLASS__, 'onRenewal'],
];
```

_Step 3 — add handler after `getSettings()`:_
```php
/**
 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
 */
public static function onRenewal(GenericEvent $event)
{
	/**
	 * @var \ServiceHandler $service
	 */
	$service = $event->getSubject();
	$settings = get_module_settings(self::$module);
	$serviceInfo = $service->getServiceInfo();
	myadmin_log(self::$module, 'info', 'Renewal event for service '.$serviceInfo[$settings['PREFIX'].'_id'], __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
}
```

_Step 5 — update `tests/PluginTest.php`:_
```php
// Change assertCount(2, ...) → assertCount(3, ...)
// Add:
public function testGetHooksContainsRenewalKey(): void
{
    $hooks = Plugin::getHooks();
    $this->assertArrayHasKey(Plugin::$module . '.renewal', $hooks);
}
```

**Result:** `vendor/bin/phpunit` passes; hook is registered and dispatched by the event system.

## Common Issues

- **`testGetHooksReturnsTwoEntries` fails with `assertCount(2)` after adding a hook:** Update the count in `tests/PluginTest.php` to match the new total.
- **`testNoUnexpectedPublicMethods` fails:** Add the new handler method name to the `$expectedMethods` array in that test in `tests/PluginTest.php`.
- **`testHookCallbackMethodsExist` fails with "references non-existent method":** The method name in `getHooks()` does not match the actual method name declared — check for typos in the `[__CLASS__, 'methodName']` string.
- **PHP fatal: `Call to undefined function function_requirements()`** during tests: This is expected — `function_requirements()` is a MyAdmin global not available in unit test scope. Guard with `if (function_exists('function_requirements'))` or test via reflection only.
- **Indentation mixed tabs/spaces causes CS failure:** Run `make php-cs-fixer` from the parent MyAdmin root. Ensure your editor inserts tabs, not 4-space indents, in `src/Plugin.php`.
