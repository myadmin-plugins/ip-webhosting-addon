<?php

namespace Detain\MyAdminWebhostingIp;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminWebhostingIp
 */
class Plugin {

	public static $name = 'Dedicated IP Webhosting Addon';
	public static $description = 'Allows selling of Dedicated IP Addon for Webhosting.';
	public static $help = '';
	public static $module = 'webhosting';
	public static $type = 'addon';

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array<string,string[]>
	 */
	public static function getHooks() {
		return [
			self::$module.'.load_addons' => [__CLASS__, 'getAddon'],
			self::$module.'.settings' => [__CLASS__, 'getSettings']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getAddon(GenericEvent $event) {
		$service = $event->getSubject();
		function_requirements('class.AddonHandler');
		$addon = new \AddonHandler();
		$addon->setModule(self::$module)
			->set_text('Dedicated IP')
			->set_text_match('Dedicated IP (.*)')
			->set_cost(WEBSITE_IP_COST)
			->setEnable([__CLASS__, 'doEnable'])
			->setDisable([__CLASS__, 'doDisable'])
			->register();
		$service->addAddon($addon);
	}

	/**
	 * @param \ServiceHandler $serviceOrder
	 * @param                $repeatInvoiceId
	 * @param bool           $regexMatch
	 * @throws \Exception
	 */
	public static function doEnable(\ServiceHandler $serviceOrder, $repeatInvoiceId, $regexMatch = FALSE) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings(self::$module);
		if ($regexMatch === FALSE) {
			function_requirements('get_service_master');
			$serverdata = get_service_master($serviceInfo[$settings['PREFIX'].'_server'], self::$module);
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
				if ($ipData['used'] == 0 && $ipData['active'] == 1 && $ipData['dedicated'] == 1)
					$freeips[] = $ipData['ip'];
				if ($ipData['dedicated'] == 0)
					$sharedIps[] = $ipData['ip'];
			}
			// check if ip is main or additional/dedicated.  if ip is main, get a new one
			if (in_array($serviceInfo[$settings['PREFIX'].'_ip'], $sharedIps)) {
				myadmin_log(self::$module, 'info', "ip {$serviceInfo[$settings['PREFIX'].'_ip']} (Shared) Main IP {$mainIp}", __LINE__, __FILE__);
				if (count($freeips) > 0) {
					// assign new ip
					$serviceInfo[$settings['PREFIX'].'_ip'] = $freeips[0];
					$response = $whm->setsiteip($serviceInfo[$settings['PREFIX'].'_ip'], $serviceInfo[$settings['PREFIX'].'_username']);
					myadmin_log(self::$module, 'info', "WHM setsiteip({$serviceInfo[$settings['PREFIX'].'_ip']}, {$serviceInfo[$settings['PREFIX'].'_username']}) Response: {$response}", __LINE__, __FILE__);
					$response = json_decode($response);
					if ($response->result[0]->status == 1) {
						// update db w/ new ip
						$db = get_module_db(self::$module);
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

	/**
	 * @param \ServiceHandler $serviceOrder
	 * @param                $repeatInvoiceId
	 * @param bool           $regexMatch
	 * @throws \Exception
	 */
	public static function doDisable(\ServiceHandler $serviceOrder, $repeatInvoiceId, $regexMatch = FALSE) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings(self::$module);
		$db = get_module_db(self::$module);
		function_requirements('whm_api');
		$serverdata = get_service_master($serviceInfo[$settings['PREFIX'].'_server'], self::$module);
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
				$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_ip='{$mainIp}' where {$settings['PREFIX']}_id={$serviceInfo[$settings['PREFIX'].'_id']}", __LINE__, __FILE__);
				myadmin_log(self::$module, 'info', "Gave Website {$serviceInfo[$settings['PREFIX'].'_id']} Main IP {$serviceInfo[$settings['PREFIX'].'_ip']}", __LINE__, __FILE__);
			} else {
				myadmin_log(self::$module, 'info', "Error Giving Website {$serviceInfo[$settings['PREFIX'].'_id']} Main IP {$serviceInfo[$settings['PREFIX'].'_ip']}", __LINE__, __FILE__);
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
		$email = $settings['TBLNAME'].' ID: '.$serviceInfo[$settings['PREFIX'].'_id'].'<br>'.$settings['TBLNAME'].' Hostname: '.$serviceInfo[$settings['PREFIX'].'_hostname'].'<br>Description: '.self::$name.'<br>';
		$subject = $settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Canceled Dedicated IP';
		$headers = '';
		$headers .= 'MIME-Version: 1.0'.EMAIL_NEWLINE;
		$headers .= 'Content-type: text/html; charset=UTF-8'.EMAIL_NEWLINE;
		$headers .= 'From: '.$settings['TITLE'].' <'.$settings['EMAIL_FROM'].'>'.EMAIL_NEWLINE;
		admin_mail($subject, $email, $headers, FALSE, 'admin_email_website_ip_canceled.tpl');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Costs & Limits', 'website_ip_cost', 'Dedicated IP Cost:', 'This is the cost for purchasing an additional IP on top of a Website.', (defined(WEBSITE_IP_COST) ? WEBSITE_IP_COST : 3));
	}
}
