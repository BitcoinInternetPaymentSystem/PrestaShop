<?php
	class BIPS extends PaymentModule
	{
		private $_html = '';
		private $_postErrors = array();

		function __construct()
		{
			$this->name = 'BIPS';
			$this->tab = 'payments_gateways';
			$this->version = '0.1';

			$this->currencies = true;
			$this->currencies_mode = 'checkbox';

			parent::__construct();

			$this->page = basename(__FILE__, '.php');
			$this->displayName = $this->l('BIPS');
			$this->description = $this->l('Accepts payments in bitcoin via BIPS.');
			$this->confirmUninstall = $this->l('Are you sure you want to delete your details?');


			if (Configuration::get('BIPS_APIKEY') == '')
			{
				$this->warning .= $this->l('API key is empty.');
			}

			if (Configuration::get('BIPS_SECRET') == '')
			{
				$this->warning .= $this->l('IPN secret is empty.');
			}
		}

		public function install()
		{
			if (!parent::install() || !$this->registerHook('invoice') || !$this->registerHook('payment') || !$this->registerHook('paymentReturn'))
			{
				return false;
			}

			$db = Db::getInstance();
			$query = "CREATE TABLE `"._DB_PREFIX_."order_BIPS` (
			`id_payment` int(11) NOT NULL AUTO_INCREMENT,
			`id_order` int(11) NOT NULL,
			`cart_id` int(11) NOT NULL,
			`invoice` int(11) NOT NULL,
			`txid` varchar(255) NOT NULL,
			`address` varchar(255) NOT NULL,
			PRIMARY KEY (`id_payment`),
			UNIQUE KEY `invoice` (`invoice`),
			UNIQUE KEY `txid` (`txid`)
			) ENGINE="._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';

			$db->Execute($query);

			Configuration::updateValue('BIPS_APIKEY', '');
			Configuration::updateValue('BIPS_SECRET', '');

			return true;
		}

		public function uninstall()
		{
			Configuration::deleteByName('BIPS_APIKEY');
			Configuration::deleteByName('BIPS_SECRET');
			
			return parent::uninstall();
		}

		public function getContent()
		{
			$this->_html .= '<h2>'.$this->l('BIPS').'</h2>';	
	
			$this->_postProcess();
			$this->_setBIPSSubscription();
			$this->_setConfigurationForm();
			
			return $this->_html;
		}

		function hookPayment($params)
		{
			global $smarty;

			$smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));

			return $this->display(__FILE__, 'BIPSpayment.tpl');
		}

		private function _setBIPSSubscription()
		{
			$this->_html .= '
			<div style="float: right; width: 440px; height: 150px; border: dashed 1px #666; padding: 8px; margin-left: 12px;">
				<h2>'.$this->l('Opening your BIPS account').'</h2>
				<div style="clear: both;"></div>
				<p>'.$this->l('When opening your BIPS account by clicking on the following image, you are helping us significantly to improve the BIPS Solution:').'</p>
				<p style="text-align: center;"><a href="https://bips.me/"><img src="../modules/BIPS/prestashop_bips.png" alt="PrestaShop & BIPS" style="margin-top: 12px;" /></a></p>
				<div style="clear: right;"></div>
			</div>
			<b>'.$this->l('This module allows you to accept payments in bitcoin via BIPS.').'</b><br /><br />
			'.$this->l('If the client chooses this payment mode, your BIPS account will be automatically credited.').'<br />
			'.$this->l('You need to configure your BIPS account before using this module.').'
			<div style="clear:both;">&nbsp;</div>';
		}

		private function _setConfigurationForm()
		{
			$this->_html .= '
			<form method="post" action="'.htmlentities($_SERVER['REQUEST_URI']).'">	
				<script type="text/javascript">
					var pos_select = '.(($tab = (int)Tools::getValue('tabs')) ? $tab : '0').';
				</script>
				<script type="text/javascript" src="'._PS_BASE_URL_._PS_JS_DIR_.'tabpane.js"></script>
				<link type="text/css" rel="stylesheet" href="'._PS_BASE_URL_._PS_CSS_DIR_.'tabpane.css" />
				<input type="hidden" name="tabs" id="tabs" value="0" />
				<div class="tab-pane" id="tab-pane-1" style="width:100%;">
					<div class="tab-page" id="step1">
						<h4 class="tab">'.$this->l('Settings').'</h2>
						'.$this->_getSettingsTabHtml().'
					</div>
				</div>
				<div class="clear"></div>
				<script type="text/javascript">
					function loadTab(id){}
					setupAllTabs();
				</script>
			</form>';
		}

		private function _getSettingsTabHtml()
		{
			$html = '
			<h2>'.$this->l('Settings').'</h2>
			<label>'.$this->l('API key').':</label>
			<div class="margin-form">
				<input type="password" name="apikey_bips" value="'.htmlentities(Tools::getValue('apikey_bips', Configuration::get('BIPS_APIKEY')), ENT_COMPAT, 'UTF-8').'" size="30" />
			</div>
			<label>'.$this->l('IPN secret').':</label>
			<div class="margin-form">
				<input type="password" name="secret_bips" value="'.htmlentities(Tools::getValue('secret_bips', Configuration::get('BIPS_SECRET')), ENT_COMPAT, 'UTF-8').'" size="40" />
			</div>
			<br /><br />
			<h3>' . $this->l('Please copy this to Merchant Callback URL in BIPS account.') . '</h3>
			' . (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/'.$this->name.'/ipn.php' . '

			<p class="center"><input class="button" type="submit" name="submitBIPS" value="'.$this->l('Save settings').'" /></p>';
			return $html;
		}

		private function _postProcess()
		{
			global $currentIndex, $cookie;

			if (Tools::isSubmit('submitBIPS'))
			{
				$template_available = array('A', 'B', 'C');

				$this->_errors = array();

				if (Tools::getValue('apikey_bips') == NULL)
				{
					$this->_errors[] = $this->l('Missing BIPS API key');
				}

				if (Tools::getValue('secret_bips') == NULL)
				{
					$this->_errors[] = $this->l('Missing BIPS IPN secret');
				}

				if (count($this->_errors) > 0)
				{
					$error_msg = '';
					foreach ($this->_errors AS $error)
						$error_msg .= $error.'<br />';
					$this->_html = $this->displayError($error_msg);
				}
				else
				{
					Configuration::updateValue('BIPS_APIKEY', trim(Tools::getValue('apikey_bips')));
					Configuration::updateValue('BIPS_SECRET', trim(Tools::getValue('secret_bips')));

					$this->_html = $this->displayConfirmation($this->l('Settings updated'));
				}
			}
		}

		public function execPayment($cart)
		{
			global $cookie, $smarty;

			if (!$this->active)
				return ;

			$currency = new Currency((int)($cart->id_currency));
			$currency = $currency->iso_code;


			$method = _PS_OS_PREPARATION_;

			if ($cart->isVirtualCart())
			{
				$method = _PS_OS_PAYMENT_;
			}

			$this->validateOrder($cart->id, $method, $cart->getOrderTotal(true), $this->displayName, NULL, NULL, $currency->id);
			$order = new Order($this->currentOrder);

			$ch = curl_init();
			curl_setopt_array($ch, array(
			CURLOPT_URL => 'https://bips.me/api/v1/invoice',
			CURLOPT_USERPWD => Configuration::get('BIPS_APIKEY'),
			CURLOPT_POSTFIELDS => 'price=' . number_format($cart->getOrderTotal(true), 2, '.', '') . '&currency=' . $currency . '&item=Cart&custom=' . json_encode(array('order_id' => $order->id, 'cart_id' => $cart->id, 'returnurl' => rawurlencode((Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'order-confirmation.php?id_cart='.$cart->id), 'cancelurl' => rawurlencode((Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'order.php'))),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC));
			$redirect = curl_exec($ch);
			curl_close($ch);

			header('Location: ' . $redirect);
		}

		function writeDetails($id_order, $cart_id, $invoice, $hash, $address)
		{
			$db = Db::getInstance();
			$result = $db->Execute('INSERT INTO `' . _DB_PREFIX_ . 'order_BIPS` (`id_order`, `cart_id`, `invoice`, `txid`, `address`) VALUES(' . intval($id_order)  .', ' . intval($cart_id) . ', ' . intval($invoice) . ', "' . $hash . '", "' . $address . '")');

			$result = $db->Execute('UPDATE `' . _DB_PREFIX_ . 'order_history` SET id_order_state = ' . _PS_OS_PAYMENT_ . ' WHERE id_order = ' . intval($id_order));
		}

		function readBitcoinpaymentdetails($id_order)
		{
			$db = Db::getInstance();
			$result = $db->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'order_BIPS` WHERE `id_order` = ' . intval($id_order) . ';');
			return $result[0];
		}

		function hookInvoice($params)
		{
			global $smarty;

			$id_order = $params['id_order'];

			$bitcoinpaymentdetails = $this->readBitcoinpaymentdetails($id_order);

			$smarty->assign(array(
				'invoice' => $bitcoinpaymentdetails['invoice'],
				'txid' => $bitcoinpaymentdetails['txid'],
				'address' => $bitcoinpaymentdetails['address'],
				'id_order' => $id_order,
				'this_page' => $_SERVER['REQUEST_URI'],
				'this_path' => $this->_path,
				'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"
			));
		
			return $this->display(__FILE__, 'invoice_block.tpl');
		}

		function hookpaymentReturn($params)
		{
			global $smarty;

			$smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));

			return $this->display(__FILE__, 'complete.tpl');
		}
	}
?>