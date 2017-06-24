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
		$service->addAddon($addon);
	}

	public static function doEnable(\Service_Order $serviceOrder, $repeatInvoiceId, $regexMatch = false) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings(self::$module);
		if ($regexMatch === false) {
			$db = get_module_db(self::$module);
			$id = $serviceInfo[$settings['PREFIX'].'_id'];
			$ip = $serviceInfo[$settings['PREFIX'].'_ip'];
			$db->query("select * from website_masters where website_id='{$serviceInfo[$settings['PREFIX'].'_server']}'", __LINE__, __FILE__);
			$db->next_record(MYSQL_ASSOC);
			$serverdata = $db->Record;
			$hash = $serverdata[$settings['PREFIX'].'_key'];
			$user = 'root';
			function_requirements('whm_api');
			$whm = new \xmlapi($serverdata[$settings['PREFIX'].'_ip']);
			//$whm->set_debug('true');
			$whm->set_port('2087');
			$whm->set_protocol('https');
			$whm->set_output('json');
			$whm->set_auth_type('hash');
			$whm->set_user($user);
			$whm->set_hash($hash);
			$accts = json_decode($whm->listips(), true);
			$freeips = [];
			$shared_ips = [];
			foreach ($accts['result'] as $idx => $ipdata) {
				if ($ipdata['mainaddr'] == '1')
					$main_ip = $ipdata['ip'];
				if ($ipdata['used'] == 0 && $ipdata['active'] == 1)
					$freeips[] = $ipdata['ip'];
				if ($ipdata['dedicated'] == 0)
					$shared_ips[] = $ipdata['ip'];
			}
			// check if ip is main or additional/dedicated.  if ip is main, get a new one
			if (in_array($ip, $shared_ips)) {
				myadmin_log(self::$module, 'info', "IP {$ip} (Shared) Main IP {$main_ip}", __LINE__, __FILE__);
				if (sizeof($freeips) > 0) {
					// assign new ip
					$ip = $freeips[0];
					$response = $whm->setsiteip($ip, $serviceInfo[$settings['PREFIX'].'_username']);
					myadmin_log(self::$module, 'info', "WHM setsiteip({$ip}, {$serviceInfo[$settings['PREFIX'].'_username']}) Response: {$response}", __LINE__, __FILE__);
					$response = json_decode($response);
					if ($response->result[0]->status == 1) {
						// update db w/ new ip
						$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='$ip' where {$settings['PREFIX']}_id=$id", __LINE__, __FILE__);
						myadmin_log(self::$module, 'info', "Gave Website {$id} IP {$ip}", __LINE__, __FILE__);
					} else {
						myadmin_log(self::$module, 'info', "Error Giving Website {$id} IP {$ip}", __LINE__, __FILE__);
						$headers = '';
						$headers .= 'MIME-Version: 1.0' . EMAIL_NEWLINE;
						$headers .= 'Content-type: text/html; charset=UTF-8' . EMAIL_NEWLINE;
						$headers .= 'From: ' . TITLE . ' <' . EMAIL_FROM . '>' . EMAIL_NEWLINE;
						$subject = 'Error Setting IP ' . $ip . ' on ' . $settings['TBLNAME'] . ' ' . $serviceInfo[$settings['TITLE_FIELD']];
						admin_mail($subject, $subject, $headers, false, 'admin_email_website_no_ips.tpl');
					}
				} else {
					$subject = "0 Free IPs On {$settings['TBLNAME']} Server {$serverdata[$settings['PREFIX'].'_name']}";
					admin_mail($subject, "Webserver {$id} Has Pending IPS<br>\n" . $subject, false, false, 'admin_email_website_no_ips.tpl');
					myadmin_log(self::$module, 'info', $subject, __LINE__, __FILE__);
				}
			} else {
				myadmin_log(self::$module, 'info', "IP {$ip} (Already Dedicated) Main IP {$main_ip}", __LINE__, __FILE__);
			}
		} else {
			$ip = $regexMatch;
			myadmin_log(self::$module, 'info', "IP {$ip} (Already Dedicated)", __LINE__, __FILE__);
		}
	}

	public static function doDisable(\Service_Order $serviceOrder) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings(self::$module);
	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Addon Costs', 'website_ip_cost', 'Webhosting Dedicated IP Cost:', 'This is the cost for purchasing an Dedicated IP on top of a Webhosting.', $settings->get_setting('WEBSITE_IP_COST'));
	}
}
