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

	public static function doEnable(\Service_Order $serviceOrder, $repeatInvoiceId, $regexMatch = FALSE) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings(self::$module);
		if ($regexMatch === FALSE) {
			$db = get_module_db(self::$module);
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
			$accts = json_decode($whm->listips(), TRUE);
			$freeips = [];
			$sharedIps = [];
			foreach (array_values($accts['result']) as $ipData) {
				if ($ipData['mainaddr'] == '1')
					$mainIp = $ipData['ip'];
				if ($ipData['used'] == 0 && $ipData['active'] == 1)
					$freeips[] = $ipData['ip'];
				if ($ipData['dedicated'] == 0)
					$sharedIps[] = $ipData['ip'];
			}
			// check if ip is main or additional/dedicated.  if ip is main, get a new one
			if (in_array($serviceInfo[$settings['PREFIX'].'_ip'], $sharedIps)) {
				myadmin_log(self::$module, 'info', "ip {$serviceInfo[$settings['PREFIX'].'_ip']} (Shared) Main IP {$mainIp}", __LINE__, __FILE__);
				if (sizeof($freeips) > 0) {
					// assign new ip
					$serviceInfo[$settings['PREFIX'].'_ip'] = $freeips[0];
					$response = $whm->setsiteip($serviceInfo[$settings['PREFIX'].'_ip'], $serviceInfo[$settings['PREFIX'].'_username']);
					myadmin_log(self::$module, 'info', "WHM setsiteip({$serviceInfo[$settings['PREFIX'].'_ip']}, {$serviceInfo[$settings['PREFIX'].'_username']}) Response: {$response}", __LINE__, __FILE__);
					$response = json_decode($response);
					if ($response->result[0]->status == 1) {
						// update db w/ new ip
						$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='{$serviceInfo[$settings['PREFIX'].'_ip']}' where {$settings['PREFIX']}_id={$serviceInfo[$settings['PREFIX'].'_id']}", __LINE__, __FILE__);
						myadmin_log(self::$module, 'info', "Gave Website {$serviceInfo[$settings['PREFIX'].'_id']} IP {$serviceInfo[$settings['PREFIX'].'_ip']}", __LINE__, __FILE__);
					} else {
						myadmin_log(self::$module, 'info', "Error Giving Website {$serviceInfo[$settings['PREFIX'].'_id']} IP {$serviceInfo[$settings['PREFIX'].'_ip']}", __LINE__, __FILE__);
						$headers = '';
						$headers .= 'MIME-Version: 1.0'.EMAIL_NEWLINE;
						$headers .= 'Content-type: text/html; charset=UTF-8'.EMAIL_NEWLINE;
						$headers .= 'From: '.TITLE.' <'.EMAIL_FROM.'>'.EMAIL_NEWLINE;
						$subject = 'Error Setting IP '.$serviceInfo[$settings['PREFIX'].'_ip'].' on '.$settings['TBLNAME'].' '.$serviceInfo[$settings['TITLE_FIELD']];
						admin_mail($subject, $subject, $headers, FALSE, 'admin_email_website_no_ips.tpl');
					}
				} else {
					$subject = "0 Free IPs On {$settings['TBLNAME']} Server {$serverdata[$settings['PREFIX'].'_name']}";
					admin_mail($subject, "webserver {$serviceInfo[$settings['PREFIX'].'_id']} Has Pending IPS<br>\n".$subject, FALSE, FALSE, 'admin_email_website_no_ips.tpl');
					myadmin_log(self::$module, 'info', $subject, __LINE__, __FILE__);
				}
			} else {
				myadmin_log(self::$module, 'info', "ip {$serviceInfo[$settings['PREFIX'].'_ip']} (Already Dedicated) Main IP {$mainIp}", __LINE__, __FILE__);
			}
		} else {
			$serviceInfo[$settings['PREFIX'].'_ip'] = $regexMatch;
			myadmin_log(self::$module, 'info', "ip {$serviceInfo[$settings['PREFIX'].'_ip']} (Already Dedicated)", __LINE__, __FILE__);
		}
	}

	public static function doDisable(\Service_Order $serviceOrder) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings(self::$module);
		$db = get_module_db(self::$module);
		function_requirements('whm_api');
		$serverdata = get_service_master($serviceInfo['website_server'], self::$module);
		$hash = $serverdata['website_key'];
		$user = 'root';
		$whm = new \xmlapi($serverdata['website_ip']);
		//$whm->set_debug('true');
		$whm->set_port('2087');
		$whm->set_protocol('https');
		$whm->set_output('json');
		$whm->set_auth_type('hash');
		$whm->set_user($user);
		$whm->set_hash($hash);
		$accts = obj2array(json_decode($whm->listips()));
		$freeips = [];
		$sharedIps = [];
		$values = array_values($accts['result']);
		foreach ($values as $ipData) {
			if ($ipData['mainaddr'] == 1)
				$mainIp = $ipData['ip'];
			if ($ipData['used'] == 0 && $ipData['active'] == 1)
				$freeips[] = $ipData['ip'];
			if ($ipData['dedicated'] == 0)
				$sharedIps[] = $ipData['ip'];
		}
		// check if ip is main or additional/dedicated. if ip is main, get a new one
		if (!in_array($serviceInfo[$settings['PREFIX'].'_ip'], $sharedIps)) {
			myadmin_log(self::$module, 'info', "ip {$serviceInfo[$settings['PREFIX'].'_ip']} (Dedicated IP) Main IP {$mainIp}", __LINE__, __FILE__);
			$newIp = $sharedIps[0];
			$response = $whm->setsiteip($newIp, $serviceInfo[$settings['PREFIX'].'_username']);
			myadmin_log(self::$module, 'info', "WHM setsiteip({$newIp}, {$serviceInfo[$settings['PREFIX'].'_username']}) Response: {$response}", __LINE__, __FILE__);
			$response = json_decode($response);
			if ($response->result[0]->status == 1) {
				// update db w/ new ip
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='{$mainIp}' where {$settings['PREFIX']}_id={$db->Record['repeat_invoices_service']}", __LINE__, __FILE__);
				myadmin_log(self::$module, 'info', "Gave Website {$db->Record['repeat_invoices_service']} Main IP {$serviceInfo[$settings['PREFIX'].'_ip']}", __LINE__, __FILE__);
			} else {
				myadmin_log(self::$module, 'info', "Error Giving Website {$db->Record['repeat_invoices_service']} Main IP {$serviceInfo[$settings['PREFIX'].'_ip']}", __LINE__, __FILE__);
				$headers = '';
				$headers .= 'MIME-Version: 1.0'.EMAIL_NEWLINE;
				$headers .= 'Content-type: text/html; charset=UTF-8'.EMAIL_NEWLINE;
				$headers .= 'From: '.TITLE.' <'.EMAIL_FROM.'>'.EMAIL_NEWLINE;
				$subject = 'Error Reverting To Main IP '.$serviceInfo[$settings['PREFIX'].'_ip'].' on '.$settings['TBLNAME'].' '.$serviceInfo[$settings['TITLE_FIELD']];
				admin_mail($subject, $subject, $headers, FALSE, 'admin_email_website_no_ips.tpl');
			}
		} else {
			myadmin_log(self::$module, 'info', "ip {$serviceInfo[$settings['PREFIX'].'_ip']} (Shared IP) Main IP {$mainIp}, no Change Needed", __LINE__, __FILE__);
		}
		add_output('Dedicated IP Order Canceled');
		$email = $settings['TBLNAME'].' ID: '.$serviceInfo[$settings['PREFIX'].'_id'].'<br>'.$settings['TBLNAME'].' Hostname: '.$serviceInfo[$settings['PREFIX'].'_hostname'].'<br>'."Invoice: $r<br>"."Description: {$db->Record['repeat_invoices_description']}<br>";
		$subject = $settings['TBLNAME'].' '.$db->Record['repeat_invoices_service'].' Canceled Dedicated IP';
		$headers = '';
		$headers .= 'MIME-Version: 1.0'.EMAIL_NEWLINE;
		$headers .= 'Content-type: text/html; charset=UTF-8'.EMAIL_NEWLINE;
		$headers .= 'From: '.$settings['TITLE'].' <'.$settings['EMAIL_FROM'].'>'.EMAIL_NEWLINE;
		admin_mail($subject, $email, $headers, FALSE, 'admin_email_website_ip_canceled.tpl');
	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Costs & Limits', 'website_ip_cost', 'Dedicated IP Cost:', 'This is the cost for purchasing an additional IP on top of a Website.', (defined(WEBSITE_IP_COST) ? WEBSITE_IP_COST : 3));
	}
}
