<?php

namespace Detain\MyAdminWebhostingIp;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Dedicated IP Licensing Webhosting Addon';
	public static $description = 'Allows selling of Dedicated IP Server and Webhosting License Types.  More info at https://www.netenberg.com/ips.php';
	public static $help = 'It provides more than one million end users the ability to quickly install dozens of the leading open source content management systems into their web space.  	Must have a pre-existing cPanel license with cPanelDirect to purchase a ips license. Allow 10 minutes for activation.';
	public static $module = 'webhosting';
	public static $type = 'addon';


	public function __construct() {
	}

	public static function getHooks() {
		return [
			self::$module.'.load_addons' => [__CLASS__, 'getAddon'],
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
		];
	}

	public static function getAddon(GenericEvent $event) {
		$service = $event->getSubject();
		function_requirements('class.Addon');
		$addon = new \Addon();
		$addon->setModule(self::$module)
			->set_text('Dedicated IP')
			->set_text_match('Dedicated IP (.*)')
			->set_cost(WEBSITE_IP_COST)
			->set_enable([__CLASS__, 'doEnable'])
			->set_disable([__CLASS__, 'doDisable'])
			->register();
		$service->add_addon($addon);
	}

	public static function doEnable(\Service_Order $serviceOrder) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings($serviceOrder->getModule());
	}

	public static function doDisable(\Service_Order $serviceOrder) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings($serviceOrder->getModule());
	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Addon Costs', 'website_ip_cost', 'Webhosting Dedicated IP Cost:', 'This is the cost for purchasing an Dedicated IP on top of a Webhosting.', $settings->get_setting('WEBSITE_IP_COST'));
	}
}
