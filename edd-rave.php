<?php 

/*
Plugin Name: Flutterwave payment gateway for Easy Digital Downloads
Plugin URI: https://ravepay.co/
Description: Accept payments using the Flutterwave Payment Gateway
Version: 1.1.0
Author: Flutterwave Developers
Author URI: https://ravepay.co/
*/

define( 'PPS_EDD_RAVE_URL', plugin_dir_url( __FILE__ ) );
define( 'PPS_EDD_RAVE_VERSION', '1.1.0' );

if (! class_exists('EDD_Rave')){

class EDD_Rave{

	private static $instance;
	public $file;
	public $logo;
	public $version;
	public $public_key;
	public $secret_key;
	

	public static function instance() {
		if ( !isset( self::$instance ) ) {
			self::$instance = new EDD_Rave(__FILE__);
		}
		return self::$instance;
	}

	private function __construct($file){
		
		global $edd_options;
		
		$this->file = $file;
		$this->logo = plugins_url( 'img/edd-rave.png',  __FILE__ );
		$this->version = '1.0';
		$this->msg = null;
		$this->method_title = __( "Rave", 'edd-rave' );
		$this->method_description = __( "PCI DSS Complaint payment gateway for recieving payments via cards, bank accounts, and USSD", 'edd-rave' );
		$this->base_url = 'https://api.ravepay.co';
		

        add_filter( 'edd_settings_sections_gateways', array( $this, 'gateway_section' ) );
		
		if (is_admin()){
			add_filter( 'edd_settings_gateways', array($this, 'add_gateway_settings') );
		}

		add_filter( 'edd_payment_gateways', array($this, 'rave_register_gateway') );
		add_filter( 'edd_currencies', array($this, 'register_rave_currencies') );
		add_filter( 'edd_accepted_payment_icons', array($this, 'edd_admin_rave_logo') );
		add_filter( 'edd_rave_cc_form', array($this, 'edd_rave_cc_form') );
		//process payment
		add_filter( 'edd_gateway_rave', array($this, 'verify_payment') );

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		
		if( edd_get_option( 'edd_rave_test_mode' ) ){
			// $this->base_url = 'https://ravesandboxapi.flutterwave.com';
			$this->public_key = edd_get_option('sandbox_public_key');
			$this->secret_key = edd_get_option('sandbox_secret_key');

     	}else{ 
     		// $this->base_url = 'https://api.ravepay.co';
     		$this->public_key = edd_get_option('live_public_key');
			$this->secret_key = edd_get_option('live_secret_key');
     	}

        $this->currency = edd_get_currency();
		
	}
	
    public function gateway_section( $sections ) {

        $sections['rave-settings'] = 'rave';
    
        return $sections;
    
    }
	
	public function rave_register_gateway( $gateways ) {
		$gateways['rave'] = array(
			'admin_label' => 'rave',
			'checkout_label' => 'rave');
		return $gateways;
	}

	public function edd_admin_rave_logo( $icons ) {
		$icons[$this->logo] = 'Rave Payment Gateway';
		return $icons;
	}

	public function add_gateway_settings( $settings ) {
		
		$rave_settings = array(
            array(
				'id'   => 'edd_rave_test_mode',
				'name' => 'Enable Sandbox Mode',
				'desc' => 'Sandbox mode enables you to test payments before going live. Once the LIVE MODE is enabled on your Rave account uncheck this',
				'type' => 'checkbox',
				'std'  => 0,
			),
			array(
				'id'	=>	'rave_gateway_settings',
				'name'	=>	'<h3>' . __('Rave Payment Gateway', 'edd_rave') . '</h3>',
				'desc'	=>	'<p>' . __('PCI DSS Complaint payment gateway for recieving payments via cards, bank accounts, and USSD', 'edd_rave') . '</p>',
				'type'	=>	'header'
			),
			array(
				'id'	=>	'sandbox_public_key',
				'name'	=>	__( 'Sandbox Public Key', 'edd_rave' ),
				'type'	=>	'text',
			),
			array(
				'id'	=>	'sandbox_secret_key',
				'name'	=>	__( 'Sandbox Secret Key', 'edd_rave' ),
				'type'	=>	'text',
			),
			array(
				'id'	=>	'live_public_key',
				'name'	=>	__( 'Live Public Key', 'edd_rave' ),
				'type'	=>	'text',
			),
			array(
				'id'	=>	'live_secret_key',
				'name'	=>	__( 'Live Secret Key', 'edd_rave' ),
				'type'	=>	'text',
			),
		);

        if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$rave_settings = array( 'rave-settings' => $rave_settings );
		}
		
		return array_merge($settings, $rave_settings);
	}
	
	public function register_rave_currencies($currencies) {
		$currencies['GHS'] = __('Ghana Cedis (&#8373;)', 'edd_rave');
		$currencies['KES'] = __('Kenyan Shilling (KSh)', 'edd_rave');
		$currencies['NGN'] = __('Nigerian Naira (&#8358;)', 'edd_rave');
		$currencies['ZAR'] = __('South African Rand (R)', 'edd_rave');
		$currencies['RWF'] = __('Rwandan Franc', 'edd_rave');
		$currencies['UGX'] = __('Ugandan Shilling', 'edd_rave');
		$currencies['TZS'] = __('Tanzanian Shilling', 'edd_rave');
		$currencies['SLL'] = __('Sierra Leone Leone', 'edd_rave');
		$currencies['ZMW'] = __('Zambian Kwacha', 'edd_rave');
		$currencies['XAF'] = __('Central African Republic Franc', 'edd_rave');
		return $currencies;
	}
	
	public function payment_scripts() {
		if (!edd_is_checkout() ) {
			return;
		}
      	wp_enqueue_script( 'rave_inline_js', $this->base_url . '/flwv3-pug/getpaidx/api/flwpbf-inline.js');
	}

	public function generateNewReference()
    { 
    	$reference = '';
	    $check = true;
	    while ($check) {
	        $characters = strtoupper('0abcd'.time().'efz1nrstu2o'.time().'123456'.time().'pqghijk'.time().'lm3456vwxy'.time().'789');
		    $charactersLength = strlen($characters);
		    $reference = 'RAVEEDD';
		    for ($i = 0; $i < 8; $i++) {
		        $reference .= $characters[rand(0, $charactersLength - 1)];
		    }
		    $check = false;
		    $reference .= time();
	    }
	    return $reference;
    }

	public function edd_rave_cc_form() {
		global $edd_options;
		$reference = $this->generateNewReference();
		ob_start();
		
		$checked = 1;
		$rave_public = edd_get_option( 'edd_rave_test_mode' ) ? edd_get_option('sandbox_public_key') : edd_get_option('live_public_key');

        echo "<script>
            var store_currency='".edd_get_currency()."';
        </script>";
		switch (edd_get_currency()) {
			case 'GHS':
				$country = 'GH';
				break;
			case 'KES':
				$country = 'KE';
				break;
			case 'ZAR':

				$country = 'ZA';
				break;
			case 'TZS':
				$country = 'TZ';
				break;
			
			default:
				$country = 'NG';
				break;
		}
		?>
		<input  name="rave_reference" type="hidden" value="<?php echo esc_attr($reference); ?>"/>
		<input type="hidden" id="flwReference" name="flwReference" value="" />
		<script type="text/javascript">
			jQuery('form#edd_purchase_form').on('submit', function(e){
				 e.preventDefault();
			if(jQuery('input:hidden[name=edd-gateway]').val()=='rave'){
			    console.log("Using rave");
			    jQuery('form#edd_purchase_form').addClass("processing");
			  
			     	getpaidSetup({
				      PBFPubKey: '<?php echo $this->public_key; ?>',
				      customer_email: document.getElementById('edd-email').value,
				      customer_firstname: document.getElementById('edd-first').value,
				      customer_lastname: document.getElementById('edd-last').value,
				      amount: '<?php echo (int)(edd_get_cart_total()); ?>',
				      currency: store_currency,
				      txref : '<?php echo esc_attr($reference); ?>',
				      country : '<?php echo esc_attr($country); ?>',
				      onclose: function() {},
				      callback: function(response) {
				        var flw_ref = response.tx.flwRef;
				        console.log("This is the response returned after a charge", response);
				        if ( response.tx.chargeResponseCode == "00" || response.tx.chargeResponseCode == "0" ) {
				        	
				  		document.getElementById('flwReference').value = flw_ref;

				          	document.getElementById('edd_purchase_form').submit();
				        }else{
				          	alert(response.respmsg);
				        }
				      }
				    });
			  }else{
			    console.log("Not using Rave. Carry on");
			  };
			});

		</script>
		<?php
		echo ob_get_clean();
	}

	private function getTransactionDetails( $flwReference ) {

      $url = $this->base_url . '/flwv3-pug/getpaidx/api/verify';
      $args = array(
		'timeout' => 30,
        'body' => array(
			
          'flw_ref' => $flwReference,
		  'SECKEY' => $this->secret_key,
		'normalize' => 1 ),
        'sslverify' => false
	  );
	  
	  $response = wp_remote_post( $url, $args );
	 
      $result = wp_remote_retrieve_response_code( $response );
	 
      if( $result === 200 ){
        return wp_remote_retrieve_body( $response );
      }
	 
      return $result;

    }

	public function verify_payment( $purchase_data ) {
		global $edd_options;

		$reference = (isset( $_POST['rave_reference'] ) ) ? $_POST['rave_reference'] : null;
		$flwReference = (isset( $_POST['flwReference'] ) ) ? $_POST['flwReference'] : null;

		if (!isset( $purchase_data['post_data']['edd-gateway'])){
					return;
		}
		$errors = edd_get_errors();
		
		if (!$errors){
			$payment_data = array( 
				'price' => $purchase_data['price'], 
				'date' => $purchase_data['date'],
				'user_email' => $purchase_data['user_email'],
				'purchase_key' => $purchase_data['purchase_key'],
				'currency' => edd_get_currency(),
				'downloads' => $purchase_data['downloads'],
				'cart_details' => $purchase_data['cart_details'],
				'user_info' => $purchase_data['user_info'],
				'status' => 'pending'
			);

			$purchase_id= edd_insert_payment($payment_data);
			
			if (!$purchase_id || !$this->secret_key ){
				edd_record_gateway_error( __( 'Unable to verify transaction!', 'edd_rave' ), sprintf( __( 'Secret key is not set: %s', 'edd_rave' ), json_encode( $payment_data ) ), $purchase_id);
				edd_send_back_to_checkout( '?payment-mode=1' . $purchase_data['post_data']['edd-gateway'] );

			}else{
				
				if (!$reference || !$flwReference){
					$error = "Unable to verify transaction! No transaction reference or FlW reference passed. Contact Site Owner.";
					edd_update_payment_status($purchase_id, 'failed');
					edd_insert_payment_note( $purchase_id,$error);
					echo $error;
					throw new Exception( __($error));
				}else{
					$amount = (int)$payment_data['price'];
					$email = strtolower($payment_data['user_email']);

					$result = json_decode($this->getTransactionDetails($flwReference));
					if ($result->data->flwMeta->chargeResponse === '00' || $result->data->flwMeta->chargeResponse === '0') {
						$emailP = 'customer.email';
						$rave_email = strtolower($result->data->customer->email);
						$rave_amount = $result->data->amount;
						
						if ($rave_amount == $amount) {
							edd_update_payment_status( $purchase_id, 'complete' );
							edd_insert_payment_note( $purchase_id, "Payment Successful! <br> Transaction Reference:".$reference.", <br> FLWReference:".$flwReference );
							edd_send_to_success_page();
						}else{
							$error = "Invalid amount paid. Contact Site Owner. Reference:".$flwReference;
							edd_insert_payment_note( $purchase_id, __($error) );
							edd_update_payment_status( $purchase_id, 'failed' );
							edd_insert_payment_note( $purchase_id,$error);
							echo $error;
							throw new Exception( __($error));
						}

					}else{
						$error = "Failed with message from Rave: " . $result->data->flwMeta->chargeResponseMessage.'. Reference:'.$flwReference;
						edd_insert_payment_note( $purchase_id, __($error) );
						edd_update_payment_status( $purchase_id, 'failed' );
						edd_insert_payment_note( $purchase_id,$error);
						echo $error;
						throw new Exception( __($error));

					}
				}
			}
		}else{
			edd_send_back_to_checkout( '?payment-mode=2' . $purchase_data['post_data']['edd-gateway'] );
		}
	}
}

}

