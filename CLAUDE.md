# Dedicated IP Webhosting Addon — MyAdmin Plugin

## Overview
Composer plugin that sells dedicated IP addresses as an addon for cPanel/WHM webhosting services in MyAdmin.
- **Namespace**: `Detain\MyAdminWebhostingIp\` → `src/` · tests: `Detain\MyAdminWebhostingIp\Tests\` → `tests/`
- **Module**: `webhosting` · **Type**: `addon`
- **Deps**: `symfony/event-dispatcher ^5.0`

## Commands
```bash
composer install
vendor/bin/phpunit tests/ -v
vendor/bin/phpunit tests/ -v --coverage-clover coverage.xml --whitelist src/
```

## Architecture
**Entry**: `src/Plugin.php` · static class `Plugin`
**Hooks**: `getHooks()` → `webhosting.load_addons` → `getAddon()` · `webhosting.settings` → `getSettings()`
**WHM API**: `\xmlapi` (port `2087`, protocol `https`, output `json`, auth `hash`) via `function_requirements('whm_api')`
**Addon reg**: `function_requirements('class.AddonHandler')` → `new \AddonHandler()` → fluent chain → `register()`
**DB**: `get_module_db(self::$module)` · raw `UPDATE` on `$settings['TABLE']` using `$settings['PREFIX']`
**Logging**: `myadmin_log(self::$module, 'info', $msg, __LINE__, __FILE__, self::$module, $serviceId)`
**Mail**: `(new \MyAdmin\Mail())->adminMail($subject, $body, false, 'admin/website_no_ips.tpl')`
**Settings**: `get_module_settings(self::$module)` → `PREFIX`, `TABLE`, `TBLNAME`, `TITLE_FIELD`
**CI/CD**: `.github/` workflows automate testing and deployment for this plugin
**IDE**: `.idea/` stores PhpStorm project config including `inspectionProfiles/`, `deployment.xml`, and `encodings.xml`

## Key Patterns

### Hook Registration
```php
public static function getHooks(): array {
    return [
        self::$module.'.load_addons' => [__CLASS__, 'getAddon'],
        self::$module.'.settings'    => [__CLASS__, 'getSettings'],
    ];
}
```

### AddonHandler Registration
```php
function_requirements('class.AddonHandler');
$addon = new \AddonHandler();
$addon->setModule(self::$module)
    ->set_text('Dedicated IP')
    ->set_text_match('Dedicated IP (.*)')
    ->set_cost(WEBSITE_IP_COST)
    ->setEnable([__CLASS__, 'doEnable'])
    ->setDisable([__CLASS__, 'doDisable'])
    ->register();
```

### WHM API Init
```php
function_requirements('whm_api');
$whm = new \xmlapi($serverdata[$settings['PREFIX'].'_ip']);
$whm->set_port('2087');
$whm->set_protocol('https');
$whm->set_output('json');
$whm->set_auth_type('hash');
$whm->set_user('root');
$whm->set_hash($serverdata[$settings['PREFIX'].'_key']);
```

### Module Settings
```php
$settings->setTarget('module');
$settings->add_text_setting(self::$module, _('Costs & Limits'), 'website_ip_cost', _('Label'), _('Desc'), WEBSITE_IP_COST);
$settings->setTarget('global');
```

## Conventions
- Tabs for indentation (`.scrutinizer.yml` coding style)
- camelCase properties and parameters
- Skip WHM calls when service type is `WEB_DIRECTADMIN` or `WEB_STORAGE`
- Log all WHM API responses via `myadmin_log()` before acting on `$response->result[0]->status`
- Email admin on errors using `admin/website_no_ips.tpl` or `admin/website_ip_canceled.tpl`
- Test bootstrap: `phpunit.xml.dist`

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
