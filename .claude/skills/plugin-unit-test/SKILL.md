---
name: plugin-unit-test
description: Writes PHPUnit ^9.6 test cases in `tests/PluginTest.php` for the `Plugin` class static methods. Use when user says 'add test', 'write test', 'test getHooks', 'add coverage', or wants tests for `src/Plugin.php`. Bootstrap is `vendor/autoload.php`, run via `vendor/bin/phpunit`. Do NOT use for integration tests requiring a live WHM server or real DB connections.
---
# plugin-unit-test

## Critical

- Never call `doEnable`/`doDisable` directly — they need live WHM/DB; test them via **source inspection** only (`ReflectionMethod` + `file()`)
- Never mock `get_module_db`, `myadmin_log`, or `\xmlapi` — unavailable in unit context; use `assertStringContainsString` on method source instead
- All tests go in `tests/PluginTest.php`, namespace `Detain\MyAdminWebhostingIp\Tests`, class `PluginTest extends TestCase`
- Run with: `vendor/bin/phpunit tests/ -v`

## Instructions

1. **Open `tests/PluginTest.php`** and verify the file header matches:
   ```php
   <?php
   namespace Detain\MyAdminWebhostingIp\Tests;
   use Detain\MyAdminWebhostingIp\Plugin;
   use PHPUnit\Framework\TestCase;
   use ReflectionClass;
   ```
   Add `private ReflectionClass $reflector;` and a `setUp()` that sets `$this->reflector = new ReflectionClass(Plugin::class);`.

2. **Static property tests** — assert exact values via `Plugin::$property`:
   ```php
   $this->assertSame('webhosting', Plugin::$module);
   $this->assertSame('addon',      Plugin::$type);
   ```
   Verify property is public+static via `$this->reflector->getProperty($name)->isPublic()`.

3. **`getHooks()` tests** — call `Plugin::getHooks()`, assert:
   - Returns array with exactly 2 keys
   - Keys are `Plugin::$module . '.load_addons'` and `Plugin::$module . '.settings'`
   - Each value is `[Plugin::class, 'getAddon']` / `[Plugin::class, 'getSettings']`
   - `is_callable($callback)` is true for each entry

4. **Reflection signature tests** — use `$this->reflector->getMethod($name)` to assert visibility and parameters without invoking:
   ```php
   $method = $this->reflector->getMethod('doEnable');
   $this->assertTrue($method->isPublic());
   $this->assertTrue($method->isStatic());
   $this->assertSame(2, $method->getNumberOfRequiredParameters()); // $regexMatch is optional
   $params = $method->getParameters();
   $this->assertSame('serviceOrder',    $params[0]->getName());
   $this->assertSame('repeatInvoiceId', $params[1]->getName());
   $this->assertSame('regexMatch',      $params[2]->getName());
   $this->assertFalse($params[2]->getDefaultValue()); // default is false
   ```
   Apply the same pattern to `doDisable`, `getAddon`, `getSettings`, `getIps`.

5. **Source inspection tests** — extract method body with `file()` + `ReflectionMethod` line numbers, then `assertStringContainsString`:
   ```php
   $method    = $this->reflector->getMethod('doEnable');
   $filename  = $method->getFileName();
   $source    = implode('', array_slice(
       file($filename),
       $method->getStartLine() - 1,
       $method->getEndLine() - $method->getStartLine() + 1
   ));
   $this->assertStringContainsString('get_module_db', $source);
   $this->assertStringContainsString('myadmin_log',   $source);
   $this->assertStringContainsString('update',        $source);
   ```
   For `doDisable`, also assert `adminMail` and `website_ip_canceled.tpl`.
   For `getAddon`, assert `AddonHandler`, `set_cost`, `WEBSITE_IP_COST`, `register`, `addAddon`.
   For `getSettings`, assert `setTarget('module')`, `setTarget('global')`, `add_text_setting`, `website_ip_cost`.
   For `getIps`, assert `'main'`, `'used'`, `'free'`, `'shared'`, `listips()`.

6. **Run and verify** — all tests must pass before finishing:
   ```bash
   vendor/bin/phpunit tests/ -v
   ```

## Examples

**User says:** "Add a test that getHooks returns callable entries"

**Actions taken:**
1. Read `tests/PluginTest.php` and `src/Plugin.php`
2. Add test in the `// Hook Integration Tests` section:
```php
public function testHookCallbacksAreCallable(): void
{
    $hooks = Plugin::getHooks();
    foreach ($hooks as $callback) {
        $this->assertTrue(is_callable($callback));
    }
}
```
3. Run `vendor/bin/phpunit tests/ -v` — confirm pass

**Result:** New test asserts callability without invoking WHM code.

## Common Issues

- **"Call to undefined function get_module_settings()"** — you called `getSettings`/`doEnable`/`doDisable` directly. Switch to source inspection via `ReflectionMethod` + `file()` instead.
- **"Class 'xmlapi' not found"** — same cause as above. Never instantiate or invoke methods that bootstrap WHM. Use reflection only.
- **`assertCount(5, $staticProps)` fails** — `Plugin::$staticPropertyCount` changed. Recount with `array_filter($this->reflector->getProperties(), fn($p) => $p->isStatic())` and update the assertion.
- **`getDefaultValue()` throws `ReflectionException`** — parameter has no default; guard with `$params[2]->isOptional()` check first.
- **Coverage report empty** — pass `--whitelist src/` flag: `vendor/bin/phpunit tests/ -v --coverage-clover coverage.xml --whitelist src/`
