<?php
/*
  Plugin Name: «Экспресс Платежи: Банковские карты» для WooCommerce
  Plugin URI: https://express-pay.by/cms-extensions/wordpress
  Description: «Экспресс Платежи: Банковские карты» - плагин для интеграции с сервисом «Экспресс Платежи» (express-pay.by) через API. Плагин позволяет выставлять счета для оплаты банковскими картами, получать и обрабатывать уведомления о платеже по банковской карте. Описание плагина доступно по адресу: <a target="blank" href="https://express-pay.by/cms-extensions/wordpress">https://express-pay.by/cms-extensions/wordpress</a>
  Version: 3.1
  Author: ООО «ТриИнком»
  Author URI: https://express-pay.by/
  WC requires at least: 4.0
  WC tested up to: 4.7
 */

if(!defined('ABSPATH')) exit;

define("CARD_EXPRESSPAY_VERSION", "3.0.2");

add_action('plugins_loaded', 'init_card_gateway', 0);

function add_wordpress_card_expresspay($methods) {
	$methods[] = 'wordpress_card_expresspay';

	return $methods;
}

function init_card_gateway() {
	if(!class_exists('WC_Payment_Gateway'))
		return;

	add_filter('woocommerce_payment_gateways', 'add_wordpress_card_expresspay');

	load_plugin_textdomain("wordpress_card_expresspay", false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');

	class Wordpress_Card_Expresspay extends WC_Payment_Gateway {
		private $plugin_dir;

		public function __construct() {
			$this->id = "expresspay_card";
            //$this->method_title = __('Экспресс Платежи: Банковские карты');
			//$this->method_description = __('Оплата по карте сервис «Экспресс Платежи»');
			$this->method_title = $this->get_option('payment_methid_title');
            $this->method_description = $this->get_option('payment_methid_description');
			$this->plugin_dir = plugin_dir_url(__FILE__);

			$this->init_form_fields();
			$this->init_settings();

			//$this->title = __("Банковская карта", 'wordpress_card_expresspay');
			
			$this->title = $this->get_option('payment_methid_title');
			
            $this->token = $this->get_option('token');
            $this->service_id = $this->get_option('service_id');
			$this->secret_word = $this->get_option('secret_key');
			$this->is_use_signature_notify = ( $this->get_option('is_use_signature_notify') == 'yes' ) ? 1 : 0;
			$this->secret_key_notify = $this->get_option('secret_key_notify');
			$this->session_timeout_secs = $this->get_option('session_timeout_secs');
			$this->message_success = $this->get_option('message_success');
			$this->message_fail = $this->get_option('message_fail');
			
			$this->url = ( $this->get_option('test_mode') != 'yes' ) ? $this->get_option('url_api') : $this->get_option('url_sandbox_api');
			$this->url .= "/v1/web_cardinvoices";
			$this->test_mode = ( $this->get_option('test_mode') == 'yes' ) ? 1 : 0;

			$this->status_after_payment = $this->get_option('status_after_payment');
			$this->status_after_cancellation = $this->get_option('status_after_cancellation');

			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_api_wordpress_card_expresspay', array($this, 'check_ipn_response'));
		}

		public function admin_options() {
			?>
			<h3><?php _e('«Экспресс Платежи: Оплата по карте', 'wordpress_card_expresspay'); ?></h3>
            <div style="float: left; display: inline-block;">
                 <a target="_blank" href="https://express-pay.by"><img src="<?php echo $this->plugin_dir; ?>assets/images/erip_expresspay_big.png" width="270" height="91" alt="exspress-pay.by" title="express-pay.by"></a>
            </div>
            <div style="margin-left: 6px; margin-top: 15px; display: inline-block;">
				<?php _e('«Экспресс Платежи: Оплата по карте» - плагин для интеграции с сервисом «Экспресс Платежи» (express-pay.by) через API. 
				<br/>Плагин позволяет выставить счет для оплаты по карте, получить и обработать уведомление о платеже.
				<br/>Описание плагина доступно по адресу: ', 'wordpress_card_expresspay'); ?><a target="blank" href="https://express-pay.by/cms-extensions/wordpress#woocommerce_2_x">https://express-pay.by/cms-extensions/wordpress#woocommerce_2_x</a>
            </div>

			<table class="form-table">
				<?php		
					$this->generate_settings_html();
				?>
			</table>

			<div class="copyright" style="text-align: center;">
				<?php _e("© Все права защищены | ООО «ТриИнком»,", 'wordpress_card_expresspay'); ?> 2013-<?php echo date("Y"); ?><br/>
				<?php echo __('Версия', 'wordpress_card_expresspay') . " " . CARD_EXPRESSPAY_VERSION ?>			
			</div>
			<?php
		}

		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __('Включить/Выключить', 'wordpress_card_expresspay'),
					'type'    => 'checkbox',
					'default' => 'no'
				),
				'token' => array(
					'title'   => __('Токен', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Генерирутся в панели express-pay.by', 'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'service_id' => array(
					'title'   => __('Номер услуги', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Номер услуги в системе express-pay.by', 'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'handler_url' => array(
					'title'   => __('Адрес для уведомлений', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'css' => 'display: none;',
					'description' => get_site_url() . '/?wc-api=wc_card_expresspay&action=notify'
				),
				'secret_key' => array(
					'title'   => __('Секретное слово для подписи счетов', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Секретного слово, которое известно только серверу и клиенту. Используется для формирования цифровой подписи. Задается в панели express-pay.by', 'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'is_use_signature_notify' => array(
					'title'   => __('Использовать цифровую подпись для уведомлений', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('Использовать цифровую подпись для уведомлений', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'secret_key_norify' => array(
					'title'   => __('Секретное слово для подписи уведомлений', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Секретного слово, которое известно только серверу и клиенту. Используется для формирования цифровой подписи. Задается в панели express-pay.by', 'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'session_timeout_secs' => array(
					'title'   => __('Продолжительность сессии', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Временной промежуток указанный в секундах, за время которого клиент может совершить платеж (находится в промежутке от 600 секунд (10 минут) до 86400 секунд (1 сутки) ). По-умолчанию равен 1200 секунд (20 минут)', 'wordpress_card_expresspay'),
					'default' => '1200',
                    'desc_tip'    => true
				),
				'test_mode' => array(
					'title'   => __('Использовать тестовый режим', 'wordpress_card_expresspay'),
					'type'    => 'checkbox'
				),
				'url_api' => array(
					'title'   => __('Адрес API', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'default' => 'https://api.express-pay.by'
				),
				'url_sandbox_api' => array(
					'title'   => __('Адрес тестового API', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'default' => 'https://sandbox-api.express-pay.by'
				),
				'message_success' => array(
					'title'   => __('Сообщение при успешном заказе', 'wordpress_card_expresspay'),
					'type'    => 'textarea',
					'default' => __('Заказ номер "##order_id##" успешно оплачен. Нажмите "продолжить".', 'wordpress_card_expresspay'),
					'css'	  => 'min-height: 160px;'
				),
                'message_fail' => array(
					'title'   => __('Сообщение при ошибке заказа', 'wordpress_card_expresspay'),
					'type'    => 'textarea',
					'default' => __('При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина', 'wordpress_card_expresspay'),
					'css'	  => 'min-height: 160px;'
				),
				'status_after_payment'         => array(
					'title'       => __( 'Статус после оплаты', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Статус, который будет иметь заказ после оплаты', 'woocommerce' ),
					'options'     => wc_get_order_statuses(),
					'desc_tip'    => true,
				),
				'status_after_cancellation'    => array(
					'title'       => __( 'Статус после отмены', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Статус, который будет иметь заказ после отмены', 'woocommerce' ),
					'options'     => wc_get_order_statuses(),
					'desc_tip'    => true,
				),
				'payment_methid_title' => array(
					'title'   => __('Название метода оплаты', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Название, которое будет отображаться в корзине, при выборе метода оплаты', 'wordpress_card_expresspay'),
					'default' 	=> __("Экспресс Платежи: Банковские карты",'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'payment_methid_description' => array(
					'title'   => __('Описание метода оплаты', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Описание, которое будет отображаться в настройках методах оплаты', 'wordpress_card_expresspay'),
					'default' 	=> __("Оплата по карте сервис «Экспресс Платежи»",'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
			);
		}

		function process_payment($order_id) {
            global $woocommerce;
        
            $this->log_info('process_payment', 'Initialization request for add invoice');
			$order = new WC_Order($order_id);	

			return array(
				'result' => 'success',
				'redirect'	=> add_query_arg('order-pay', $order->get_order_number( ), add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id('pay'))))
			);
		}

		function receipt_page( $order ){

			if(isset($_REQUEST['status']))
			{
				$this->log_info('process_payment', 'STATUS - ' . $_REQUEST['status']);

				switch($_REQUEST['status'])
				{
					case 'fail':
						$this->log_info('process_payment', 'Order number ' . $order.' Fail');
						echo $this->fail($order);
						break;
					case 'success':
						$this->log_info('process_payment', 'Order number ' . $order.' Success');
						echo $this->success($order);
						break;
					default:
						echo  $this->generate_expresspay_form($order);
						break;
				}
			}
			else
			{
				echo  $this->generate_expresspay_form($order);
			}

		}

		function generate_expresspay_form($order_id)
		{
			global $woocommerce;

			$this->log_info('generate_expresspay_form', 'Initialization request for add invoice');
			$order = new WC_Order($order_id);

			$price = preg_replace('#[^\d.]#', '', $order->get_total());
			$price = str_replace('.', ',', $price);
			
			$currency = (date('y') > 16 || (date('y') >= 16 && date('n') >= 7)) ? '933' : '974';

			$request_params = array(
				"ServiceId" 			=>  $this->service_id,
				"AccountNo" 			=> 	$order_id,
				"Amount" 				=> 	$price,
				"Currency" 				=> 	$currency,
				"Info" 					=> 	"Покупка в магазине ",
				"Language"				=> "ru",
				"SessionTimeoutSecs"	=> 1200,
				'ReturnType'			=> 	'redirect',				
				"ReturnUrl" 			=> 	get_site_url() . add_query_arg('status', 'success', add_query_arg('order-pay', $order->get_order_number(), add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id($order->get_checkout_payment_url()))))),
				"FailUrl" 				=> 	get_site_url() . add_query_arg('status', 'fail', add_query_arg('order-pay', $order->get_order_number(), add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id($order->get_checkout_payment_url()))))),
				"Signature" 			=> 	''
			);

			$signature = $this->compute_signature_add_invoice($request_params, $this->secret_word);

			$request_params['Signature'] = $signature;
			
			$this->log_info('generate_expresspay_form', 'Send POST request; ORDER ID - ' . $order_id . '; URL - ' . $this->url . '; TOKEN - ' . $this->token . '; REQUEST - ' . json_encode($request_params));
			
			$html = '<form action="' . $this->url . '" method="post" name="expresspay_form" >';
			foreach ($request_params as $name => $value) {
				$html.= '<input type="hidden" id="'.$name.'" name="' . $name . '" value="' . htmlspecialchars($value) . '" /><br/>';
			}
			$html.= '</form>';
			$html.= ' <script type="text/javascript">';
			$html.= ' document.expresspay_form.submit();';
			$html.= ' </script>';

			$this->log_info('generate_expresspay_form', 'Send POST request; ORDER ID - ' . $order_id . '; HTML - ' . $html);

			return $html;
		}

		private function success($order_id) {
			global $woocommerce;

			$order = new WC_Order($order_id);	

			$this->log_info('receipt_page', 'End request for add invoice');
			$this->log_info('success', 'Initialization render success page; ORDER ID - ' . $order->get_order_number());

			$woocommerce->cart->empty_cart();

			//$order->update_status('completed', __('Счет успешно оплачен', 'wordpress_card_expresspay'));
			
			wc_get_template(
				'order/order-details.php',
				array(
					'order_id' => $order_id,
				)
			);

			$order->update_status($this->status_after_payment, __('Счет успешно успешно и ожидает оплаты', 'wordpress_card_expresspay'));

			$html = '';

			$html .= '<h2>' . __('Счет успешно оплачен', 'wordpress_card_expresspay') . '</h2>';
			$html .= str_replace("##order_id##", $order->get_order_number(), nl2br($this->message_success, true));

			$html .= '<br/><br/><p class="return-to-shop"><a class="button wc-backward" href="' . get_permalink( wc_get_page_id( "shop" ) ) . '">' . __('Продолжить', 'wordpress_card_expresspay') . '</a></p>';

			$this->log_info('success', 'End render success page; ORDER ID - ' . $order->get_order_number());

			return $html;
		}

		private function fail($order_id) {
			global $woocommerce;

			$order = new WC_Order($order_id);	

			$this->log_info('receipt_page', 'End request for add invoice');
			$this->log_info('fail', 'Initialization render fail page; ORDER ID - ' . $order->get_order_number());

			$order->update_status('failed', __('Платеж не оплачен', 'wordpress_card_expresspay'));

			$this->log_info('fail', 'End render fail page; ORDER ID - ' . $order->get_order_number());

			$html = '<h2>' . __('Ошибка оплаты заказа по банковской карте', 'wordpress_card_expresspay') . '</h2>';
			$html .= str_replace("##order_id##", $order->get_order_number(), nl2br($this->message_fail, true));
			$html .= '<br/><br/><p class="return-to-shop"><a class="button wc-backward" href="' . wc_get_checkout_url() . '">' . __('Попробовать заново', 'wordpress_card_expresspay') . '</a></p>';

			$this->log_info('fail', 'End render fail page; ORDER ID - ' . $order->get_order_number());

			return $html;
		}

		function check_ipn_response() {
			$this->log_info('check_ipn_response', 'Get notify from server; REQUEST METHOD - ' . $_SERVER['REQUEST_METHOD']);

			if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['action']) && $_REQUEST['action'] == 'notify') {
				$data = ( isset($_REQUEST['Data']) ) ? htmlspecialchars_decode($_REQUEST['Data']) : '';
				$data = stripcslashes($data);
				$signature = ( isset($_REQUEST['Signature']) ) ? $_REQUEST['Signature'] : '';

			    if($this->is_use_signature_notify) {
			    	if($signature == $this->compute_signature($data, $this->secret_key_notify))
				        $this->notify_success($data);
				    else  
				    	$this->notify_fail($data);
			    } else 
			    	$this->notify_success($data);
			}

			$this->log_info('check_ipn_response', 'End (Get notify from server); REQUEST METHOD - ' . $_SERVER['REQUEST_METHOD']);

			die();
		}

		private function notify_success($dataJSON) {
			global $woocommerce;

			try {
	        	$data = json_decode($dataJSON);
	    	} catch(Exception $e) {
				$this->log_error('notify_success', "Fail to parse the server response; RESPONSE - " . $dataJSON);

	    		$this->notify_fail($dataJSON);
	    	}

            $order = new WC_Order($data->AccountNo);

	        if(isset($data->CmdType)) {
	        	switch ($data->CmdType) {
	        		case '1':
						$order->update_status($this->status_after_payment, __('Счет успешно успешно и ожидает оплаты', 'wordpress_card_expresspay'));
	                    $this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет успешно оплачен; RESPONSE - ' . $dataJSON);
	        			break;
	        		case '2':
						$order->update_status($this->status_after_cancellation, __('Платеж отменён', 'wordpress_card_expresspay'));
						$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Платеж отменён; RESPONSE - '. $dataJSON);
						break;
					case '3':
						$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет успешно оплачен; RESPONSE - ' . $dataJSON);
						break;
	        		default:
						$this->notify_fail($dataJSON);
						die();
	        	}

		    	header("HTTP/1.0 200 OK");
		    	echo 'SUCCESS';
	        } else
				$this->notify_fail($dataJSON);	
		}

		private function notify_fail($dataJSON) {
			$this->log_error('notify_fail', "Fail to update status; RESPONSE - " . $dataJSON);
			
			header("HTTP/1.0 400 Bad Request");
			echo 'FAILED | Incorrect digital signature';
		}

		private function compute_signature($json, $secret_word) {
		    $hash = NULL;
		    $secret_word = trim($secret_word);
		    
		    if(empty($secret_word))
				$hash = strtoupper(hash_hmac('sha1', $json, ""));
		    else
		        $hash = strtoupper(hash_hmac('sha1', $json, $secret_word));

		    return $hash;
		}	

	    private function compute_signature_add_invoice($request_params, $secret_word) {
	    	$secret_word = trim($secret_word);
	        $normalized_params = array_change_key_case($request_params, CASE_LOWER);
	        $api_method = array(
				"serviceid",
				"accountno",
				"expiration",
				"amount",
				"currency",
				"info",
				"returnurl",
				"failurl",
				"language",
				"sessiontimeoutsecs",
				"expirationdate",
				"returntype"
	        );

	        $result = $this->token;

	        foreach ($api_method as $item)
	            $result .= ( isset($normalized_params[$item]) ) ? $normalized_params[$item] : '';

	        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

	        return $hash;
	    }
        
        private function compute_signature_get_form_url($request_params, $secret_word) {
	    	$secret_word = trim($secret_word);
            $normalized_params = array_change_key_case($request_params, CASE_LOWER);
	        $api_method = array(
	                "token",
                    "cardinvoiceno"
	        );

	        $result = "";

	        foreach ($api_method as $item)
	            $result .= ( isset($normalized_params[$item]) ) ? $normalized_params[$item] : '';

	        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

	        return $hash;
	    }

	    private function log_error_exception($name, $message, $e) {
	    	$this->log($name, "ERROR" , $message . '; EXCEPTION MESSAGE - ' . $e->getMessage() . '; EXCEPTION TRACE - ' . $e->getTraceAsString());
	    }

	    private function log_error($name, $message) {
	    	$this->log($name, "ERROR" , $message);
	    }

	    private function log_info($name, $message) {
	    	$this->log($name, "INFO" , $message);
	    }

	    private function log($name, $type, $message) {
			$log_url = wp_upload_dir();
			$log_url = $log_url['basedir'] . "/erip_expresspay";

			if(!file_exists($log_url)) {
				$is_created = mkdir($log_url, 0777);

				if(!$is_created)
					return;
			}

			$log_url .= '/express-pay-' . date('Y.m.d') . '.log';

			file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] .  "; DATETIME - " .date("Y-m-d H:i:s").  "; USER AGENT - " . $_SERVER['HTTP_USER_AGENT'] . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
	    }
	}
}
?>