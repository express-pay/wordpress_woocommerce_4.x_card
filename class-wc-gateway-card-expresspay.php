<?php
/*
  Plugin Name: Express Payments: Internet-Acquiring
  Plugin URI: https://express-pay.by/cms-extensions/wordpress
  Description: Express Payments: Internet-Acquiring - plugin for integration with the Express Payments service (express-pay.by) via API. The plugin allows you to issue invoices for payments by bank cards, receive and process notifications about payments by bank cards. The plugin description is available at: <a target="blank" href="https://express-pay.by/cms-extensions/wordpress">https://express-pay.by/cms-extensions/wordpress</a>
  Version: 1.0.5
  Author: LLC "TriInkom"
  Author URI: https://express-pay.by/
  License: GPLv2 or later
  License URI: http://www.gnu.org/licenses/gpl-2.0.html
  WC requires at least: 4.0
  WC tested up to: 5.6
  Text Domain: wordpress_card_expresspay
  Domain Path: /languages
 */

if(!defined('ABSPATH')) exit;

define("CARD_EXPRESSPAY_VERSION", "1.0.5");

add_action('plugins_loaded', 'card_expresspay_gateway', 0);

function add_wordpress_card_expresspay($methods) {
	$methods[] = 'wordpress_card_expresspay';

	return $methods;
}

