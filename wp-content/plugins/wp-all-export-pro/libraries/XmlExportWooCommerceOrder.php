<?php

if ( ! class_exists('XmlExportWooCommerceOrder') ){

	final class XmlExportWooCommerceOrder
	{
		/**
		 * Singletone instance
		 * @var XmlExportWooCommerceOrder
		 */
		protected static $instance;

		/**
		 * Return singletone instance
		 * @return XmlExportWooCommerceOrder
		 */
		static public function getInstance() {
			if (self::$instance == NULL) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public static $is_active = true;

		public static $order_sections = array();		
		public static $order_items_per_line = false;
		public static $orders_data = null;
		public static $exportQuery = null;

		private $init_fields = array(			
			array(
				'name'    => 'Order ID',
				'type'    => 'woo_order',
				'options' => 'order',
				'label'   => 'ID'
			),
			array(
				'name'    => 'Order Key',
				'type'    => 'woo_order',
				'options' => 'order',
				'label'   => '_order_key'
			),			
			array(
				'name'    => 'Title',
				'type'    => 'woo_order',
				'options' => 'order',
				'label'   => 'post_title'
			)			
		);

		private $filter_sections = array();		

		private function __construct()
		{			

			if ( ! class_exists('WooCommerce') 
					or ( XmlExportEngine::$exportOptions['export_type'] == 'specific' and ! in_array('shop_order', XmlExportEngine::$post_types) ) 
						or ( XmlExportEngine::$exportOptions['export_type'] == 'advanced' and strpos(XmlExportEngine::$exportOptions['wp_query'], 'shop_order') === false ) ) {
				self::$is_active = false;
				return;			
			}						
			$this->filter_sections = array(
				'general' => array(
					'title'  => __("Order", "wp_all_export_plugin"),
					'fields' => array(
						'ID' 						=> __('Order ID', 'wp_all_export_plugin'),
						'cf__order_key'				=> __('Order Key', 'wp_all_export_plugin'),				
						'post_date' 				=> __('Order Date', 'wp_all_export_plugin'),
						'cf__completed_date' 		=> __('Completed Date', 'wp_all_export_plugin'),
						'post_title' 				=> __('Title', 'wp_all_export_plugin'),
						'post_status' 				=> __('Order Status', 'wp_all_export_plugin'),
						'cf__order_currency' 		=> __('Order Currency', 'wp_all_export_plugin'),						
						'cf__payment_method_title' 	=> __('Payment Method', 'wp_all_export_plugin'),
						'cf__order_total' 			=> __('Order Total', 'wp_all_export_plugin')
					)
				),
				'customer' => array(
					'title'  => __("Customer", "wp_all_export_plugin"),
					'fields' => array()											
				)				
			);

			foreach ($this->available_customer_data() as $key => $value) {
				$this->filter_sections['customer']['fields'][($key == 'post_excerpt') ? $key : 'cf_' . $key] = $value;
			}

			if ( empty(PMXE_Plugin::$session) ) // if cron execution
			{
				$id = $_GET['export_id'];
				$export = new PMXE_Export_Record();
				$export->getById($id);	
				if ( ! $export->isEmpty() and $export->options['export_to'] == 'csv'){	
					$this->init_additional_data();
				}
			} 
			else
			{
				self::$orders_data = PMXE_Plugin::$session->get('orders_data');								
			}

			add_filter("wp_all_export_available_sections", 			array( &$this, "filter_available_sections" ), 10, 1);
			add_filter("wp_all_export_available_filter_sections", 	array( &$this, "filter_available_filter_sections" ), 10, 1);
			add_filter("wp_all_export_csv_rows", 					array( &$this, "filter_csv_rows"), 10, 2);
			add_filter("wp_all_export_init_fields", 				array( &$this, "filter_init_fields"), 10, 1);
			add_filter("wp_all_export_filters", 					array( &$this, "filter_export_filters"), 10, 1);

			self::$order_sections = $this->available_sections();

		}

		// [FILTERS]

			/**
			*
			* Filter data for advanced filtering
			*
			*/
			public function filter_export_filters($filters){
				return $this->filter_sections;
			}

			/**
			*
			* Filter sections for advanced filtering
			*
			*/
			public function filter_available_filter_sections($sections){
				unset($sections['cats']);
				$sections['cf']['title'] = __('Advanced', 'wp_all_export_plugin');			
				return $sections;
			}	

			/**
			*
			* Filter Init Fields
			*
			*/
			public function filter_init_fields($init_fields){
				return $this->init_fields;
			}

			/**
			*
			* Filter Sections in Available Data
			*
			*/
			public function filter_available_sections($sections){						
				return array();
			}								

		// [\FILTERS]

		public function init( & $existing_meta_keys = array() ){

			if ( ! self::$is_active ) return;	

			if ( ! empty($existing_meta_keys) )
			{
				foreach (self::$order_sections as $slug => $section) :
					
					foreach ($section['meta'] as $cur_meta_key => $cur_meta_label) 
					{	
						foreach ($existing_meta_keys as $key => $record_meta_key) 
						{
							if ( $record_meta_key == $cur_meta_key )
							{
								unset($existing_meta_keys[$key]);
								break;
							}
						}									
					}

				endforeach;		

				foreach ($existing_meta_keys as $key => $record_meta_key) 
				{							
					self::$order_sections['cf']['meta'][$record_meta_key] = array(
						'name' => $record_meta_key,
						'label' => $record_meta_key,
						'options' => '',
						'type' => 'cf'
					);
				}
			}				

			global $wpdb;
			$table_prefix = $wpdb->prefix;

			$product_data = $this->available_order_default_product_data();

			$meta_keys = $wpdb->get_results("SELECT DISTINCT {$table_prefix}woocommerce_order_itemmeta.meta_key FROM {$table_prefix}woocommerce_order_itemmeta");
			if ( ! empty($meta_keys)){
				foreach ($meta_keys as $meta_key) {
					if (strpos($meta_key->meta_key, "pa_") !== 0 and empty(self::$order_sections['cf']['meta'][$meta_key->meta_key]) and empty($product_data[$meta_key->meta_key])) 
						self::$order_sections['cf']['meta'][$meta_key->meta_key] = array(
							'name' => $meta_key->meta_key,
							'label' => $meta_key->meta_key,
							'options' => 'items',
							'type' => 'woo_order'
						);
				}
			}	

		}

		public function init_additional_data(){

			if ( ! self::$is_active ) return;								

			if ( empty(self::$orders_data) or 'PMXE_Admin_Manage' == PMXE_Plugin::getInstance()->getAdminCurrentScreen()->base ){
				
				$in_orders = preg_replace("%(SQL_CALC_FOUND_ROWS|LIMIT.*)%", "", XmlExportEngine::$exportQuery->request);

				self::$orders_data = array();				

				global $wpdb;

				$table_prefix = $wpdb->prefix;	

				self::$orders_data['line_items_max_count'] = $wpdb->get_var($wpdb->prepare("SELECT max(cnt) as line_items_count FROM ( 
					SELECT order_id, COUNT(*) as cnt FROM {$table_prefix}woocommerce_order_items 
						WHERE {$table_prefix}woocommerce_order_items.order_item_type = %s AND {$table_prefix}woocommerce_order_items.order_id IN (". $in_orders .") GROUP BY order_id) AS T3", 'line_item'));

				self::$orders_data['taxes'] = $wpdb->get_results($wpdb->prepare("SELECT order_item_id, order_id, order_item_name FROM {$table_prefix}woocommerce_order_items 
						WHERE {$table_prefix}woocommerce_order_items.order_item_type = %s AND {$table_prefix}woocommerce_order_items.order_id IN (". $in_orders .") GROUP BY order_item_name", 'tax'));

				self::$orders_data['coupons'] = $wpdb->get_results($wpdb->prepare("SELECT order_item_id, order_id, order_item_name FROM {$table_prefix}woocommerce_order_items 
						WHERE {$table_prefix}woocommerce_order_items.order_item_type = %s AND {$table_prefix}woocommerce_order_items.order_id IN (". $in_orders .") GROUP BY order_item_name", 'coupon'));

				self::$orders_data['fees'] = $wpdb->get_results($wpdb->prepare("SELECT order_item_id, order_id, order_item_name FROM {$table_prefix}woocommerce_order_items 
						WHERE {$table_prefix}woocommerce_order_items.order_item_type = %s AND {$table_prefix}woocommerce_order_items.order_id IN (". $in_orders .") GROUP BY order_item_name", 'fee'));

				self::$orders_data['variations'] = $wpdb->get_results($wpdb->prepare("SELECT meta_key FROM {$table_prefix}woocommerce_order_itemmeta 
						WHERE {$table_prefix}woocommerce_order_itemmeta.meta_key LIKE %s AND {$table_prefix}woocommerce_order_itemmeta.order_item_id IN (
							SELECT {$table_prefix}woocommerce_order_items.order_item_id FROM {$table_prefix}woocommerce_order_items 
							WHERE {$table_prefix}woocommerce_order_items.order_item_type = %s AND {$table_prefix}woocommerce_order_items.order_id IN (". $in_orders .") ) GROUP BY meta_key", 'pa_%', 'line_item'));	

				if ( ! empty(PMXE_Plugin::$session) )
				{ 
					PMXE_Plugin::$session->set('orders_data', self::$orders_data);
					PMXE_Plugin::$session->save_data();
				}
			}

		}

		private $order_items  		= null;
		private $order_taxes  		= null;
		private $order_shipping 	= null;
		private $order_coupons 		= null;
		private $order_surcharge 	= null;
		private $__total_fee_amount = null;	
		private $__coupons_used     = null;
		private $order_id 			= null;

		protected function prepare_export_data( $record, $options, $elId ){						

			// an array with data to export
			$data = array(
				'items' => array(),
				'taxes' => array(),
				'shipping'  => array(),
				'coupons'   => array(),
				'surcharge' => array()
			); 

			global $wpdb;
			$table_prefix = $wpdb->prefix;					
		
			if ( empty($this->order_id) or $this->order_id != $record->ID)
			{
				$this->order_id   = $record->ID;

				$all_order_items  = $wpdb->get_results("SELECT * FROM {$table_prefix}woocommerce_order_items WHERE order_id = {$record->ID}");

				if ( ! empty($all_order_items) )
				{
					foreach ($all_order_items as $item) 
					{
						switch ($item->order_item_type) 
						{
							case 'line_item':
								$this->order_items[] = $item;
								break;
							case 'tax':
								$this->order_taxes[] = $item;
								break;
							case 'shipping':
								$this->order_shipping[] = $item;
								break;
							case 'coupon':
								$this->order_coupons[] = $item;
								break;
							case 'fee':
								$this->order_surcharge[] = $item;
								break;							
						}
					}
				}				
			}

			if ( ! empty($options['cc_value'][$elId]) ){					

				$fieldSnipped = ( ! empty($options['cc_php'][$elId]) and ! empty($options['cc_code'][$elId]) ) ? $options['cc_code'][$elId] : false;

				switch ($options['cc_options'][$elId]) {
					
					case 'order':
					case 'customer':					
						
						$data[$options['cc_name'][$elId]] = ( strpos($options['cc_value'][$elId], "_") === 0 ) ? get_post_meta($record->ID, $options['cc_value'][$elId], true) : $record->$options['cc_value'][$elId];						

						if ($options['cc_value'][$elId] == "post_title")
						{							
							$data[$options['cc_name'][$elId]] = str_replace("&ndash;", '-', $data[$options['cc_name'][$elId]]);
						}

						$data[$options['cc_name'][$elId]] = pmxe_filter( $data[$options['cc_name'][$elId]], $fieldSnipped);	

						break;

					case 'items':

						if ( ! empty($this->order_items)){
							
							foreach ($this->order_items as $n => $order_item) {							
									
								$meta_data = $wpdb->get_results("SELECT * FROM {$table_prefix}woocommerce_order_itemmeta WHERE order_item_id = {$order_item->order_item_id}", ARRAY_A);

								$item_data = array();

								foreach ($options['ids'] as $subID => $subvalue) {

									if ( $options['cc_type'][$subID] == 'woo_order' and $options['cc_options'][$subID] == 'items') {
										
										$element_name = $options['cc_name'][$subID] . ' #' . ($n + 1);
										$fieldSnipped = ( ! empty($options['cc_php'][$subID]) and ! empty($options['cc_code'][$subID]) ) ? $options['cc_code'][$subID] : false;																				

										switch ($options['cc_value'][$subID]) {
											
											case '_product_id':
											case '__product_sku':
											case '__product_title':
											
												$product_id   = '';
												$variation_id = '';
												foreach ($meta_data as $meta) {
													if ($meta['meta_key'] == '_variation_id' and ! empty($meta['meta_value'])){
														$variation_id = $meta['meta_value'];																
													}
													if ($meta['meta_key'] == '_product_id' and ! empty($meta['meta_value'])){
														$product_id = $meta['meta_value'];																
													}
												}

												$_product_id = empty($variation_id) ? $product_id : $variation_id;

												switch ($options['cc_value'][$subID]) {

													case '_product_id':																											

														$item_data[$element_name] = pmxe_filter( $_product_id, $fieldSnipped);														

														break;

													case '__product_sku':														

														$item_data[$element_name] = pmxe_filter( get_post_meta($_product_id, '_sku', true), $fieldSnipped);														

														break;

													case '__product_title':														
														
														$_product = get_post($_product_id);

														$item_data[$element_name] = ( ! is_null($_product)) ? pmxe_filter( $_product->post_title, $fieldSnipped) : '';
														
														break;

												}

												break;

											case '__product_variation':												

												$variations = array();

												foreach ($meta_data as $meta) {
													if ( strpos($meta['meta_key'], "pa_") === 0 ){
														$variations[str_replace("pa_", "", $meta['meta_key'])] = $meta['meta_value'];
													}
												}

												if ( ! empty($variations) ){													
													foreach ($variations as $key => $value) {														
														$item_data[$element_name . " (" . sanitize_title($key) . ")"] = pmxe_filter( $value, $fieldSnipped);
													}													
												}												

												break;

											case '_line_subtotal':

												$_line_total = 0;
												$_qty = 0;
												foreach ($meta_data as $meta) {
													if ($meta['meta_key'] == '_line_total'){
														$_line_total = $meta['meta_value'];
													}
													if ($meta['meta_key'] == '_qty'){
														$_qty = $meta['meta_value'];
													}
												}

												$_line_subtotal = ($_qty) ? number_format($_line_total/$_qty, 2) : 0;

												$item_data[$element_name] = pmxe_filter( $_line_subtotal, $fieldSnipped);

												break;

											default:
												$meta_key_founded = false;
												foreach ($meta_data as $meta) {													
													if ($meta['meta_key'] == $options['cc_value'][$subID]){																
														$item_data[$element_name] = pmxe_filter( $meta['meta_value'], $fieldSnipped);															
														$meta_key_founded = true;
													}
												}
												if ( ! $meta_key_founded ) $item_data[$element_name] = pmxe_filter( '', $fieldSnipped);

												break;
										}										
									}									
								}	
								if ( ! empty($item_data)) $data['items'][] = $item_data;							
							}								

							$this->order_items = null;
						}

						break;

					case 'taxes':

						// Order Taxes
						if ( ! empty($this->order_taxes) ){

							foreach ($this->order_taxes as $order_tax) {															

								$meta_data = $wpdb->get_results("SELECT * FROM {$table_prefix}woocommerce_order_itemmeta WHERE order_item_id = {$order_tax->order_item_id}", ARRAY_A);
								$rate_details = null;
								foreach ($meta_data as $meta) {
									if ($meta['meta_key'] == 'rate_id'){
										$rate_id = $meta['meta_value'];														
										$rate_details = $wpdb->get_row("SELECT * FROM {$table_prefix}woocommerce_tax_rates WHERE tax_rate_id = {$rate_id}");																																	
										break;
									}	
								}

								$tax_data = array();
								
								foreach ($options['ids'] as $subID => $subvalue) {

									if ( $options['cc_type'][$subID] == 'woo_order' and $options['cc_options'][$subID] == 'taxes') {

										$element_name = str_replace("per tax", $rate_details->tax_rate_name, $options['cc_name'][$subID]);
										$fieldSnipped = ( ! empty($options['cc_php'][$subID]) and ! empty($options['cc_code'][$subID]) ) ? $options['cc_code'][$subID] : false;																				

										switch ($options['cc_value'][$subID]) {

											case 'tax_order_item_name':												
												$tax_data[$element_name] = pmxe_filter( $order_tax->order_item_name, $fieldSnipped);															
												break;
											case 'tax_rate':

												$tax_data[$element_name] = pmxe_filter(( ! empty($rate_details)) ? $rate_details->tax_rate : '', $fieldSnipped);
												
												break;
											case 'tax_amount':
												$tax_amount = 0;
												foreach ($meta_data as $meta) {
													if ($meta['meta_key'] == 'tax_amount' || $meta['meta_key'] == 'shipping_tax_amount'){
														$tax_amount += $meta['meta_value'];
													}
												}
												
												$tax_data[$element_name] = pmxe_filter( $tax_amount, $fieldSnipped);			
												
												break;
																						
										}										
									}	
								}
								if ( ! empty($tax_data) ) $data['taxes'][] = $tax_data;
							}								

							$this->order_taxes = null;
						}

						// Calculate Total Tax Amount
						if ( $options['cc_value'][$elId] == '_order_tax' ){
						
							$_order_shipping_tax = get_post_meta($record->ID, '_order_shipping_tax', true);
							$_order_tax 		 = get_post_meta($record->ID, '_order_tax', true);
							$_order_tax_total    = $_order_shipping_tax + $_order_tax;												
							
							$data[$options['cc_name'][$elId]] = pmxe_filter( $_order_tax_total, $fieldSnipped);			
							
						}

						if ( ! empty($this->order_shipping) ){

							foreach ($this->order_shipping as $order_ship) {							

								$shipping_data = array();

								foreach ($options['ids'] as $subID => $subvalue) {

									if ( $options['cc_type'][$subID] == 'woo_order' and $options['cc_options'][$subID] == 'taxes') {

										$element_name = $options['cc_name'][$subID];
										$fieldSnipped = ( ! empty($options['cc_php'][$subID]) and ! empty($options['cc_code'][$subID]) ) ? $options['cc_code'][$subID] : false;																			

										switch ($options['cc_value'][$subID]) {
											case 'shipping_order_item_name':													
													$shipping_data[$element_name] = pmxe_filter( $order_ship->order_item_name, $fieldSnipped);													
												break;
											case '_order_shipping':													
													$shipping_data[$element_name] = pmxe_filter( get_post_meta($record->ID, '_order_shipping', true), $fieldSnipped);													
												break;
										}										
									}
								}		
								if ( ! empty($shipping_data) ) $data['shipping'][] = $shipping_data;																						
							}

							$this->order_shipping = null;
						}							

						break;
					case 'fees':

						if ( ! empty($this->order_coupons) ){				

							$this->__coupons_used = array();			
								
							foreach ($this->order_coupons as $order_coupon) {		

								$this->__coupons_used[] = $order_coupon->order_item_name;						

								$meta_data = $wpdb->get_results("SELECT * FROM {$table_prefix}woocommerce_order_itemmeta WHERE order_item_id = {$order_coupon->order_item_id}", ARRAY_A);

								$coupons_data = array();

								foreach ($options['ids'] as $subID => $subvalue) {

									if ( $options['cc_type'][$subID] == 'woo_order' and $options['cc_options'][$subID] == 'fees') {
										
										$element_name = str_replace("per coupon", $order_coupon->order_item_name, $options['cc_name'][$subID]);
										$fieldSnipped = ( ! empty($options['cc_php'][$subID]) and ! empty($options['cc_code'][$subID]) ) ? $options['cc_code'][$subID] : false;																				

										switch ($options['cc_value'][$subID]) {
											case 'discount_amount':
												foreach ($meta_data as $meta) {
													if ($meta['meta_key'] == 'discount_amount'){															
														$coupons_data[$element_name] = pmxe_filter( $meta['meta_value'] * (-1), $fieldSnipped);														
														break;
													}	
												}
												break;																																
										}										
									}
								}		
								if ( ! empty($coupons_data) ) $data['coupons'][] = $coupons_data;					
							}							
							$this->order_coupons = null;
						}

						// List of all coupons used
						if ( $options['cc_value'][$elId] == '__coupons_used' and ! is_null($this->__coupons_used)){
							$implode_delimiter = ($options['delimiter'] == ',') ? '|' : ',';	
							$data[$options['cc_name'][$elId]] = pmxe_filter(implode($implode_delimiter, $this->__coupons_used) , $fieldSnipped);
						}

						// Calculate Total Discount Amount
						if ( $options['cc_value'][$elId] == '_cart_discount' ){
							$_cart_discount = get_post_meta($record->ID, '_cart_discount', true);							
							$data[$options['cc_name'][$elId]] = pmxe_filter( $_cart_discount * (-1), $fieldSnipped);																
						}

						if ( ! empty($this->order_surcharge) ){													
							
							$this->__total_fee_amount = 0;

							foreach ($this->order_surcharge as $order_surcharge) {									

								$meta_data = $wpdb->get_results("SELECT * FROM {$table_prefix}woocommerce_order_itemmeta WHERE order_item_id = {$order_surcharge->order_item_id}", ARRAY_A);

								$surcharge_data = array();

								foreach ($options['ids'] as $subID => $subvalue) {

									if ( $options['cc_type'][$subID] == 'woo_order' and $options['cc_options'][$subID] == 'fees') {
										
										$element_name = str_replace("Amount (per surcharge)", "(".$order_surcharge->order_item_name.")", $options['cc_name'][$subID]);
										$fieldSnipped = ( ! empty($options['cc_php'][$subID]) and ! empty($options['cc_code'][$subID]) ) ? $options['cc_code'][$subID] : false;																				

										switch ($options['cc_value'][$subID]) {																					
											
											case 'fee_line_total':

												foreach ($meta_data as $meta) {
													if ($meta['meta_key'] == '_line_total'){															
														$surcharge_data[$element_name] = pmxe_filter( $meta['meta_value'], $fieldSnipped);
														$this->__total_fee_amount += $meta['meta_value'];
														break;
													}	
												}

												break;											
										}										
									}
								}		
								if ( ! empty($surcharge_data) ) $data['surcharge'][] = $surcharge_data;					
							}														

							$this->order_surcharge = null;
						}								

						// Total Fee Amount
						if ( $options['cc_value'][$elId] == '__total_fee_amount' and ! is_null($this->__total_fee_amount)){
							$data[$options['cc_name'][$elId]] = $this->__total_fee_amount;
						}				

						break;
				}

			}

			return $data;
		}

		private $additional_articles = array();

		public function export_csv( & $article, & $titles, $record, $options, $elId ){		

			if ( ! self::$is_active ) return;		

			$data_to_export = $this->prepare_export_data( $record, $options, $elId );

			foreach ($data_to_export as $key => $data) {
				
				if ( in_array($key, array('items', 'taxes', 'shipping', 'coupons', 'surcharge')) )
				{					
					if ( ! empty($data))
					{						
						if ( $key == 'items' and $options['order_item_per_row'])
						{							
							foreach ($data as $item) {			
								$additional_article = array();											
								if ( ! empty($item) ){									
									foreach ($item as $item_key => $item_value) {
										$final_key = preg_replace("%\s#\d%", "", $item_key);
										$additional_article[$final_key] = $item_value;										
										if ( ! in_array($final_key, $titles) ) $titles[] = $final_key;
									}									
								}															
								if ( ! empty($additional_article) )
								{ 
									if ( empty($this->additional_articles) )
									{
										foreach ($additional_article as $item_key => $item_value) {
											$article[$item_key] = $item_value;
										}
									}								
									$this->additional_articles[] = $additional_article;								
								}
							}							
						}	
						else
						{	
							foreach ($data as $item) {														
								if ( ! empty($item)){
									foreach ($item as $item_key => $item_value) {
										$article[$item_key] = $item_value;																				
										if ( ! in_array($item_key, $titles) ) $titles[] = $item_key;
									}									
								}							
							}							
						}						
					}
				}
				else
				{										
					$article[$key] = $data;
					if ( ! in_array($key, $titles) ) $titles[] = $key;
				}
			}
		}

		public function filter_csv_rows($articles, $options){

			if ( ! empty($this->additional_articles) and $options['order_item_per_row'] and $options['export_to'] == 'csv')
			{
				array_shift($this->additional_articles);								
				if ( ! empty($this->additional_articles ) ){
					foreach ($this->additional_articles as $article) {
						$articles[] = $article;
					}
					$this->additional_articles = array();
				}				
			}			

			return $articles;
		}

		public function get_element_header( & $headers, $options, $element_key ){

			switch ($options['cc_value'][$element_key]) 
			{
				// Rate Code (per tax)
				case 'tax_order_item_name':
				// Rate Percentage (per tax)	
				case 'tax_rate':
				// Amount (per tax)	
				case 'tax_amount':

					if ( ! empty(self::$orders_data['taxes']))
					{
						foreach ( self::$orders_data['taxes'] as $tax) {
							$friendly_name = str_replace("per tax", $this->get_rate_friendly_name($tax->order_item_id), $options['cc_name'][$element_key]);							
							if ( ! in_array($friendly_name, $headers)) $headers[] = $friendly_name;
						}
					}

					break;
				// Discount Amount (per coupon)
				case 'discount_amount':

					if ( ! empty(self::$orders_data['coupons']))
					{
						foreach ( self::$orders_data['coupons'] as $coupon) {
							$friendly_name = str_replace("per coupon", $coupon->order_item_name, $options['cc_name'][$element_key]);
							if ( ! in_array($friendly_name, $headers)) $headers[] = $friendly_name;
						}
					}

					break;
				// Fee Amount (per surcharge)	
				case 'fee_line_total':
					
					if ( ! empty(self::$orders_data['fees']))
					{
						foreach ( self::$orders_data['fees'] as $fee) {
							$friendly_name = str_replace("Amount (per surcharge)", "(" . $fee->order_item_name . ")", $options['cc_name'][$element_key]);
							if ( ! in_array($friendly_name, $headers)) $headers[] = $friendly_name;
						}
					}

					break;	

				// Product Variation Details	
				case '__product_variation':
					
					if ( ! empty(self::$orders_data['line_items_max_count']) and ! empty(self::$orders_data['variations']))
					{
						if ($options['order_item_per_row']){
							foreach ( self::$orders_data['variations'] as $variation) {																
								$friendly_name = $options['cc_name'][$element_key] . " (" . sanitize_title(str_replace("pa_", "", $variation->meta_key)) . ")";									
								if ( ! in_array($friendly_name, $headers)) $headers[] = $friendly_name;
							}
						}
						else{							
							for ($i = 1; $i <= self::$orders_data['line_items_max_count']; $i++){
								foreach ( self::$orders_data['variations'] as $variation) {																
									$friendly_name = $options['cc_name'][$element_key] . " #" . $i . " (" . sanitize_title(str_replace("pa_", "", $variation->meta_key)) . ")";									
									if ( ! in_array($friendly_name, $headers)) $headers[] = $friendly_name;
								}
							}
						}
					}

					break;

				default:

					if ( $options['cc_options'][$element_key] == 'items')
					{
						if ($options['order_item_per_row']){
							if ( ! in_array($options['cc_name'][$element_key], $headers)) $headers[] = $options['cc_name'][$element_key];
						}
						else
						{
							if ( ! empty(self::$orders_data['line_items_max_count'])){
								for ($i = 1; $i <= self::$orders_data['line_items_max_count']; $i++){
									$friendly_name = $options['cc_name'][$element_key] . " #" . $i;
									if ( ! in_array($friendly_name, $headers)) $headers[] = $friendly_name;
								}
							}
						}
					}
					else
					{
						if ( ! in_array($options['cc_name'][$element_key], $headers)) $headers[] = $options['cc_name'][$element_key];
					}

					break;

			}

		}

		public function get_rate_friendly_name( $order_item_id ){

			global $wpdb;			
			$table_prefix = $wpdb->prefix;		

			$rate_details = null;
			$meta_data = $wpdb->get_results("SELECT * FROM {$table_prefix}woocommerce_order_itemmeta WHERE order_item_id = {$order_item_id}", ARRAY_A);			
			foreach ($meta_data as $meta) {
				if ($meta['meta_key'] == 'rate_id'){
					$rate_id = $meta['meta_value'];														
					$rate_details = $wpdb->get_row("SELECT * FROM {$table_prefix}woocommerce_tax_rates WHERE tax_rate_id = {$rate_id}");																																	
					break;
				}	
			}

			return $rate_details ? $rate_details->tax_rate_name : '';

		}

		public function export_xml( & $xmlWriter, $record, $options, $elId ){

			if ( ! self::$is_active ) return;	

			$data_to_export = $this->prepare_export_data( $record, $options, $elId );

			foreach ($data_to_export as $key => $data) {
				
				if ( in_array($key, array('items', 'taxes', 'shipping', 'coupons', 'surcharge')) )
				{
					if ( ! empty($data)){						
						$xmlWriter->startElement('order_' . $key);															
							foreach ($data as $item) {															
								if ( ! empty($item)){
									$xmlWriter->startElement(preg_replace("%(s|es)$%", "", $key));							
									foreach ($item as $item_key => $item_value) {
										$xmlWriter->writeElement(str_replace("-", "_", sanitize_title(preg_replace("%#\d%", "", $item_key))), $item_value);
									}
									$xmlWriter->endElement();
								}														
							}							
						$xmlWriter->endElement();
					}
				}
				else
				{			
					$xmlWriter->writeElement(str_replace("-", "_", sanitize_title($key)), $data);							
				}				
			}				
		}

		public function render( & $i ){			
				
			if ( ! self::$is_active ) return;

			foreach (self::$order_sections as $slug => $section) :
				?>										
				<p class="wpae-available-fields-group"><?php echo $section['title']; ?><span class="wpae-expander">+</span></p>
				<div class="wpae-custom-field">
					<ul>
						<li>
							<div class="default_column" rel="">								
								<label class="wpallexport-element-label"><?php echo __("All", "wp_all_export_plugin") . ' ' . $section['title'] . ' ' . __("Data", "wp_all_export_plugin"); ?></label>
								<input type="hidden" name="rules[]" value="pmxe_<?php echo $slug;?>"/>
							</div>
						</li>
						<?php
						foreach ($section['meta'] as $cur_meta_key => $field) {									
							?>
							<li class="pmxe_<?php echo $slug; ?>">
								<div class="custom_column" rel="<?php echo ($i + 1);?>">
									<label class="wpallexport-xml-element">&lt;<?php echo (is_array($field)) ? $field['name'] : $field; ?>&gt;</label>
									<input type="hidden" name="ids[]" value="1"/>
									<input type="hidden" name="cc_label[]" value="<?php echo (is_array($field)) ? $field['label'] : $cur_meta_key; ?>"/>										
									<input type="hidden" name="cc_php[]" value=""/>										
									<input type="hidden" name="cc_code[]" value=""/>
									<input type="hidden" name="cc_sql[]" value=""/>
									<input type="hidden" name="cc_options[]" value="<?php echo (is_array($field)) ? $field['options'] : $slug;?>"/>										
									<input type="hidden" name="cc_type[]" value="<?php echo (is_array($field)) ? $field['type'] : 'woo_order'; ?>"/>
									<input type="hidden" name="cc_value[]" value="<?php echo (is_array($field)) ? $field['label'] : $cur_meta_key; ?>"/>
									<input type="hidden" name="cc_name[]" value="<?php echo (is_array($field)) ? $field['name'] : $field;?>"/>
								</div>
							</li>
							<?php
							$i++;												
						}																		
						?>
					</ul>
				</div>									
				<?php
			endforeach;
		}

		public function render_filters(){
			
		}

		public function available_sections(){

			$sections = array(
				'order'    => array(
					'title' => __('Order', 'wp_all_export_plugin'),
					'meta'  => $this->available_order_data()
				),
				'customer' => array(
					'title' => __('Customer', 'wp_all_export_plugin'),
					'meta'  => $this->available_customer_data()
				),
				'items'    => array(
					'title' => __('Items', 'wp_all_export_plugin'),
					'meta'  => $this->available_order_default_product_data()
				),
				'taxes'    => array(
					'title' => __('Taxes & Shipping', 'wp_all_export_plugin'),
					'meta'  => $this->available_order_taxes_data()
				),
				'fees'     => array(
					'title' => __('Fees & Discounts', 'wp_all_export_plugin'),
					'meta'  => $this->available_order_fees_data()
				),
				'cf'       => array(
					'title' => __('Advanced', 'wp_all_export_plugin'),
					'meta'  => array()
				),
			);

			return apply_filters('wp_all_export_available_order_sections_filter', $sections);

		}

		/*
		 * Define the keys for orders informations to export
		 */
		public function available_order_data()
		{
			$data = array(			   
				'ID' 					=> __('Order ID', 'wp_all_export_plugin'),
				'_order_key' 			=> __('Order Key', 'wp_all_export_plugin'),				
				'post_date' 			=> __('Order Date', 'wp_all_export_plugin'),
				'_completed_date' 		=> __('Completed Date', 'wp_all_export_plugin'),
				'post_title' 			=> __('Title', 'wp_all_export_plugin'),
				'post_status' 			=> __('Order Status', 'wp_all_export_plugin'),
				'_order_currency' 		=> __('Order Currency', 'wp_all_export_plugin'),				
				'_payment_method_title' => __('Payment Method', 'wp_all_export_plugin'),
				'_order_total' 			=> __('Order Total', 'wp_all_export_plugin')
			);
				
			return apply_filters('wp_all_export_available_order_data_filter', $data);
		}

		/*
		 * Define the keys for general product informations to export
		 */
		public function available_order_default_product_data()
		{			

			$data = array(
				'_product_id'  			=> __('Product ID', 'wp_all_export_plugin'),
				'__product_sku' 		=> __('SKU', 'wp_all_export_plugin'),
				'__product_title' 		=> __('Product Name', 'wp_all_export_plugin'),
				'__product_variation' 	=> __('Product Variation Details', 'wp_all_export_plugin'),
				'_qty' 					=> __('Quantity', 'wp_all_export_plugin'),
				'_line_subtotal' 		=> __('Item Cost', 'wp_all_export_plugin'),
				'_line_total' 			=> __('Item Total', 'wp_all_export_plugin')
			);			

			return apply_filters('wp_all_export_available_order_default_product_data_filter', $data);
		}

		public function available_order_taxes_data(){
			
			$data = array(
				'tax_order_item_name'  		=> __('Rate Code (per tax)', 'wp_all_export_plugin'),
				'tax_rate' 					=> __('Rate Percentage (per tax)', 'wp_all_export_plugin'),
				'tax_amount' 				=> __('Amount (per tax)', 'wp_all_export_plugin'),
				'_order_tax' 				=> __('Total Tax Amount', 'wp_all_export_plugin'),
				'shipping_order_item_name' 	=> __('Shipping Method', 'wp_all_export_plugin'),
				'_order_shipping' 			=> __('Shipping Cost', 'wp_all_export_plugin')
			);

			return apply_filters('wp_all_export_available_order_default_taxes_data_filter', $data);
		}

		public function available_order_fees_data(){

			$data = array(
				'discount_amount'  		=> __('Discount Amount (per coupon)', 'wp_all_export_plugin'),
				'__coupons_used' 		=> __('Coupons Used', 'wp_all_export_plugin'),
				'_cart_discount' 		=> __('Total Discount Amount', 'wp_all_export_plugin'),
				'fee_line_total' 		=> __('Fee Amount (per surcharge)', 'wp_all_export_plugin'),
				'__total_fee_amount' 	=> __('Total Fee Amount', 'wp_all_export_plugin')				
			);

			return apply_filters('wp_all_export_available_order_fees_data_filter', $data);
		}

		public function available_customer_data()
		{
			
			$main_fields = array(
				'_customer_user' => __('Customer User ID', 'wp_all_export_plugin'),
				'post_excerpt'   => __('Customer Note', 'wp_all_export_plugin')				
			);

			$data = array_merge($main_fields, $this->available_billing_information_data(), $this->available_shipping_information_data());

			return apply_filters('wp_all_export_available_user_data_filter', $data);
		
		}

		public function available_billing_information_data()
		{
			
			$keys = array(
				'_billing_first_name',  '_billing_last_name', '_billing_company',
				'_billing_address_1', '_billing_address_2', '_billing_city',
				'_billing_postcode', '_billing_country', '_billing_state', 
				'_billing_email', '_billing_phone'
			);

			$data = $this->generate_friendly_titles($keys, 'billing');

			return apply_filters('wp_all_export_available_billing_information_data_filter', $data);
		
		}

		public function available_shipping_information_data()
		{
			
			$keys = array(
				'_shipping_first_name', '_shipping_last_name', '_shipping_company', 
				'_shipping_address_1', '_shipping_address_2', '_shipping_city', 
				'_shipping_postcode', '_shipping_country', '_shipping_state'
			);

			$data = $this->generate_friendly_titles($keys, 'shipping');

			return apply_filters('wp_all_export_available_shipping_information_data_filter', $data);
		
		}

		public function generate_friendly_titles($keys, $keyword = ''){
			$data = array();
			foreach ($keys as $key) {
									
				$key1 = ucwords(str_replace('_', ' ', $key));
						$key2 = '';

						if(strpos($key1, $keyword)!== false)
						{
							$key1 = str_replace($keyword, '', $key1);
							$key2 = ' ('.__($keyword, 'wp_all_export_plugin').')';
						}
				
				$data[$key] = __(trim($key1), 'woocommerce').$key2;	
										
			}
			return $data;
		}

		/**
	     * __get function.
	     *
	     * @access public
	     * @param mixed $key
	     * @return mixed
	     */
	    public function __get( $key ) {
	        return $this->get( $key );
	    }	

	    /**
	     * Get a session variable
	     *
	     * @param string $key
	     * @param  mixed $default used if the session variable isn't set
	     * @return mixed value of session variable
	     */
	    public function get( $key, $default = null ) {        
	        return isset( $this->{$key} ) ? $this->{$key} : $default;
	    }
		
	}
}