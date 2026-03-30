---
name: whm-api-integration
description: Scaffolds WHM/cPanel \xmlapi API calls inside doEnable or doDisable methods. Use when user says 'call WHM', 'use xmlapi', 'set site IP', 'assign dedicated IP', or adds server interaction logic to Plugin.php. Handles function_requirements loading, xmlapi init with hash auth on port 2087, myadmin_log of every response, and admin mail on error. Do NOT use for DirectAdmin or storage service types — those are silently skipped by convention.
---
# WHM API Integration

## Critical

- **Always skip** `WEB_DIRECTADMIN` and `WEB_STORAGE` service types — wrap the entire WHM block in:
  ```php
  if (in_array($serviceInfo[$settings['PREFIX'].'_type'], [get_service_define('WEB_DIRECTADMIN'), get_service_define('WEB_STORAGE')])) {
  } else {
      // WHM logic here
  }
  ```
- **Always log** the raw API response string via `myadmin_log()` *before* calling `json_decode()` on it.
- **Never** access `$response->result[0]->status` without first logging the raw response.
- **Always** send admin mail via `(new \MyAdmin\Mail())->adminMail(...)` on both API failure and resource exhaustion (e.g., 0 free IPs).

## Instructions

1. **Resolve service context** — at the top of `doEnable`/`doDisable`:
   ```php
   $serviceInfo = $serviceOrder->getServiceInfo();
   $settings = get_module_settings(self::$module);
   function_requirements('get_service_master');
   $serverdata = get_service_master($serviceInfo[$settings['PREFIX'].'_server'], self::$module);
   ```
   Verify `$settings['PREFIX']`, `$settings['TABLE']`, `$settings['TBLNAME']`, and `$settings['TITLE_FIELD']` are available before proceeding.

2. **Guard non-WHM service types** — wrap all WHM logic in the `else` branch of the type check shown in Critical above. No `return` inside the empty `if` body — leave it empty per existing convention.

3. **Load and init `\xmlapi`**:
   ```php
   function_requirements('whm_api');
   $whm = new \xmlapi($serverdata[$settings['PREFIX'].'_ip']);
   //$whm->set_debug('true');
   $whm->set_port('2087');
   $whm->set_protocol('https');
   $whm->set_output('json');
   $whm->set_auth_type('hash');
   $whm->set_user('root');
   $whm->set_hash($serverdata[$settings['PREFIX'].'_key']);
   ```
   Leave the commented-out `set_debug` line in place — it matches the existing codebase style.

4. **Make the WHM API call and log the raw response** (uses output from step 3):
   ```php
   $response = $whm->setsiteip($newIp, $serviceInfo[$settings['PREFIX'].'_username']);
   myadmin_log(self::$module, 'info', "WHM setsiteip({$newIp}, {$serviceInfo[$settings['PREFIX'].'_username']}) Response: {$response}", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
   $response = json_decode($response);
   ```
   Replace `setsiteip` with the actual WHM API method name for your operation.

5. **Branch on `$response->result[0]->status`** (uses output from step 4):
   ```php
   if ($response->result[0]->status == 1) {
       // success: update DB
       $db = get_module_db(self::$module);
       $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='{$newIp}' where {$settings['PREFIX']}_id={$serviceInfo[$settings['PREFIX'].'_id']}", __LINE__, __FILE__);
       myadmin_log(self::$module, 'info', "Success message", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
   } else {
       myadmin_log(self::$module, 'info', "Error message", __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
       $subject = 'Error Setting IP '.$serviceInfo[$settings['PREFIX'].'_ip'].' on '.$settings['TBLNAME'].' '.$serviceInfo[$settings['TITLE_FIELD']];
       (new \MyAdmin\Mail())->adminMail($subject, $subject, false, 'admin/website_no_ips.tpl');
   }
   ```

6. **Handle resource exhaustion** (e.g., no free IPs) before making the API call:
   ```php
   if (count($ips['free']) > 0) {
       // proceed with WHM call (steps 4–5)
   } else {
       $subject = "0 Free IPs On {$settings['TBLNAME']} Server {$serverdata[$settings['PREFIX'].'_name']}";
       (new \MyAdmin\Mail())->adminMail($subject, "webserver {$serviceInfo[$settings['PREFIX'].'_id']} Has Pending IPS<br>\n".$subject, false, 'admin/website_no_ips.tpl');
       myadmin_log(self::$module, 'info', $subject, __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
   }
   ```

7. **Verify** by running: `vendor/bin/phpunit tests/ -v`

## Examples

**User says:** "Add a WHM call in doEnable to assign a free IP to the site"

**Actions taken:**
1. Resolve `$serviceInfo`, `$settings`, `$serverdata` at top of `doEnable`
2. Add `WEB_DIRECTADMIN`/`WEB_STORAGE` type guard
3. Call `function_requirements('whm_api')`, init `\xmlapi` with port `2087`, `https`, `json`, `hash` auth
4. Call `self::getIps($whm)` to classify server IPs into `main`/`used`/`free`/`shared`
5. Guard `count($ips['free']) > 0`, else email admin via `website_no_ips.tpl`
6. Call `$whm->setsiteip($ips['free'][0], $username)`, log raw response, `json_decode`
7. On `status == 1`: `UPDATE` table via `get_module_db()` + log success
8. On failure: log error + `adminMail` with `website_no_ips.tpl`

**Result:** Matches the pattern in `src/Plugin.php`.

## Common Issues

- **`Call to undefined function function_requirements()`** — this helper is provided by the MyAdmin host environment. It will not exist during unit tests. Mock or skip WHM blocks in tests using reflection on source (see `tests/PluginTest.php`).
- **`Trying to get property 'status' of non-object`** — `json_decode` returned `null` because the WHM response was not valid JSON. Confirm `set_output('json')` is called and check the logged raw response string for an HTML error page (WHM auth failure returns HTML).
- **`set_hash()` has no effect / 401 from WHM** — the hash is read from `$serverdata[$settings['PREFIX'].'_key']`. In `doDisable`, the existing code uses the literal key `'website_key'` — use `$settings['PREFIX'].'_key'` instead to stay consistent with `doEnable`.
- **Type guard silently skips all logic** — if service type constants (`WEB_DIRECTADMIN`, `WEB_STORAGE`) are not defined in the test/dev environment, `get_service_define()` may return `null` and `in_array` will not match. Ensure constants are defined in bootstrap or mock `get_service_define()`.
- **Admin mail not sent on error** — confirm the template path `'admin/website_no_ips.tpl'` exists under `include/templates/email/admin/`. Wrong path causes a silent Smarty failure.