function card_expresspay_gateway() {
	if(!class_exists('WC_Payment_Gateway')  or class_exists('Wordpress_Card_Expresspay'))
		return;

	add_filter('woocommerce_payment_gateways', 'add_wordpress_card_expresspay');

	load_plugin_textdomain("wordpress_card_expresspay", false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

	class Wordpress_Card_Expresspay extends WC_Payment_Gateway {
		private $plugin_dir;

		public function __construct() {
			$this->id = "expresspay_card";
            $this->method_title = __('Express Payments: Internet-Acquiring', 'wordpress_card_expresspay');
			$this->method_description = __('Payment by card service "Express Payments"', 'wordpress_card_expresspay');
			$this->plugin_dir = plugin_dir_url(__FILE__);

			$this->card_expresspay_form_fields();
			
			$this->title = $this->get_option('payment_method_title');
			$this->description = $this->get_option('payment_method_description');
			
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
			<h3><?php echo __('Express Payments: Internet-Acquiring', 'wordpress_card_expresspay'); ?></h3>
            <div style="display: inline-block;">
                 <a target="_blank" href="https://express-pay.by"><img src="<?php echo $this->plugin_dir; ?>assets/images/erip_expresspay_big.png" alt="exspress-pay.by" title="express-pay.by"></a>
            </div>
            <div style="margin-left: 6px; display: inline-block;">
				<?php _e('Express Payments: Internet-Acquiring - plugin for integration with the Express Payments service (express-pay.by) via API.
				<br/>The plugin allows you to issue an invoice for a card payment, receive and process a payment notification.
				<br/>The plugin description is available at: ', 'wordpress_card_expresspay'); ?><a target="blank" href="https://express-pay.by/cms-extensions/wordpress#woocommerce_4_x">https://express-pay.by/cms-extensions/wordpress#woocommerce_4_x</a>
            </div>

			<table class="form-table">
				<?php		
					$this->generate_settings_html();
				?>
			</table>

			<div class="copyright" style="text-align: center;">
				<?php _e("© All rights reserved | ООО «TriInkom»,", 'wordpress_card_expresspay'); ?> 2013-<?php echo date("Y"); ?><br/>
				<?php echo __('Version', 'wordpress_card_expresspay') . " " . CARD_EXPRESSPAY_VERSION ?>			
			</div>
			<?php
		}

		function card_expresspay_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __('Enable/Disable', 'wordpress_card_expresspay'),
					'type'    => 'checkbox',
					'default' => 'no'
				),
				'token' => array(
					'title'   => __('Token', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Generated in the panel express-pay.by', 'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'service_id' => array(
					'title'   => __('Service number', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Service number in express-pay.by', 'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'handler_url' => array(
					'title'   => __('Address for notifications', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'css' => 'display: none;',
					'description' => get_site_url() . '/?wc-api=wordpress_card_expresspay&action=notify'
				),
				'secret_key' => array(
					'title'   => __('Secret word for signing invoices', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('A secret word that is known only to the server and the client. Used to generate a digital signature. Set in the panel express-pay.by', 'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'is_use_signature_notify' => array(
					'title'   => __('Use digitally sign notifications', 'wordpress_erip_expresspay'),
					'type'    => 'checkbox',
					'description' => __('Use digitally sign notifications', 'wordpress_erip_expresspay'),
					'desc_tip'    => true
				),
				'secret_key_notify' => array(
					'title'   => __('Secret word for signing notifications', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('A secret word that is known only to the server and the client. Used to generate a digital signature. Set in the panel express-pay.by', 'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'session_timeout_secs' => array(
					'title'   => __('Session duration', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('The time interval specified in seconds during which the client can make a payment (ranges from 600 seconds (10 minutes) to 86400 seconds (1 day)). The default is 1200 seconds (20 minutes)', 'wordpress_card_expresspay'),
					'default' => '1200',
                    'desc_tip'    => true
				),
				'test_mode' => array(
					'title'   => __('Use test mode', 'wordpress_card_expresspay'),
					'type'    => 'checkbox'
				),
				'url_api' => array(
					'title'   => __('API address', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'default' => 'https://api.express-pay.by'
				),
				'url_sandbox_api' => array(
					'title'   => __('Test API address', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'default' => 'https://sandbox-api.express-pay.by'
				),
				'message_success' => array(
					'title'   => __('Successful order message', 'wordpress_card_expresspay'),
					'type'    => 'textarea',
					'default' => __('Order number "##order_id##" has been successfully paid. Click "continue".', 'wordpress_card_expresspay'),
					'css'	  => 'min-height: 160px;'
				),
                'message_fail' => array(
					'title'   => __('Order error message', 'wordpress_card_expresspay'),
					'type'    => 'textarea',
					'default' => __("An unexpected error occurred while executing the request. Please try again later or contact the store's technical support", 'wordpress_card_expresspay'),
					'css'	  => 'min-height: 160px;'
				),
				'status_after_payment' => array(
					'title'       => __( 'Status after payment', 'wordpress_card_expresspay' ),
					'type'        => 'select',
					'description' => __( 'The status that the order will have after payment', 'wordpress_card_expresspay' ),
					'options'     => wc_get_order_statuses(),
					'desc_tip'    => true,
				),
				'status_after_cancellation'    => array(
					'title'       => __( 'Status after cancellation', 'wordpress_card_expresspay' ),
					'type'        => 'select',
					'description' => __( 'The status that the order will have after cancellation', 'wordpress_card_expresspay' ),
					'options'     => wc_get_order_statuses(),
					'desc_tip'    => true,
				),
				'payment_method_title' => array(
					'title'   => __('Payment method name', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('The name that will be displayed in the cart when choosing a payment method', 'wordpress_card_expresspay'),
					'default' 	=> __("Express Payments: Internet-Acquiring",'wordpress_card_expresspay'),
					'desc_tip'    => true
				),
				'payment_method_description' => array(
					'title'   => __('Description of the payment method', 'wordpress_card_expresspay'),
					'type'    => 'text',
					'description' => __('Description that will be displayed in the payment method settings', 'wordpress_card_expresspay'),
					'default' 	=> __("Payment by card service Express Payments",'wordpress_card_expresspay'),
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
				$this->log_info('process_payment', 'STATUS - ' . sanitize_text_field($_REQUEST['status']));

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
				"Info" 					=> 	"Покупка в магазине",
				"Language"				=>  "ru",
				"SessionTimeoutSecs"	=>  1200,
				'ReturnType'			=> 	'redirect',				
				"ReturnUrl" 			=> 	get_site_url() . add_query_arg('status', 'success', add_query_arg('order-pay', $order->get_order_number(), add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id($order->get_checkout_payment_url()))))),
				"FailUrl" 				=> 	get_site_url() . add_query_arg('status', 'fail', add_query_arg('order-pay', $order->get_order_number(), add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id($order->get_checkout_payment_url()))))),
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
			
			wc_get_template(
				'order/order-details.php',
				array(
					'order_id' => $order_id,
				)
			);

			$order->update_status($this->status_after_payment, __('Invoice paid successfully', 'wordpress_card_expresspay'));

			$html = '';

			$html .= '<h2>' . __('Invoice paid successfully', 'wordpress_card_expresspay') . '</h2>';
			$html .= str_replace("##order_id##", $order->get_order_number(), nl2br($this->message_success, true));

			$html .= '<br/><br/><p class="return-to-shop"><a class="button wc-backward" href="' . get_permalink( wc_get_page_id( "shop" ) ) . '">' . __('Proceed', 'wordpress_card_expresspay') . '</a></p>';

			$this->log_info('success', 'End render success page; ORDER ID - ' . $order->get_order_number());

			return $html;
		}

		private function fail($order_id) {
			global $woocommerce;

			$order = new WC_Order($order_id);	

			$this->log_info('receipt_page', 'End request for add invoice');
			$this->log_info('fail', 'Initialization render fail page; ORDER ID - ' . $order->get_order_number());

			$order->update_status($this->status_after_cancellation, __('Invoice not paid', 'wordpress_card_expresspay'));

			$this->log_info('fail', 'End render fail page; ORDER ID - ' . $order->get_order_number());

			$html = '<h2>' . __('Error of payment for the order with a bank card', 'wordpress_card_expresspay') . '</h2>';
			$html .= str_replace("##order_id##", $order->get_order_number(), nl2br($this->message_fail, true));
			$html .= '<br/><br/><p class="return-to-shop"><a class="button wc-backward" href="' . wc_get_checkout_url() . '">' . __('Try again', 'wordpress_card_expresspay') . '</a></p>';

			$this->log_info('fail', 'End render fail page; ORDER ID - ' . $order->get_order_number());

			return $html;
		}

		function check_ipn_response() {
			$this->log_info('check_ipn_response', 'Get notify from server; REQUEST METHOD - ' . $_SERVER['REQUEST_METHOD']);

			if (sanitize_text_field($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_REQUEST['action']) && sanitize_text_field($_REQUEST['action'] == 'notify')) {
				$data = ( isset($_REQUEST['Data']) ) ? sanitize_text_field($_REQUEST['Data']) : '';
				$data = stripcslashes($data);
				$signature = ( isset($_REQUEST['Signature']) ) ? sanitize_text_field($_REQUEST['Signature']) : '';

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

			try{
				$order = new WC_Order($data->AccountNo);
			} catch (Exception $e){
				$this->log_error('notify_success', "Fail find to order!");
				die();
			}

			if (isset($data->CmdType)) {
				switch ($data->CmdType) {
					case '1':
						$order->update_status($this->status_after_payment, __('The bill is paid', 'wordpress_card_expresspay'));
						$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет оплачен; RESPONSE - ' . $dataJSON);
						break;
					case '2':
						$order->update_status($this->status_after_cancellation, __('Payment canceled', 'wordpress_card_expresspay'));
						$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Платеж отменён; RESPONSE - ' . $dataJSON);

						break;
					case '3':
						if ($data->Status == '1') {
							$order->update_status('pending_payment', __('Invoice awaiting payment', 'wordpress_card_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет ожидает оплаты; RESPONSE - ' . $dataJSON);
						} elseif ($data->Status == '2') {
							$order->update_status($this->status_after_cancellation, __('Invoice expired', 'wordpress_card_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет просрочен; RESPONSE - ' . $dataJSON);
						} elseif ($data->Status == '3') {
							$order->update_status($this->status_after_payment, __('The bill is paid', 'wordpress_card_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет оплачен; RESPONSE - ' . $dataJSON);
						} elseif ($data->Status == '5') {
							$order->update_status($this->status_after_cancellation, __('Invoice canceled', 'wordpress_card_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет отменен; RESPONSE - ' . $dataJSON);
						} elseif ($data->Status == '6') {
							$order->update_status($this->status_after_payment, __('Invoice paid by card', 'wordpress_card_expresspay'));
							$this->log_info('notify_success', 'Initialization to update status. STATUS ID - Счет оплачен картой; RESPONSE - ' . $dataJSON);
						}
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
				
			$this->log_info('compute_signature', 'RESULT - ' . $result);

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
			$log_url = $log_url['basedir'] . "/card_expresspay";

			if(!file_exists($log_url)) {
				$is_created = mkdir($log_url, 0777);

				if(!$is_created)
					return;
			}

			$log_url .= '/express-pay-' . date('Y.m.d') . '.log';

			file_put_contents($log_url, $type . " - IP - " . sanitize_text_field($_SERVER['REMOTE_ADDR']) .  "; DATETIME - " .date("Y-m-d H:i:s").  "; USER AGENT - " . sanitize_text_field($_SERVER['HTTP_USER_AGENT']) . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
	    }
	}
}
?>