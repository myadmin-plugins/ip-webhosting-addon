<?php

namespace Detain\MyAdminWebhostingIp\Tests;

use Detain\MyAdminWebhostingIp\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test suite for the Dedicated IP Webhosting Addon Plugin.
 *
 * @covers \Detain\MyAdminWebhostingIp\Plugin
 */
class PluginTest extends TestCase
{
    /**
     * @var ReflectionClass<Plugin>
     */
    private ReflectionClass $reflector;

    protected function setUp(): void
    {
        $this->reflector = new ReflectionClass(Plugin::class);
    }

    // ---------------------------------------------------------------
    // Class Structure Tests
    // ---------------------------------------------------------------

    /**
     * Tests that the Plugin class can be instantiated.
     */
    public function testPluginCanBeInstantiated(): void
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * Tests that the Plugin class exists in the expected namespace.
     */
    public function testClassExistsInCorrectNamespace(): void
    {
        $this->assertTrue(class_exists(Plugin::class));
        $this->assertSame('Detain\MyAdminWebhostingIp\Plugin', $this->reflector->getName());
    }

    /**
     * Tests that the Plugin class is not abstract.
     */
    public function testClassIsNotAbstract(): void
    {
        $this->assertFalse($this->reflector->isAbstract());
    }

    /**
     * Tests that the Plugin class is not final.
     */
    public function testClassIsNotFinal(): void
    {
        $this->assertFalse($this->reflector->isFinal());
    }

    // ---------------------------------------------------------------
    // Static Property Tests
    // ---------------------------------------------------------------

    /**
     * Tests that the $name static property has the expected value.
     */
    public function testNameStaticProperty(): void
    {
        $this->assertSame('Dedicated IP Webhosting Addon', Plugin::$name);
    }

    /**
     * Tests that the $description static property has the expected value.
     */
    public function testDescriptionStaticProperty(): void
    {
        $this->assertSame('Allows selling of Dedicated IP Addon for Webhosting.', Plugin::$description);
    }

    /**
     * Tests that the $help static property is an empty string.
     */
    public function testHelpStaticPropertyIsEmptyString(): void
    {
        $this->assertSame('', Plugin::$help);
    }

    /**
     * Tests that the $module static property has the expected value.
     */
    public function testModuleStaticProperty(): void
    {
        $this->assertSame('webhosting', Plugin::$module);
    }

    /**
     * Tests that the $type static property has the expected value.
     */
    public function testTypeStaticProperty(): void
    {
        $this->assertSame('addon', Plugin::$type);
    }

    /**
     * Tests that all static properties are of type string.
     */
    public function testStaticPropertiesAreStrings(): void
    {
        $this->assertIsString(Plugin::$name);
        $this->assertIsString(Plugin::$description);
        $this->assertIsString(Plugin::$help);
        $this->assertIsString(Plugin::$module);
        $this->assertIsString(Plugin::$type);
    }

    /**
     * Tests that the static properties are publicly accessible.
     */
    public function testStaticPropertiesArePublic(): void
    {
        $properties = ['name', 'description', 'help', 'module', 'type'];
        foreach ($properties as $property) {
            $prop = $this->reflector->getProperty($property);
            $this->assertTrue($prop->isPublic(), "Property \${$property} should be public");
            $this->assertTrue($prop->isStatic(), "Property \${$property} should be static");
        }
    }

    /**
     * Tests that there are exactly five static properties.
     */
    public function testStaticPropertyCount(): void
    {
        $staticProps = array_filter(
            $this->reflector->getProperties(),
            static fn(\ReflectionProperty $p) => $p->isStatic()
        );
        $this->assertCount(5, $staticProps);
    }

    // ---------------------------------------------------------------
    // getHooks() Tests
    // ---------------------------------------------------------------

    /**
     * Tests that getHooks returns an array.
     */
    public function testGetHooksReturnsArray(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertIsArray($hooks);
    }

    /**
     * Tests that getHooks returns exactly two hook entries.
     */
    public function testGetHooksReturnsTwoEntries(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertCount(2, $hooks);
    }