function edd_rave_missing_notice() {
	echo '<div class="error"><p>' . sprintf( __( '%sEasy Digital Downloads%s is required to use the Rave payment gateway for Easy Digital Downloads plugin.' ), '<a href="' . admin_url( 'plugin-install.php?tab=search&type=term&s=easy+digital+downloads&plugin-search-input=Search+Plugins' ) . '">', '</a>' ) . '</p></div>';
}


function edd_rave_init() {
	if (! class_exists('Easy_Digital_Downloads') ) {
		add_action( 'admin_notices', 'edd_rave_missing_notice' );
	} else {
		return EDD_Rave::instance();
	}
}
add_action( 'plugins_loaded', 'edd_rave_init', 20 );

function pps_edd_rave_plugin_action_links( $links ) {

	$settings_link = array(
        // https://mydigital.live/wp-admin/edit.php?post_type=download&page=edd-settings&tab=gateways&section=payfast-settings
		'settings' => '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=rave-settings' ) . '" title="Settings">Settings</a>',
	);
	
	return array_merge( $settings_link, $links );
	
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pps_edd_rave_plugin_action_links' );

function pps_edd_rave_extra_currency_symbol( $symbol, $currency ) {

    switch ( $currency ) {
        case 'ZAR':
            $symbol = 'R';
            break;
    }

    return $symbol;

}
add_filter( 'edd_currency_symbol', 'pps_edd_rave_extra_currency_symbol', 10, 2 );

function pps_edd_rave_format_ngn_currency_before( $formatted, $currency, $price ) {

    $symbol = edd_currency_symbol( $currency );

    return $symbol . $price;
}
add_filter( 'edd_zar_currency_filter_before', 'pps_edd_rave_format_ngn_currency_before', 10, 3 );


function pps_edd_rave_check_config() {

	$is_enabled = edd_is_gateway_active( 'rave' );

	if ( ( ! $is_enabled || empty( pps_payfast_keys() ) ) && 'rave' == edd_get_chosen_gateway() ) {
		edd_set_error( 'rave_gateway_not_configured', 'An error occured with configuration.' );
	}

	if ( ! in_array( edd_get_currency(), array( 'ZAR' ) ) && 'rave' == edd_get_chosen_gateway() ) {
		edd_set_error( 'rave_gateway_invalid_currency', 'Set your store currency to ZAR (R)' );
	}

}
add_action( 'edd_pre_process_purchase', 'pps_edd_rave_check_config', 1 );


?>