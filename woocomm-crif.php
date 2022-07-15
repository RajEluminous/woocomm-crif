<?php
/**
* Plugin Name: Woocommerce CRIF
* Plugin URI: https://eluminoustechnologies.com/
* Description: This plugin checks the credit of consumer via crif api.
* Version: 1.0.0
* Text Domain: woocomm-crif
* Author: Rajendra Mahajan
* Author URI: https://eluminoustechnologies.com/
* License: GPL-2.0+
* License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/  
// Plugin directory url.
define('WCUFLS_URL', WP_PLUGIN_URL."/".dirname( plugin_basename( __FILE__ ) ) );

    /**
     * For development: To display error, set WP_DEBUG to true.
     * In production: set it to false
    */


 // Get absolute path 
    if ( !defined('WCUFLS_ABSPATH'))
    	define('WCUFLS_ABSPATH', dirname(__FILE__) . '/');

 // Get absolute path 
    if ( !defined('ABSPATH'))
    	define('ABSPATH', dirname(__FILE__) . '/');

/**
 *  Current plugin version.
 */
if ( ! defined( 'WCUFLS_VER' ) ) {
	define( 'WCUFLS_VER', '1.0.0' );
}

define('WCUFLS_TEMPLATES',WCUFLS_ABSPATH.'templates');
define('WCUFLS_PAGE_TITLE','Woocommerce CRIF');
define('WCUFLS_PAY_INVOICE_ERROR_MSG','Die Zahlung auf Rechnung konnte nicht ausgeführt werden. Bitte wählen Sie eine andere Zahlungsmethode');
 // define('WCUFLS_PAY_INVOICE_ERROR_MSG','Payment per invoice could not be executed. Please select another payment method');

  // Main Class
class WoocommCrif {
	
	function __construct() {	
		global $wpdb;
		global $wp;
		global $woocommerce;
		global $product;
		global $post;
		
		//Initial loading			 
		add_action('admin_init',array($this,'init'),0);	 
		add_action('admin_menu', array($this, 'wcufs_admin_menu'));
		add_action('woocommerce_init', array($this, 'on_woocommerce_init'));
		
		// To add the Gender = MALE/FEMALE option - as required by CRIF
		add_action('woocommerce_after_checkout_billing_form', array($this,'misha_select_field' ));
		add_action('woocommerce_checkout_process', array($this,'misha_check_if_selected'));
		add_action('woocommerce_checkout_update_order_meta', array($this,'misha_save_what_we_added' ));
		// add_filter( 'woocommerce_billing_fields', array($this,'custom_woocommerce_billing_fields' ));	
	} 
	function custom_woocommerce_billing_fields($fields) {
		$fields['billing_address_2']['label'] = 'Address 2';
		$fields['billing_address_2']['label_class'] = '';

		return $fields;
		
	}
	function enabling_date_picker() {
		// Only on front-end and checkout page
		if( is_admin() || ! is_checkout() ) return;

		// Load the datepicker jQuery-ui plugin script
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style('jquery-ui', "https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/smoothness/jquery-ui.css", '', '', false);
		
		wp_register_style( 'childstyle', WCUFLS_URL.'/css/style.css'  );
		wp_enqueue_style( 'childstyle' );
		
		//wp_enqueue_script('custom-aos', WCUFLS_URL.'/js/script.js');
	}
	
	// Check WooCommerce installed or not
	function init(){
		
		if (!is_plugin_active('woocommerce/woocommerce.php')) {
			echo "<div class='error'><p>". sprintf(__('%s WooCommerce %s plugin is not active. In order to make %s Woocommerce CRIF %s plugin work, you need to install and activate %s WooCommerce %s first', 'woocomm-crif'), "<strong>", "</strong>", "<strong>", "</strong>", "<strong>", "</strong>") . "</p></div>";

			if (is_plugin_active('woocomm-crif/woocomm-crif.php')) {
				deactivate_plugins(plugin_basename(__FILE__));
			}
			unset($_GET['activate']);
		}		 		 
	}
	
	// add menu to admin
	function wcufs_admin_menu() {	  
		add_menu_page('Woocommerce CRIF','Woocommerce CRIF','administrator', 'woocomm_crif',array($this,'wccrif_admin_form'),'',100);	   	     
	}
	
	// Admin menu
	function wccrif_admin_form() {
		echo "Woocommerce CRIF";		
	}
	