    /**
     * Tests that the load_addons hook key is correctly formed using the module name.
     */
    public function testGetHooksContainsLoadAddonsKey(): void
    {
        $hooks = Plugin::getHooks();
        $expectedKey = Plugin::$module . '.load_addons';
        $this->assertArrayHasKey($expectedKey, $hooks);
    }

    /**
     * Tests that the settings hook key is correctly formed using the module name.
     */
    public function testGetHooksContainsSettingsKey(): void
    {
        $hooks = Plugin::getHooks();
        $expectedKey = Plugin::$module . '.settings';
        $this->assertArrayHasKey($expectedKey, $hooks);
    }

    /**
     * Tests that the load_addons hook maps to the getAddon method of Plugin.
     */
    public function testGetHooksLoadAddonsCallable(): void
    {
        $hooks = Plugin::getHooks();
        $callback = $hooks[Plugin::$module . '.load_addons'];
        $this->assertIsArray($callback);
        $this->assertCount(2, $callback);
        $this->assertSame(Plugin::class, $callback[0]);
        $this->assertSame('getAddon', $callback[1]);
    }

    /**
     * Tests that the settings hook maps to the getSettings method of Plugin.
     */
    public function testGetHooksSettingsCallable(): void
    {
        $hooks = Plugin::getHooks();
        $callback = $hooks[Plugin::$module . '.settings'];
        $this->assertIsArray($callback);
        $this->assertCount(2, $callback);
        $this->assertSame(Plugin::class, $callback[0]);
        $this->assertSame('getSettings', $callback[1]);
    }

    /**
     * Tests that getHooks keys use the current module property value.
     */
    public function testGetHooksKeysMatchModuleProperty(): void
    {
        $hooks = Plugin::getHooks();
        foreach (array_keys($hooks) as $key) {
            $this->assertStringStartsWith(Plugin::$module . '.', $key);
        }
    }

    // ---------------------------------------------------------------
    // Method Signature / Reflection Tests
    // ---------------------------------------------------------------

    /**
     * Tests that the constructor exists and takes no required parameters.
     */
    public function testConstructorHasNoRequiredParameters(): void
    {
        $constructor = $this->reflector->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertSame(0, $constructor->getNumberOfRequiredParameters());
    }

