---
name: addon-handler-setup
description: Creates a new AddonHandler registration block inside a `getAddon` method. Use when user says 'add addon', 'register addon', 'new webhosting addon', or adds a new `load_addons` hook handler. Generates `function_requirements('class.AddonHandler')`, fluent `->set_text()`, `->set_cost()`, `->setEnable()`, `->setDisable()`, `->register()` chain followed by `$service->addAddon()`. Do NOT use for editing existing addons or for non-addon hook handlers.
---
# Addon Handler Setup

## Critical

- `function_requirements('class.AddonHandler')` MUST be called before `new \AddonHandler()` — the class is lazy-loaded.
- `$service->addAddon($addon)` MUST be the last line in `getAddon()` — omitting it silently drops the addon.
- The `getAddon` method signature MUST accept `GenericEvent $event` and extract `$service` via `$event->getSubject()`.
- Register the hook in `getHooks()` using `self::$module.'.load_addons'` as the key — never hardcode the module string.
- `doEnable` and `doDisable` MUST have the signature: `(\ServiceHandler $serviceOrder, $repeatInvoiceId, $regexMatch = false)`.

## Instructions

1. **Add the hook to `getHooks()`** in `src/Plugin.php`. The key is `self::$module.'.load_addons'`, value is `[__CLASS__, 'getAddon']`.
   ```php
   public static function getHooks(): array {
       return [
           self::$module.'.load_addons' => [__CLASS__, 'getAddon'],
           self::$module.'.settings'    => [__CLASS__, 'getSettings'],
       ];
   }
   ```
   Verify `getHooks()` returns this key before proceeding.

2. **Write the `getAddon` method** immediately after `getHooks()`. Extract `$service` from the event, call `function_requirements`, instantiate `\AddonHandler`, chain all setters, call `register()`, then call `$service->addAddon($addon)`.
   ```php
   public static function getAddon(GenericEvent $event)
   {
       /** @var \ServiceHandler $service */
       $service = $event->getSubject();
       function_requirements('class.AddonHandler');
       $addon = new \AddonHandler();
       $addon->setModule(self::$module)
           ->set_text('Your Addon Name')
           ->set_text_match('Your Addon Name (.*)')
           ->set_cost(YOUR_COST_CONSTANT)
           ->setEnable([__CLASS__, 'doEnable'])
           ->setDisable([__CLASS__, 'doDisable'])
           ->register();
       $service->addAddon($addon);
   }
   ```
   `set_text_match` must be a regex where `(.*)` captures the variable part (e.g., IP or plan name).

3. **Write `doEnable`**. It receives `\ServiceHandler $serviceOrder`. Retrieve service info and settings first. Skip WHM calls for `WEB_DIRECTADMIN` and `WEB_STORAGE` types. Log every WHM response before acting on it.
   ```php
   public static function doEnable(\ServiceHandler $serviceOrder, $repeatInvoiceId, $regexMatch = false)
   {
       $serviceInfo = $serviceOrder->getServiceInfo();
       $settings = get_module_settings(self::$module);
       if ($regexMatch === false) {
           function_requirements('get_service_master');
           $serverdata = get_service_master($serviceInfo[$settings['PREFIX'].'_server'], self::$module);
           if (in_array($serviceInfo[$settings['PREFIX'].'_type'], [get_service_define('WEB_DIRECTADMIN'), get_service_define('WEB_STORAGE')])) {
               // skip — unsupported type
           } else {
               // perform enable logic
               myadmin_log(self::$module, 'info', 'Enabling addon for '.$serviceInfo[$settings['PREFIX'].'_id'], __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
           }
       }
   }
   ```

4. **Write `doDisable`**. Same signature as `doEnable`. Always call `add_output('...')` and send an admin cancellation email via `(new \MyAdmin\Mail())->adminMail()`.
   ```php
   public static function doDisable(\ServiceHandler $serviceOrder, $repeatInvoiceId, $regexMatch = false)
   {
       $serviceInfo = $serviceOrder->getServiceInfo();
       $settings = get_module_settings(self::$module);
       $db = get_module_db(self::$module);
       // disable logic here
       add_output('Your Addon Order Canceled');
       $subject = $settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Canceled Your Addon';
       $email = $settings['TBLNAME'].' ID: '.$serviceInfo[$settings['PREFIX'].'_id'].'<br>Description: '.self::$name.'<br>';
       (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_ip_canceled.tpl');
   }
   ```

5. **Run tests** to verify hook registration and addon wiring:
   ```bash
   vendor/bin/phpunit tests/ -v
   ```

## Examples

**User says:** "Add a new addon for SSL certificates"

**Actions taken:**
1. Add `self::$module.'.load_addons' => [__CLASS__, 'getAddon']` to `getHooks()` in `src/Plugin.php`.
2. Create `getAddon(GenericEvent $event)` with `set_text('SSL Certificate')`, `set_text_match('SSL Certificate (.*)')`, `set_cost(SSL_CERT_COST)`.
3. Create `doEnable` skipping WHM for DirectAdmin/Storage, logging all API calls.
4. Create `doDisable` calling `add_output('SSL Certificate Canceled')` and `adminMail()`.

**Result:** Addon appears in `webhosting.load_addons` event, is purchasable at `SSL_CERT_COST`, enable/disable callbacks are wired.

## Common Issues

- **`Class 'AddonHandler' not found`**: `function_requirements('class.AddonHandler')` was not called before `new \AddonHandler()`. Add it as the first line inside `getAddon()`.
- **Addon silently missing from the service**: `$service->addAddon($addon)` was omitted. It must follow `->register()` — `register()` alone does not attach the addon to the service.
- **`doEnable` never fires for DirectAdmin accounts**: This is expected — the `in_array(...WEB_DIRECTADMIN...)` guard intentionally skips those. Do not remove it.
- **`set_text_match` not matching on invoice**: The regex passed to `set_text_match` must include `(.*)` and match the exact invoice line text. Test with the literal string from a real invoice.
- **WHM API returns null response**: `function_requirements('whm_api')` was skipped. Always call it before `new \xmlapi()`; always `myadmin_log` the raw response string before `json_decode()`.
