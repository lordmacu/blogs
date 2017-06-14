<?php
/**
*	Export CSV helper
*/
function pmxe_export_users_csv($exportQuery, $exportOptions, $preview = false, $is_cron = false, $file_path = false, $exported_by_cron = 0){

	ob_start();		

	// Prepare headers

	$headers = array();

	$stream = fopen("php://output", 'w');

	$max_attach_count = 0;
	$max_images_count = 0;						

	$cf = array();
	$woo = array();
	$acfs = array();
	$taxes = array();
	$attributes = array();
	$articles = array();

	$implode_delimiter = ($exportOptions['delimiter'] == ',') ? '|' : ',';		

	foreach ( $exportQuery->results as $user ) :				

		$attach_count = 0;
		$images_count = 0;								
		$article = array();
		
		$article['ID'] = apply_filters('pmxe_user_id', $user->ID);

		if ($exportOptions['ids']):

			if ( wp_all_export_is_compatible() and $exportOptions['is_generate_import'] and $exportOptions['import_id']){	
				$postRecord = new PMXI_Post_Record();
				$postRecord->clear();
				$postRecord->getBy(array(
					'post_id' => $user->ID,
					'import_id' => $exportOptions['import_id'],
				));

				if ($postRecord->isEmpty()){
					$postRecord->set(array(
						'post_id' => $user->ID,
						'import_id' => $exportOptions['import_id'],
						'unique_key' => $user->ID						
					))->save();
				}
				unset($postRecord);
			}			

			foreach ($exportOptions['ids'] as $ID => $value) {				

				if (is_numeric($ID)){ 

					if ( empty($exportOptions['cc_name'][$ID])  or empty($exportOptions['cc_type'][$ID]) ) continue;
					
					$element_name = ( ! empty($exportOptions['cc_name'][$ID]) ) ? $exportOptions['cc_name'][$ID] : 'untitled_' . $ID;
					$fieldSnipped = ( ! empty($exportOptions['cc_php'][$ID] ) and ! empty($exportOptions['cc_code'][$ID])) ? $exportOptions['cc_code'][$ID] : false;

					switch ($exportOptions['cc_type'][$ID]){						
						case 'id':							
							$article[$element_name] = apply_filters('pmxe_user_id', pmxe_filter($user->ID, $fieldSnipped), $user->ID);				
							break;
						case 'user_login':
							$article[$element_name] = apply_filters('pmxe_user_login', pmxe_filter($user->user_login, $fieldSnipped), $user->ID);
							break;
						case 'user_pass':
							$article[$element_name] = apply_filters('pmxe_user_pass', pmxe_filter($user->user_pass, $fieldSnipped), $user->ID);													
							break;							
						case 'user_email':
							$article[$element_name] = apply_filters('pmxe_user_email', pmxe_filter($user->user_email, $fieldSnipped), $user->ID);							
							break;
						case 'user_nicename':
							$article[$element_name] = apply_filters('pmxe_user_nicename', pmxe_filter($user->user_nicename, $fieldSnipped), $user->ID);													
							break;
						case 'user_url':
							$article[$element_name] = apply_filters('pmxe_user_url', pmxe_filter($user->user_url, $fieldSnipped), $user->ID);						
							break;
						case 'user_activation_key':
							$article[$element_name] = apply_filters('pmxe_user_activation_key', pmxe_filter($user->user_activation_key, $fieldSnipped), $user->ID);								
							break;
						case 'user_status':							
							$article[$element_name] = apply_filters('pmxe_user_status', pmxe_filter($user->user_status, $fieldSnipped), $user->ID);							
							break;
						case 'display_name':			
							$article[$element_name] = apply_filters('pmxe_user_display_name', pmxe_filter($user->display_name, $fieldSnipped), $user->ID);							
							break;	

						case 'description':
							$val = apply_filters('pmxe_user_description', pmxe_filter($user->description, $fieldSnipped), $user->ID);
							$article[$element_name] = ($preview) ? trim(preg_replace('~[\r\n]+~', ' ', htmlspecialchars($val))) : $val;							
							break;		

						case 'user_registered':
							
							if (!empty($exportOptions['cc_options'][$ID])){ 								
								switch ($exportOptions['cc_options'][$ID]) {
									case 'unix':
										$post_date = strtotime($user->user_registered);
										break;									
									default:
										$post_date = date($exportOptions['cc_options'][$ID], strtotime($user->user_registered));
										break;
								}														
							}
							else{
								$post_date = $user->user_registered;
							}

							$article[$element_name] = apply_filters('pmxe_user_registered', pmxe_filter($post_date, $fieldSnipped), $user->ID); 

							break;						

						case 'nickname':
							$article[$element_name] = apply_filters('pmxe_user_nickname', pmxe_filter($user->nickname, $fieldSnipped), $user->ID);									
							break;	
						case 'first_name':
							$article[$element_name] = apply_filters('pmxe_user_first_name', pmxe_filter($user->first_name, $fieldSnipped), $user->ID);								
							break;	
						case 'last_name':
							$article[$element_name] = apply_filters('pmxe_user_last_name', pmxe_filter($user->last_name, $fieldSnipped), $user->ID);								
							break;													
						case 'wp_capabilities':							
							$article[$element_name] = apply_filters('pmxe_user_wp_capabilities', pmxe_filter(implode($implode_delimiter, $user->roles), $fieldSnipped), $user->ID);													
							break;							
						case 'cf':							
							if ( ! empty($exportOptions['cc_value'][$ID]) ){																		
								$cur_meta_values = get_user_meta($user->ID, $exportOptions['cc_value'][$ID]);
								if (!empty($cur_meta_values) and is_array($cur_meta_values)){									
									foreach ($cur_meta_values as $key => $cur_meta_value) {										
										if (empty($article[$element_name])){
											$article[$element_name] = apply_filters('pmxe_custom_field', pmxe_filter(maybe_serialize($cur_meta_value), $fieldSnipped), $exportOptions['cc_value'][$ID], $user->ID);										
											if (!in_array($element_name, $cf)) $cf[] = $element_name;
										}
										else{
											$article[$element_name] = apply_filters('pmxe_custom_field', pmxe_filter($article[$element_name] . $implode_delimiter . maybe_serialize($cur_meta_value), $fieldSnipped), $exportOptions['cc_value'][$ID], $user->ID);
										}
									}
								}		

								if (empty($cur_meta_values)){
									if (empty($article[$element_name])){
										$article[$element_name] = apply_filters('pmxe_custom_field', pmxe_filter('', $fieldSnipped), $exportOptions['cc_value'][$ID], $user->ID);										
										if (!in_array($element_name, $cf)) $cf[] = $element_name;
									}
									// else{
									// 	$article[$element_name . '_' . $key] = apply_filters('pmxe_custom_field', pmxe_filter('', $fieldSnipped), $exportOptions['cc_value'][$ID], get_the_ID());
									// 	if (!in_array($element_name . '_' . $key, $cf)) $cf[] = $element_name . '_' . $key;
									// }
								}																																																																
							}	
							break;

						case 'acf':							

							if ( ! empty($exportOptions['cc_label'][$ID]) and class_exists( 'acf' ) ){		

								global $acf;

								$field_options = unserialize($exportOptions['cc_options'][$ID]);

								switch ($field_options['type']) {
									case 'textarea':
									case 'oembed':
									case 'wysiwyg':
									case 'wp_wysiwyg':
									case 'date_time_picker':
									case 'date_picker':
										
										$field_value = get_field($exportOptions['cc_label'][$ID], 'user_' . $user->ID, false);

										break;
									
									default:
										
										$field_value = get_field($exportOptions['cc_label'][$ID], 'user_' . $user->ID);								

										break;
								}															

								pmxe_export_acf_field_csv($field_value, $exportOptions, $ID, 'user_' . $user->ID, $article, $acfs, $element_name, $fieldSnipped, $field_options['group_id'], $preview);
																																																																				
							}				
										
						break;						
							
						case 'sql':							
							if ( ! empty($exportOptions['cc_sql'][$ID]) ) {
								global $wpdb;											
								$val = $wpdb->get_var( $wpdb->prepare( stripcslashes(str_replace("%%ID%%", "%d", $exportOptions['cc_sql'][$ID])), get_the_ID() ));
								if ( ! empty($exportOptions['cc_php'][$ID]) and !empty($exportOptions['cc_code'][$ID]) ){
									// if shortcode defined
									if (strpos($exportOptions['cc_code'][$ID], '[') === 0){									
										$val = do_shortcode(str_replace("%%VALUE%%", $val, $exportOptions['cc_code'][$ID]));
									}	
									else{
										$val = eval('return ' . stripcslashes(str_replace("%%VALUE%%", $val, $exportOptions['cc_code'][$ID])) . ';');
									}										
								}
								$article[$element_name] = apply_filters('pmxe_sql_field', $val, $element_name, get_the_ID());		
							}
							break;
						
						default:
							# code...
							break;
					}															
				}				
			}
		endif;		

		$articles[] = $article;

		$articles = apply_filters('wp_all_export_csv_rows', $articles, $exportOptions);	
		
		if ($preview) break;

	endforeach;

	if ($exportOptions['ids']):

		foreach ($exportOptions['ids'] as $ID => $value) {

			if (is_numeric($ID)){ 

				if (empty($exportOptions['cc_name'][$ID]) or empty($exportOptions['cc_type'][$ID])) continue;

				$element_name = ( ! empty($exportOptions['cc_name'][$ID]) ) ? $exportOptions['cc_name'][$ID] : 'untitled_' . $ID;

				switch ($exportOptions['cc_type'][$ID]) {	

					case 'cf':

						if ( ! empty($cf) ){
							$headers[] = array_shift($cf);									
						}
						
						break;					

					case 'acf':

						if ( ! empty($acfs) ){
							$headers[] = array_shift($acfs);							
						}
						
						break;
					
					default:
						$headers[] = $element_name;												
						break;
				}							
				
			}			
		}

		if ( is_array($article) ) {
			foreach ( $article as $article_key => $article_item ) {
				if ( ! in_array($article_key, $headers)) $headers[] = $article_key;
			}
		}

	endif;
	
	if ($is_cron)
	{
		if ( ! $exported_by_cron ) fputcsv($stream, $headers, $exportOptions['delimiter']);	
	}
	else
	{
		if ($preview or empty(PMXE_Plugin::$session->file)) fputcsv($stream, $headers, $exportOptions['delimiter']);		
	}
	

	foreach ($articles as $article) {
		$line = array();
		foreach ($headers as $header) {
			$line[$header] = ( isset($article[$header]) ) ? $article[$header] : '';	
		}	
		fputcsv($stream, $line, $exportOptions['delimiter']);
	}			

	if ($preview) return ob_get_clean();	

	if ($is_cron)
	{
		// include BOM to export file
		if ( ! $exported_by_cron and $exportOptions['include_bom'])
		{
			file_put_contents($file_path, chr(0xEF).chr(0xBB).chr(0xBF));
		}
		
		file_put_contents($file_path, ob_get_clean(), FILE_APPEND);

		return $file_path;

	}
	else
	{
		if ( empty(PMXE_Plugin::$session->file) ){			

			$is_secure_import = PMXE_Plugin::getInstance()->getOption('secure');

			$wp_uploads  = wp_upload_dir();

			$target = $is_secure_import ? wp_all_export_secure_file($wp_uploads['basedir'] . DIRECTORY_SEPARATOR . PMXE_Plugin::UPLOADS_DIRECTORY) : $wp_uploads['path'];
				
			$export_file = $target . DIRECTORY_SEPARATOR . sanitize_file_name(preg_replace('%- \d{4}.*%', '', $exportOptions['friendly_name'])) . ' - ' . date("Y F d H_i") . '.' . $exportOptions['export_to'];			
			
			if ($exportOptions['include_bom']) 
			{
				file_put_contents($export_file, chr(0xEF).chr(0xBB).chr(0xBF).ob_get_clean());
			}
			else
			{
				file_put_contents($export_file, ob_get_clean());
			}

			PMXE_Plugin::$session->set('file', $export_file);
			
			PMXE_Plugin::$session->save_data();

		}	
		else
		{
			file_put_contents(PMXE_Plugin::$session->file, ob_get_clean(), FILE_APPEND);
		}

		return true;
	}
	
}