    /**
     * Tests that getHooks is a public static method.
     */
    public function testGetHooksIsPublicStatic(): void
    {
        $method = $this->reflector->getMethod('getHooks');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Tests that getHooks takes no parameters.
     */
    public function testGetHooksHasNoParameters(): void
    {
        $method = $this->reflector->getMethod('getHooks');
        $this->assertSame(0, $method->getNumberOfParameters());
    }

    /**
     * Tests that getAddon is a public static method.
     */
    public function testGetAddonIsPublicStatic(): void
    {
        $method = $this->reflector->getMethod('getAddon');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Tests that getAddon accepts exactly one parameter of GenericEvent type.
     */
    public function testGetAddonAcceptsGenericEvent(): void
    {
        $method = $this->reflector->getMethod('getAddon');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());
        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame('Symfony\Component\EventDispatcher\GenericEvent', $type->getName());
    }

    /**
     * Tests that getSettings is a public static method.
     */
    public function testGetSettingsIsPublicStatic(): void
    {
        $method = $this->reflector->getMethod('getSettings');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Tests that getSettings accepts exactly one parameter of GenericEvent type.
     */
    public function testGetSettingsAcceptsGenericEvent(): void
    {
        $method = $this->reflector->getMethod('getSettings');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());
        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame('Symfony\Component\EventDispatcher\GenericEvent', $type->getName());
    }

    /**
     * Tests that getIps is a public static method.
     */
    public function testGetIpsIsPublicStatic(): void
    {
        $method = $this->reflector->getMethod('getIps');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Tests that getIps accepts exactly one parameter.
     */
    public function testGetIpsAcceptsOneParameter(): void
    {
        $method = $this->reflector->getMethod('getIps');
        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame(1, $method->getNumberOfRequiredParameters());
    }

    /**
     * Tests that doEnable is a public static method.
     */
    public function testDoEnableIsPublicStatic(): void
    {
        $method = $this->reflector->getMethod('doEnable');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Tests that doEnable accepts three parameters, one optional.
     */
    public function testDoEnableParameterSignature(): void
    {
        $method = $this->reflector->getMethod('doEnable');
        $params = $method->getParameters();
        $this->assertCount(3, $params);
        $this->assertSame('serviceOrder', $params[0]->getName());
        $this->assertSame('repeatInvoiceId', $params[1]->getName());
        $this->assertSame('regexMatch', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
        $this->assertFalse($params[2]->getDefaultValue());
    }

    /**
     * Tests that doDisable is a public static method.
     */
    public function testDoDisableIsPublicStatic(): void
    {
        $method = $this->reflector->getMethod('doDisable');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    /**
     * Tests that doDisable accepts three parameters, one optional.
     */
    public function testDoDisableParameterSignature(): void
    {
        $method = $this->reflector->getMethod('doDisable');
        $params = $method->getParameters();
        $this->assertCount(3, $params);
        $this->assertSame('serviceOrder', $params[0]->getName());
        $this->assertSame('repeatInvoiceId', $params[1]->getName());
        $this->assertSame('regexMatch', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
        $this->assertFalse($params[2]->getDefaultValue());
    }

    /**
     * Tests that doEnable requires exactly two parameters.
     */
    public function testDoEnableRequiresTwoParameters(): void
    {
        $method = $this->reflector->getMethod('doEnable');
        $this->assertSame(2, $method->getNumberOfRequiredParameters());
    }

    /**
     * Tests that doDisable requires exactly two parameters.
     */
    public function testDoDisableRequiresTwoParameters(): void
    {
        $method = $this->reflector->getMethod('doDisable');
        $this->assertSame(2, $method->getNumberOfRequiredParameters());
    }

    // ---------------------------------------------------------------
    // Method Existence and Count Tests
    // ---------------------------------------------------------------

    /**
     * Tests that the Plugin class declares the expected public methods.
     */
    public function testExpectedPublicMethodsExist(): void
    {
        $expectedMethods = [
            '__construct',
            'getHooks',
            'getAddon',
            'getSettings',
            'getIps',
            'doEnable',
            'doDisable',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $this->reflector->hasMethod($method),
                "Plugin should have method {$method}"
            );
        }
    }

    /**
     * Tests that all public methods declared by Plugin are accounted for.
     */
    public function testNoUnexpectedPublicMethods(): void
    {
        $expectedMethods = [
            '__construct',
            'getHooks',
            'getAddon',
            'getSettings',
            'getIps',
            'doEnable',
            'doDisable',
        ];
        $publicMethods = array_map(
            static fn(\ReflectionMethod $m) => $m->getName(),
            $this->reflector->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        // Filter to only methods declared by Plugin itself
        $ownMethods = array_filter(
            $publicMethods,
            fn(string $name) => $this->reflector->getMethod($name)->getDeclaringClass()->getName() === Plugin::class
        );
        sort($expectedMethods);
        $ownMethods = array_values($ownMethods);
        sort($ownMethods);
        $this->assertSame($expectedMethods, $ownMethods);
    }

    // ---------------------------------------------------------------
    // Hook Integration Tests
    // ---------------------------------------------------------------

    /**
     * Tests that hook callbacks reference methods that actually exist on Plugin.
     */
    public function testHookCallbackMethodsExist(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $hookName => $callback) {
            $this->assertTrue(
                method_exists($callback[0], $callback[1]),
                "Hook '{$hookName}' references non-existent method '{$callback[1]}'"
            );
        }
    }

    /**
     * Tests that hook callbacks reference static methods.
     */
    public function testHookCallbackMethodsAreStatic(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $hookName => $callback) {
            $method = $this->reflector->getMethod($callback[1]);
            $this->assertTrue(
                $method->isStatic(),
                "Hook '{$hookName}' callback '{$callback[1]}' should be static"
            );
        }
    }

    /**
     * Tests that hook callbacks are valid PHP callables.
     */
    public function testHookCallbacksAreCallable(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $callback) {
            $this->assertTrue(is_callable($callback));
        }
    }

    // ---------------------------------------------------------------
    // Static Analysis: doEnable / doDisable SQL Patterns
    // ---------------------------------------------------------------

    /**
     * Tests that doEnable method source contains expected SQL update pattern.
     */
    public function testDoEnableContainsSqlUpdatePattern(): void
    {
        $method = $this->reflector->getMethod('doEnable');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('update', $source);
        $this->assertStringContainsString("get_module_db", $source);
    }

    /**
     * Tests that doDisable method source contains expected SQL update pattern.
     */
    public function testDoDisableContainsSqlUpdatePattern(): void
    {
        $method = $this->reflector->getMethod('doDisable');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('update', $source);
        $this->assertStringContainsString("get_module_db", $source);
    }

    /**
     * Tests that doEnable references myadmin_log for audit trail.
     */
    public function testDoEnableHasLogging(): void
    {
        $method = $this->reflector->getMethod('doEnable');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('myadmin_log', $source);
    }

    /**
     * Tests that doDisable references myadmin_log for audit trail.
     */
    public function testDoDisableHasLogging(): void
    {
        $method = $this->reflector->getMethod('doDisable');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('myadmin_log', $source);
    }

    /**
     * Tests that doDisable sends admin email notification.
     */
    public function testDoDisableSendsAdminEmail(): void
    {
        $method = $this->reflector->getMethod('doDisable');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('adminMail', $source);
        $this->assertStringContainsString('website_ip_canceled.tpl', $source);
    }

    // ---------------------------------------------------------------
    // Static Analysis: getIps Return Structure
    // ---------------------------------------------------------------

    /**
     * Tests that getIps method source initializes the expected IP categories.
     */
    public function testGetIpsInitializesExpectedCategories(): void
    {
        $method = $this->reflector->getMethod('getIps');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString("'main'", $source);
        $this->assertStringContainsString("'used'", $source);
        $this->assertStringContainsString("'free'", $source);
        $this->assertStringContainsString("'shared'", $source);
    }

    /**
     * Tests that getIps method source calls listips on the WHM API.
     */
    public function testGetIpsCallsListips(): void
    {
        $method = $this->reflector->getMethod('getIps');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('listips()', $source);
    }

    // ---------------------------------------------------------------
    // Static Analysis: getAddon
    // ---------------------------------------------------------------

    /**
     * Tests that getAddon sets up the addon handler with expected properties.
     */
    public function testGetAddonSourceUsesAddonHandler(): void
    {
        $method = $this->reflector->getMethod('getAddon');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('AddonHandler', $source);
        $this->assertStringContainsString('setModule', $source);
        $this->assertStringContainsString('set_text', $source);
        $this->assertStringContainsString('set_cost', $source);
        $this->assertStringContainsString('setEnable', $source);
        $this->assertStringContainsString('setDisable', $source);
        $this->assertStringContainsString('register', $source);
        $this->assertStringContainsString('addAddon', $source);
    }

    /**
     * Tests that getAddon uses WEBSITE_IP_COST constant.
     */
    public function testGetAddonUsesWebsiteIpCostConstant(): void
    {
        $method = $this->reflector->getMethod('getAddon');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('WEBSITE_IP_COST', $source);
    }

    // ---------------------------------------------------------------
    // Static Analysis: getSettings
    // ---------------------------------------------------------------

    /**
     * Tests that getSettings configures module and global targets.
     */
    public function testGetSettingsSourceSetsTargets(): void
    {
        $method = $this->reflector->getMethod('getSettings');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString("setTarget('module')", $source);
        $this->assertStringContainsString("setTarget('global')", $source);
    }

    /**
     * Tests that getSettings adds the website_ip_cost text setting.
     */
    public function testGetSettingsAddsIpCostSetting(): void
    {
        $method = $this->reflector->getMethod('getSettings');
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $filename = $method->getFileName();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('add_text_setting', $source);
        $this->assertStringContainsString('website_ip_cost', $source);
    }
}