	// on woocommerce init
	function on_woocommerce_init() {
		add_action( 'wp_enqueue_scripts', array($this,'enabling_date_picker') );
		// BACS payement gateway description: Append custom select field
		add_filter( 'woocommerce_gateway_description', array($this,'gateway_bacs_custom_fields'), 20, 2 );
		// Checkout custom field validation
		add_action('woocommerce_checkout_process', array($this,'bacs_option_validation') );	
		// Checkout custom field save to order meta
		//add_action('woocommerce_checkout_create_order', array($this,'save_bacs_option_order_meta'), 10, 2 );
		// Display custom field on order totals lines everywhere
		//add_action('woocommerce_get_order_item_totals', array($this,'display_bacs_option_on_order_totals'), 10, 3 );
		// Display custom field in Admin orders, below billing address block
		//add_action( 'woocommerce_admin_order_data_after_billing_address', array($this,'display_bacs_option_near_admin_order_billing_address'), 10, 1 );
	}	
	
	
	function gateway_bacs_custom_fields( $description, $payment_id ){

		if( 'german_market_purchase_on_account' === $payment_id ){
        ob_start(); // Start buffering
        $datepicker_slug = 'my_datepicker';

        echo '<div id="datepicker-wrapper">';
        woocommerce_form_field($datepicker_slug, array(
        	'type' => 'date',
        	'class'=> array( 'form-row-first my-datepicker'),
        	'label' => __('Geburtsdatum wählen'),
			'required' => true, // Or false

		), '' );

        echo '<br clear="all"></div>';
        $crif_userid = get_current_user_id();
		// Jquery: Enable the Datepicker
        ?>
        <input type="hidden" class="input-hidden" name="crif_userid" id="crif_userid" value="<?php echo $crif_userid;?>"> 
        <input type="hidden" class="input-hidden" name="crif_error_msg" id="crif_error_msg" value="<?php echo WCUFLS_PAY_INVOICE_ERROR_MSG;?>"> 
        <div id="myModal" class="modal">	
        	<div class="modal-content">
        		<span class="close">&times;</span>
        		<p><?php echo "WCUFLS_PAY_INVOICE_ERROR_MSG";?></p>
        	</div>
        </div>
		<!-- <script language="javascript">
		jQuery( function($){
			var a = '#<?php echo $datepicker_slug ?>';
			$(a).datepicker({
				dateFormat: 'yy-mm-dd', // ISO formatting date
				changeYear: true,
				changeMonth : true,
				yearRange: '-80y:yy-10',
			});
		});
	</script> -->
	<?php

        $description .= ob_get_clean(); // Append buffered content
    }
    return $description;
}

function bacs_option_validation() {
	if ( isset($_POST['payment_method']) && $_POST['payment_method'] === 'german_market_purchase_on_account'	&& isset($_POST['my_datepicker']) && empty($_POST['my_datepicker']) ) {
		wc_add_notice( __( 'Please Select Date of Birth, pleaseddddddddddd.' ), 'error' );
			//  wc_add_notice( __( 'Bitte wählen Sie das Geburtsdatum aus.' ), 'error' );
// wp_redirect( wc_get_page_permalink( 'checkout' ) );
// exit();
			//$errors->add( 'validation', 'Date filed is required' );

	}
	else if ( isset($_POST['payment_method']) && $_POST['payment_method'] === 'german_market_purchase_on_account'	&& isset($_POST['my_datepicker']) && !empty($_POST['my_datepicker']) ) {

			// Get posted billing address to CRIF Request			 
		$getD = WC()->checkout->get_posted_data(); 
			// print_r($getD); 
			// die();

		$totalcart = WC()->cart->get_totals();
		$consmr_dob = str_replace('-','',$_POST['my_datepicker']);
		$billing_country = $getD['billing_country'];
		$isoCountryCode = $this->getISO3CountryCode($billing_country);	
			// print_r($getD['billing_gender']);die;
		$data = array();

		$data['consmr_name'] = $getD['billing_first_name'].' '.$getD['billing_last_name'];
		$data['consmr_firstName']  = $getD['billing_first_name'];
		$data['consmr_gender']  = $getD['billing_gender'];
		$data['consmr_contact_email']  = $getD['billing_email'];
		$data['consmr_country']  = $isoCountryCode;	  // 3 digit country code	
		$data['consmr_dateOfBirth']  = $consmr_dob; //YYYYMMDD
		$data['consmr_street']  = $getD['billing_address_1'];
		$data['consmr_house']  = $getD['billing_address_2'];
		$data['consmr_city']  = $getD['billing_city'];
		$data['consmr_zip']  = $getD['billing_postcode'];
		$data['consmr_orderValue'] = $totalcart['total'];
		$data['clientdate_reference'] = "Test_RCO_01";   
		// print_r($getD['billing_gender']);die;
		$soapRsp = $this->processCRIFSoapRequest($data);
			// print_r($soapRsp);
			// die;

		if(isset($soapRsp['archiveID']) && $soapRsp['archiveID']>0 && isset($soapRsp['decision'])) {
				// echo "Hello";
			if($soapRsp['decision']=='YELLOW' || $soapRsp['decision']=='RED') {
				 	// echo "world";

					//add_filter( 'pre_option_woocommerce_default_gateway' . '__return_false', 99 );
				setcookie( 'crif_usr_'.get_current_user_id(), 'true', time() + (7 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );	
				wc_add_notice( __( "'".WCUFLS_PAY_INVOICE_ERROR_MSG."'" ), 'error' ); 
					// $checkout = WC()->checkout();
					// do_action( 'woocommerce_after_checkout_form', $checkout );
					#wp_rediret(wc_get_checkout_url()."?error=invoice");
					// exit();
					// wc_add_notice( __( "'".WCUFLS_PAY_INVOICE_ERROR_MSG."'" ), 'error' ); 
                    // return json_encode(array('result' => 'failure'));
                    // die;
					// wc_print_notice(__( "'".WCUFLS_PAY_INVOICE_ERROR_MSG."'" ), 'error' );

			}
		}

			// To Set Cookie in case of Failure.
			//setcookie( 'Rajendra', '112233', time() + (7 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );	

			// die(); 
	}
}

function wpchris_filter_gateways( $gateways ){
	global $woocommerce;


	$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
	print_r($available_gateways);
}

	//function to show error msg 
function showPayInvoiceErrorMsg() {
		/*?><style> .modal { display: block; }</style>
		<?php */
	}
	
	//function to Process CRIF Soap Request 
	function processCRIFSoapRequest($dataVal){
		$consmr_name = $dataVal['consmr_name'];
		$consmr_firstName = $dataVal['consmr_firstName'];
		// $consmr_gender = 'MALE';//$dataVal['consmr_gender'];
		$consmr_gender = $dataVal['consmr_gender'];
		$consmr_contact_email = $dataVal['consmr_contact_email'];
		$consmr_country = $dataVal['consmr_country'];
		$consmr_dateOfBirth = $dataVal['consmr_dateOfBirth'];
		$consmr_street = $dataVal['consmr_street'];
		$consmr_house = $dataVal['consmr_house'];
		$consmr_city = $dataVal['consmr_city'];
		$consmr_zip = $dataVal['consmr_zip'];
		$consmr_orderValue = $dataVal['consmr_orderValue'];
		$clientdate_reference = $dataVal['clientdate_reference'];

		//###### Process SOAP REQUEST ###########		
		
		$data = '<?xml version="1.0" encoding="utf-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" 
		xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"> 
		<SOAP-ENV:Header> 
		<messageContext xmlns="http://www.deltavista.com/dspone/ordercheck-if/V001"> 		
		<correlationID>DE-456321</correlationID> 
		</messageContext> 
		</SOAP-ENV:Header> 
		<SOAP-ENV:Body> 
		<orderCheckRequest xmlns="http://www.deltavista.com/dspone/ordercheck-if/V001"> 
		<product> 
		<name>CreditCheckConsumer</name> 
		<country>'.$consmr_country.'</country> 
		<proofOfInterest>ABK</proofOfInterest> 
		</product> 
		<searchedAddress> 
		<legalForm>PERSON</legalForm> 
		<address> 
		<name>'.$consmr_name.'</name> 
		<firstName>'.$consmr_firstName.'</firstName> 
		<gender>'.$consmr_gender.'</gender> 
		<dateOfBirth>'.$consmr_dateOfBirth.'</dateOfBirth> 
		<location> 
		<street>'.$consmr_street.'</street> 
		<house>'.$consmr_house.'</house> 
		<city>'.$consmr_city.'</city> 
		<zip>'.$consmr_zip.'</zip> 
		<country>'.$consmr_country.'</country> 
		</location> 
		</address> 
		<contact> 
		<item>email</item> 
		<value>'.$consmr_contact_email.'</value> 
		</contact> 
		</searchedAddress> 
		<clientData> 
		<reference>'.$clientdate_reference.'</reference> 
		<order> 
		<orderValue>'.$consmr_orderValue.'</orderValue> 
		</order> 
		</clientData> 
		</orderCheckRequest> 
		</SOAP-ENV:Body> 
		</SOAP-ENV:Envelope>';


		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://demo-ordercheck.deltavista.de/soap");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, "YakSleep_xml_demo:f!Y6mM6euw");
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=utf-8", "Content-Length: " . strlen($data)));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

		$output = curl_exec($ch);
		curl_close($ch);

		$cleanxml = str_ireplace(['soapenv:', 'soap:'], '', $output);
		$cleanxml = str_ireplace('ns1:','', $cleanxml);
		$xml = simplexml_load_string($cleanxml);
		//echo "<pre>";
		//print_r($xml);

		$archiveID = isset($xml->Body->orderCheckResponse->archiveID)?$xml->Body->orderCheckResponse->archiveID:"";
		$decision = isset($xml->Body->orderCheckResponse->myDecision->decision)?$xml->Body->orderCheckResponse->myDecision->decision: "";
		$error_code = isset($xml->Body->Fault->detail->error->code)?$xml->Body->Fault->detail->error->code:"";
		$error_msg =  isset($xml->Body->Fault->detail->error->messageText)?$xml->Body->Fault->detail->error->messageText:""; 
		$error_faultstring =  isset($xml->Body->Fault->faultstring)?$xml->Body->Fault->faultstring:"";  		
		//##### END SOAP REQUEST ################	
		
		$errMsg = '';
		$errFlag = false;
		if($error_code>0) {
			$errMsg .= $error_code;
			$errFlag = true;
		} 
		else if(!empty($error_msg)) {
			$errMsg .= ' '.$error_msg;
			$errFlag = true;
		}
		else if(!empty($error_faultstring)) {
			$errMsg .= ' '.$error_faultstring;
			$errFlag = true;
		}
		
		return array('archiveID' => current($archiveID),
			'decision' => current($decision),
			'errFlag' => $errFlag,
			'errMsg' => $errMsg		
		); 
		
	}
	
	// function ISO3 Country codes
	function getISO3CountryCode($ccode) {
		//get country code.
		$jsonCC = '{"BD": "BGD", "BE": "BEL", "BF": "BFA", "BG": "BGR", "BA": "BIH", "BB": "BRB", "WF": "WLF", "BL": "BLM", "BM": "BMU", "BN": "BRN", "BO": "BOL", "BH": "BHR", "BI": "BDI", "BJ": "BEN", "BT": "BTN", "JM": "JAM", "BV": "BVT", "BW": "BWA", "WS": "WSM", "BQ": "BES", "BR": "BRA", "BS": "BHS", "JE": "JEY", "BY": "BLR", "BZ": "BLZ", "RU": "RUS", "RW": "RWA", "RS": "SRB", "TL": "TLS", "RE": "REU", "TM": "TKM", "TJ": "TJK", "RO": "ROU", "TK": "TKL", "GW": "GNB", "GU": "GUM", "GT": "GTM", "GS": "SGS", "GR": "GRC", "GQ": "GNQ", "GP": "GLP", "JP": "JPN", "GY": "GUY", "GG": "GGY", "GF": "GUF", "GE": "GEO", "GD": "GRD", "GB": "GBR", "GA": "GAB", "SV": "SLV", "GN": "GIN", "GM": "GMB", "GL": "GRL", "GI": "GIB", "GH": "GHA", "OM": "OMN", "TN": "TUN", "JO": "JOR", "HR": "HRV", "HT": "HTI", "HU": "HUN", "HK": "HKG", "HN": "HND", "HM": "HMD", "VE": "VEN", "PR": "PRI", "PS": "PSE", "PW": "PLW", "PT": "PRT", "SJ": "SJM", "PY": "PRY", "IQ": "IRQ", "PA": "PAN", "PF": "PYF", "PG": "PNG", "PE": "PER", "PK": "PAK", "PH": "PHL", "PN": "PCN", "PL": "POL", "PM": "SPM", "ZM": "ZMB", "EH": "ESH", "EE": "EST", "EG": "EGY", "ZA": "ZAF", "EC": "ECU", "IT": "ITA", "VN": "VNM", "SB": "SLB", "ET": "ETH", "SO": "SOM", "ZW": "ZWE", "SA": "SAU", "ES": "ESP", "ER": "ERI", "ME": "MNE", "MD": "MDA", "MG": "MDG", "MF": "MAF", "MA": "MAR", "MC": "MCO", "UZ": "UZB", "MM": "MMR", "ML": "MLI", "MO": "MAC", "MN": "MNG", "MH": "MHL", "MK": "MKD", "MU": "MUS", "MT": "MLT", "MW": "MWI", "MV": "MDV", "MQ": "MTQ", "MP": "MNP", "MS": "MSR", "MR": "MRT", "IM": "IMN", "UG": "UGA", "TZ": "TZA", "MY": "MYS", "MX": "MEX", "IL": "ISR", "FR": "FRA", "IO": "IOT", "SH": "SHN", "FI": "FIN", "FJ": "FJI", "FK": "FLK", "FM": "FSM", "FO": "FRO", "NI": "NIC", "NL": "NLD", "NO": "NOR", "NA": "NAM", "VU": "VUT", "NC": "NCL", "NE": "NER", "NF": "NFK", "NG": "NGA", "NZ": "NZL", "NP": "NPL", "NR": "NRU", "NU": "NIU", "CK": "COK", "XK": "XKX", "CI": "CIV", "CH": "CHE", "CO": "COL", "CN": "CHN", "CM": "CMR", "CL": "CHL", "CC": "CCK", "CA": "CAN", "CG": "COG", "CF": "CAF", "CD": "COD", "CZ": "CZE", "CY": "CYP", "CX": "CXR", "CR": "CRI", "CW": "CUW", "CV": "CPV", "CU": "CUB", "SZ": "SWZ", "SY": "SYR", "SX": "SXM", "KG": "KGZ", "KE": "KEN", "SS": "SSD", "SR": "SUR", "KI": "KIR", "KH": "KHM", "KN": "KNA", "KM": "COM", "ST": "STP", "SK": "SVK", "KR": "KOR", "SI": "SVN", "KP": "PRK", "KW": "KWT", "SN": "SEN", "SM": "SMR", "SL": "SLE", "SC": "SYC", "KZ": "KAZ", "KY": "CYM", "SG": "SGP", "SE": "SWE", "SD": "SDN", "DO": "DOM", "DM": "DMA", "DJ": "DJI", "DK": "DNK", "VG": "VGB", "DE": "DEU", "YE": "YEM", "DZ": "DZA", "US": "USA", "UY": "URY", "YT": "MYT", "UM": "UMI", "LB": "LBN", "LC": "LCA", "LA": "LAO", "TV": "TUV", "TW": "TWN", "TT": "TTO", "TR": "TUR", "LK": "LKA", "LI": "LIE", "LV": "LVA", "TO": "TON", "LT": "LTU", "LU": "LUX", "LR": "LBR", "LS": "LSO", "TH": "THA", "TF": "ATF", "TG": "TGO", "TD": "TCD", "TC": "TCA", "LY": "LBY", "VA": "VAT", "VC": "VCT", "AE": "ARE", "AD": "AND", "AG": "ATG", "AF": "AFG", "AI": "AIA", "VI": "VIR", "IS": "ISL", "IR": "IRN", "AM": "ARM", "AL": "ALB", "AO": "AGO", "AQ": "ATA", "AS": "ASM", "AR": "ARG", "AU": "AUS", "AT": "AUT", "AW": "ABW", "IN": "IND", "AX": "ALA", "AZ": "AZE", "IE": "IRL", "ID": "IDN", "UA": "UKR", "QA": "QAT", "MZ": "MOZ"}';

		$cc = json_decode($jsonCC, true);

		return $cc[$ccode];
	}
	
	 	// Functions related to GENDER FIELD
	// function misha_select_field( $checkout ){

	// 	// you can also add some custom HTML here	 
	// 	woocommerce_form_field( 'crif_gender', array(
	// 		'type'          => 'select', // text, textarea, select, radio, checkbox, password, about custom validation a little later
	// 		'required'	=> true, // actually this parameter just adds "*" to the field
	// 		'class'         => array('misha-field', 'form-row-wide'), // array only, read more about classes and styling in the previous step
	// 		'label'         => 'Gender',
	// 		'label_class'   => 'misha-label', // sometimes you need to customize labels, both string and arrays are supported
	// 		'options'	=> array( // options for <select> or <input type="radio" />
	// 			''		=> 'Please select', // empty values means that field is not selected
	// 			'MALE'	=> 'Male', // 'value'=>'Name'
	// 			'FEMALE'	=> 'Female'
	// 			)
	// 		), $checkout->get_value( 'crif_gender' ) );

	// 	// you can also add some custom HTML here

	// }
	
	 // save field values
	function misha_save_what_we_added( $order_id ){

		if( !empty( $_POST['crif_gender'] ) )
			update_post_meta( $order_id, 'crif_gender', sanitize_text_field( $_POST['crif_gender'] ) );  

	}



	// function misha_check_if_selected() {	 
	// 	// you can add any custom validations here
	// 	if ( empty( $_POST['crif_gender'] ) )
	// 		wc_add_notice( 'Please select your preferred contact method.', 'error' );

	// } 
	

}
 // Call class
 // Testnote
new WoocommCrif();


function getISO3CountryCodeByCode($ccode) {
		//get country code.
	$jsonCC = '{"BD": "BGD", "BE": "BEL", "BF": "BFA", "BG": "BGR", "BA": "BIH", "BB": "BRB", "WF": "WLF", "BL": "BLM", "BM": "BMU", "BN": "BRN", "BO": "BOL", "BH": "BHR", "BI": "BDI", "BJ": "BEN", "BT": "BTN", "JM": "JAM", "BV": "BVT", "BW": "BWA", "WS": "WSM", "BQ": "BES", "BR": "BRA", "BS": "BHS", "JE": "JEY", "BY": "BLR", "BZ": "BLZ", "RU": "RUS", "RW": "RWA", "RS": "SRB", "TL": "TLS", "RE": "REU", "TM": "TKM", "TJ": "TJK", "RO": "ROU", "TK": "TKL", "GW": "GNB", "GU": "GUM", "GT": "GTM", "GS": "SGS", "GR": "GRC", "GQ": "GNQ", "GP": "GLP", "JP": "JPN", "GY": "GUY", "GG": "GGY", "GF": "GUF", "GE": "GEO", "GD": "GRD", "GB": "GBR", "GA": "GAB", "SV": "SLV", "GN": "GIN", "GM": "GMB", "GL": "GRL", "GI": "GIB", "GH": "GHA", "OM": "OMN", "TN": "TUN", "JO": "JOR", "HR": "HRV", "HT": "HTI", "HU": "HUN", "HK": "HKG", "HN": "HND", "HM": "HMD", "VE": "VEN", "PR": "PRI", "PS": "PSE", "PW": "PLW", "PT": "PRT", "SJ": "SJM", "PY": "PRY", "IQ": "IRQ", "PA": "PAN", "PF": "PYF", "PG": "PNG", "PE": "PER", "PK": "PAK", "PH": "PHL", "PN": "PCN", "PL": "POL", "PM": "SPM", "ZM": "ZMB", "EH": "ESH", "EE": "EST", "EG": "EGY", "ZA": "ZAF", "EC": "ECU", "IT": "ITA", "VN": "VNM", "SB": "SLB", "ET": "ETH", "SO": "SOM", "ZW": "ZWE", "SA": "SAU", "ES": "ESP", "ER": "ERI", "ME": "MNE", "MD": "MDA", "MG": "MDG", "MF": "MAF", "MA": "MAR", "MC": "MCO", "UZ": "UZB", "MM": "MMR", "ML": "MLI", "MO": "MAC", "MN": "MNG", "MH": "MHL", "MK": "MKD", "MU": "MUS", "MT": "MLT", "MW": "MWI", "MV": "MDV", "MQ": "MTQ", "MP": "MNP", "MS": "MSR", "MR": "MRT", "IM": "IMN", "UG": "UGA", "TZ": "TZA", "MY": "MYS", "MX": "MEX", "IL": "ISR", "FR": "FRA", "IO": "IOT", "SH": "SHN", "FI": "FIN", "FJ": "FJI", "FK": "FLK", "FM": "FSM", "FO": "FRO", "NI": "NIC", "NL": "NLD", "NO": "NOR", "NA": "NAM", "VU": "VUT", "NC": "NCL", "NE": "NER", "NF": "NFK", "NG": "NGA", "NZ": "NZL", "NP": "NPL", "NR": "NRU", "NU": "NIU", "CK": "COK", "XK": "XKX", "CI": "CIV", "CH": "CHE", "CO": "COL", "CN": "CHN", "CM": "CMR", "CL": "CHL", "CC": "CCK", "CA": "CAN", "CG": "COG", "CF": "CAF", "CD": "COD", "CZ": "CZE", "CY": "CYP", "CX": "CXR", "CR": "CRI", "CW": "CUW", "CV": "CPV", "CU": "CUB", "SZ": "SWZ", "SY": "SYR", "SX": "SXM", "KG": "KGZ", "KE": "KEN", "SS": "SSD", "SR": "SUR", "KI": "KIR", "KH": "KHM", "KN": "KNA", "KM": "COM", "ST": "STP", "SK": "SVK", "KR": "KOR", "SI": "SVN", "KP": "PRK", "KW": "KWT", "SN": "SEN", "SM": "SMR", "SL": "SLE", "SC": "SYC", "KZ": "KAZ", "KY": "CYM", "SG": "SGP", "SE": "SWE", "SD": "SDN", "DO": "DOM", "DM": "DMA", "DJ": "DJI", "DK": "DNK", "VG": "VGB", "DE": "DEU", "YE": "YEM", "DZ": "DZA", "US": "USA", "UY": "URY", "YT": "MYT", "UM": "UMI", "LB": "LBN", "LC": "LCA", "LA": "LAO", "TV": "TUV", "TW": "TWN", "TT": "TTO", "TR": "TUR", "LK": "LKA", "LI": "LIE", "LV": "LVA", "TO": "TON", "LT": "LTU", "LU": "LUX", "LR": "LBR", "LS": "LSO", "TH": "THA", "TF": "ATF", "TG": "TGO", "TD": "TCD", "TC": "TCA", "LY": "LBY", "VA": "VAT", "VC": "VCT", "AE": "ARE", "AD": "AND", "AG": "ATG", "AF": "AFG", "AI": "AIA", "VI": "VIR", "IS": "ISL", "IR": "IRN", "AM": "ARM", "AL": "ALB", "AO": "AGO", "AQ": "ATA", "AS": "ASM", "AR": "ARG", "AU": "AUS", "AT": "AUT", "AW": "ABW", "IN": "IND", "AX": "ALA", "AZ": "AZE", "IE": "IRL", "ID": "IDN", "UA": "UKR", "QA": "QAT", "MZ": "MOZ"}';

	$cc = json_decode($jsonCC, true);

	return $cc[$ccode];
}

// Conditional Show hide checkout fields based on chosen payment methods
add_action( 'wp_footer', 'conditionally_show_hide_billing_custom_field' );
function conditionally_show_hide_billing_custom_field(){
    // Only on checkout page
	if ( is_checkout() && ! is_wc_endpoint_url() ) :
		global $woocommerce;
	$amount = $woocommerce->cart->total;
	?>
	<script>
		jQuery(function($){



			var checkout_form = $('form.checkout');

			checkout_form.on('checkout_place_order', function () {

				if ($('#confirm-order-flag').length == 0) {
					checkout_form.append('<input type="hidden" id="confirm-order-flag" name="confirm-order-flag" value="1">');
				}

				if(jQuery('input[type="radio"][name="payment_method"]:checked').val() == "german_market_purchase_on_account")
				{
					jQuery(".place-order").append('<div style="text-align:center; font-size:18px; color:gree; font-weight:bold;">Einen Augenblick Bitte</div>');
					if($("#my_datepicker").val() == "")
					{
						window.location.href = '<?php echo  wc_get_checkout_url().'?error=dob'?>';
						return false;

					}
					else
					{

						var consmr_name = jQuery('#billing_first_name').val() + ' ' + 
						jQuery('#billing_last_name').val();

						var consmr_firstName = jQuery('#billing_first_name').val();
						var consmr_gender = jQuery('#billing_gender').val();
						var consmr_contact_email = jQuery('#billing_email').val();
						var consmr_country = jQuery('#billing_country').val(); 
						var consmr_dateOfBirth = jQuery('#my_datepicker').val(); 
						var consmr_street = jQuery('#billing_address_1').val();
						var consmr_house = jQuery('#billing_address_2').val();
						var consmr_city = jQuery('#billing_city').val();
						var consmr_zip = jQuery('#billing_postcode').val();
						var consmr_orderValue = '<?php echo $amount; ?>';
						var clientdate_reference  = "Test_RCO_01";   

						jQuery.ajax({
							type : "post",
							dataType : "json",
							url : myAjax.ajaxurl,
							data : {
								action: "my_user_vote", 
								consmr_name:consmr_name, 
								consmr_firstName:consmr_firstName, 
								consmr_gender:consmr_gender,
								// consmr_gender:consmr_gender,
								consmr_contact_email:consmr_contact_email,
								consmr_country:consmr_country,
								consmr_dateOfBirth:consmr_dateOfBirth,
								consmr_street:consmr_street,
								consmr_house:consmr_house,
								consmr_city :consmr_city,
								consmr_zip :consmr_zip,
								consmr_orderValue :consmr_orderValue,
								clientdate_reference :clientdate_reference

							},
							success: function(response) {
								// console.log(data);
								if(response.type == "success") {
									return true;
								}
								else {
									window.location.href = '<?php echo  wc_get_checkout_url().'?error=invoice'?>';
									return false;
								}
							}
						}) 


						return false;
					}
				}


			});

		});
	</script>
	<?php
	if(isset($_REQUEST['error']) && ($_REQUEST['error'] == 'dob'))
	{
		wc_add_notice( __( 'Bitte wählen Sie das Geburtsdatum aus.' ), 'error' );
		?>
		<script type="text/javascript">
			var target = jQuery('html,body');
			if (target.length) {
				jQuery('html,body').animate({
					scrollTop: target.offset().top
				}, 800);
				
			}
		</script>
		<?php
		
	}if(isset($_REQUEST['error']) && ($_REQUEST['error'] == 'invoice'))
	{
		wc_add_notice( __( WCUFLS_PAY_INVOICE_ERROR_MSG ), 'error' );
		?>
		<script type="text/javascript">
			var target = jQuery('html,body');
			if (target.length) {
				jQuery('html,body').animate({
					scrollTop: target.offset().top
				}, 800);
				
			}
		</script>
		<?php
		
	}

endif;
}

add_action( 'init', 'my_script_enqueuer' );

function my_script_enqueuer() {
	wp_register_script( "my_voter_script", WCUFLS_URL.'/js/soap_request.js', array('jquery') );
	wp_localize_script( 'my_voter_script', 'myAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));        

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'my_voter_script' );

}


add_action("wp_ajax_my_user_vote", "my_user_vote");
add_action("wp_ajax_nopriv_my_user_vote", "my_user_vote");

function my_user_vote() {
	$consmr_name = $_REQUEST['consmr_name'];
	$consmr_firstName = $_REQUEST['consmr_firstName'];
		// $consmr_gender = 'MALE';//$dataVal['consmr_gender'];
	$consmr_gender = $_REQUEST['consmr_gender'];
	$consmr_contact_email = $_REQUEST['consmr_contact_email'];
	$consmr_country = $_REQUEST['consmr_country'];
	$consmr_country = getISO3CountryCodeByCode($consmr_country);
	$consmr_dateOfBirth = $_REQUEST['consmr_dateOfBirth'];
	$consmr_dateOfBirth = str_replace('-','',$consmr_dateOfBirth);
	$consmr_street = $_REQUEST['consmr_street'];
	$consmr_house = $_REQUEST['consmr_house'];
	$consmr_city = $_REQUEST['consmr_city'];
	$consmr_zip = $_REQUEST['consmr_zip'];
	$consmr_orderValue = $_REQUEST['consmr_orderValue'];
	$clientdate_reference = $_REQUEST['clientdate_reference'];

		//###### Process SOAP REQUEST ###########		

	$data = '<?xml version="1.0" encoding="utf-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" 
	xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"> 
	<SOAP-ENV:Header> 
	<messageContext xmlns="http://www.deltavista.com/dspone/ordercheck-if/V001"> 
	<credentials> 
	<user>YakSleep_xml_demo</user> 
	<password>f!Y6mM6euw</password> 
	</credentials> 
	<correlationID>DE-456321</correlationID> 
	</messageContext> 
	</SOAP-ENV:Header> 
	<SOAP-ENV:Body> 
	<orderCheckRequest xmlns="http://www.deltavista.com/dspone/ordercheck-if/V001"> 
	<product> 
	<name>CreditCheckConsumer</name> 
	<country>'.$consmr_country.'</country> 
	<proofOfInterest>ABK</proofOfInterest> 
	</product> 
	<searchedAddress> 
	<legalForm>PERSON</legalForm> 
	<address> 
	<name>'.$consmr_name.'</name> 
	<firstName>'.$consmr_firstName.'</firstName> 
	<gender>'.$consmr_gender.'</gender> 
	<dateOfBirth>'.$consmr_dateOfBirth.'</dateOfBirth> 
	<location> 
	<street>'.$consmr_street.'</street> 
	<house>'.$consmr_house.'</house> 
	<city>'.$consmr_city.'</city> 
	<zip>'.$consmr_zip.'</zip> 
	<country>'.$consmr_country.'</country> 
	</location> 
	</address> 
	<contact> 
	<item>email</item> 
	<value>'.$consmr_contact_email.'</value> 
	</contact> 
	</searchedAddress> 
	<clientData> 
	<reference>'.$clientdate_reference.'</reference> 
	<order> 
	<orderValue>'.$consmr_orderValue.'</orderValue> 
	</order> 
	</clientData> 
	</orderCheckRequest> 
	</SOAP-ENV:Body> 
	</SOAP-ENV:Envelope>';


	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://demo-ordercheck.deltavista.de/soap");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERPWD, "YakSleep_xml_demo:f!Y6mM6euw");
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=utf-8", "Content-Length: " . strlen($data)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

	$output = curl_exec($ch);
	curl_close($ch);

	$cleanxml = str_ireplace(['soapenv:', 'soap:'], '', $output);
	$cleanxml = str_ireplace('ns1:','', $cleanxml);
	$xml = simplexml_load_string($cleanxml);
	$archiveID = isset($xml->Body->orderCheckResponse->archiveID)?$xml->Body->orderCheckResponse->archiveID:"";
	$decision = isset($xml->Body->orderCheckResponse->myDecision->decision)?$xml->Body->orderCheckResponse->myDecision->decision: "";
	$error_code = isset($xml->Body->Fault->detail->error->code)?$xml->Body->Fault->detail->error->code:"";
	$error_msg =  isset($xml->Body->Fault->detail->error->messageText)?$xml->Body->Fault->detail->error->messageText:""; 
	$error_faultstring =  isset($xml->Body->Fault->faultstring)?$xml->Body->Fault->faultstring:"";  		
		//##### END SOAP REQUEST ################	

	$errMsg = '';
	$errFlag = false;
	if($error_code > 0) {
		$errMsg .= $error_code;
		$errFlag = true;
	} 
	else if(!empty($error_msg)) {
		$errMsg .= ' '.$error_msg;
		$errFlag = true;
	}
	else if(!empty($error_faultstring)) {
		$errMsg .= ' '.$error_faultstring;
		$errFlag = true;
	}

	if($archiveID > 0 && (current($decision) !=""))
	{
		if(current($decision)=='YELLOW' || current($decision)=='RED')
		{
			setcookie( 'crif_usr_'.get_current_user_id(), 'true', time() + (7 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );	
			$result['type'] = "error";
			$result['mes'] = WCUFLS_PAY_INVOICE_ERROR_MSG;
		}
		else
		{
			$result['type'] = "success";
			$result['mes'] = "success";
		}
	}
	else
	{
		$result['type'] = "success";
		$result['mes'] = "success";
	}

	$result = json_encode($result);
	echo $result;
	die();

}



?>