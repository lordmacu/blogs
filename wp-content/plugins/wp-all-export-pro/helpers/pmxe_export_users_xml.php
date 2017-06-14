<?php
/**
*	Export XML helper
*/
function pmxe_export_users_xml($exportQuery, $exportOptions, $preview = false, $is_cron = false, $file_path = false){
	
	$xmlWriter = new XMLWriter();
	$xmlWriter->openMemory();
	$xmlWriter->setIndent(true);
	$xmlWriter->setIndentString("\t");
	$xmlWriter->startDocument('1.0', $exportOptions['encoding']);
	$xmlWriter->startElement('data');		

	foreach ( $exportQuery->results as $user ) :				

		//$exportQuery->the_post(); $record = get_post( get_the_ID() );

		$xmlWriter->startElement('post');			

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

						if (empty($exportOptions['cc_name'][$ID]) or empty($exportOptions['cc_type'][$ID])) continue;
						
						$element_name = ( ! empty($exportOptions['cc_name'][$ID]) ) ? preg_replace('/[^a-z0-9_]/i', '', $exportOptions['cc_name'][$ID]) : 'untitled_' . $ID;				
						$fieldSnipped = ( ! empty($exportOptions['cc_php'][$ID]) and ! empty($exportOptions['cc_code'][$ID]) ) ? $exportOptions['cc_code'][$ID] : false;

						switch ($exportOptions['cc_type'][$ID]) {
							case 'id':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_id', pmxe_filter($user->ID, $fieldSnipped), $user->ID));			
								break;
							case 'user_login':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_login', pmxe_filter($user->user_login, $fieldSnipped), $user->ID));
								break;
							case 'user_pass':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_pass', pmxe_filter($user->user_pass, $fieldSnipped), $user->ID));
								break;							
							case 'user_email':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_email', pmxe_filter($user->user_email, $fieldSnipped), $user->ID));
								break;
							case 'user_nicename':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_nicename', pmxe_filter($user->user_nicename, $fieldSnipped), $user->ID));
								break;
							case 'user_url':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_url', pmxe_filter($user->user_url, $fieldSnipped), $user->ID));
								break;
							/*case 'user_activation_key':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_activation_key', pmxe_filter($user->user_activation_key, $fieldSnipped), $user->ID));
								break;
							case 'user_status':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_status', pmxe_filter($user->user_status, $fieldSnipped), $user->ID));
								break;*/
							case 'display_name':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_display_name', pmxe_filter($user->display_name, $fieldSnipped), $user->ID));
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
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_registered', pmxe_filter($post_date, $fieldSnipped), $user->ID));
								break;		

							case 'nickname':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_nickname', pmxe_filter($user->nickname, $fieldSnipped), $user->ID));
								break;	
							case 'first_name':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_first_name', pmxe_filter($user->first_name, $fieldSnipped), $user->ID));
								break;	
							case 'last_name':
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_last_name', pmxe_filter($user->last_name, $fieldSnipped), $user->ID));
								break;														
							case 'wp_capabilities':							
								$xmlWriter->writeElement($element_name, apply_filters('pmxe_user_wp_capabilities', pmxe_filter(implode(",", $user->roles), $fieldSnipped), $user->ID));
								break;								
							case 'description':
								$xmlWriter->startElement($element_name);
									$xmlWriter->writeCData(apply_filters('pmxe_user_description', pmxe_filter($user->description, $fieldSnipped), $user->ID));
								$xmlWriter->endElement();
								break;

							case 'cf':							
								if ( ! empty($exportOptions['cc_value'][$ID]) ){																		
									$cur_meta_values = get_user_meta($user->ID, $exportOptions['cc_value'][$ID]);																				
									if (!empty($cur_meta_values) and is_array($cur_meta_values)){
										foreach ($cur_meta_values as $key => $cur_meta_value) {
											$xmlWriter->startElement($element_name);
												$xmlWriter->writeCData(apply_filters('pmxe_custom_field', pmxe_filter(maybe_serialize($cur_meta_value), $fieldSnipped), $exportOptions['cc_value'][$ID], $user->ID));
											$xmlWriter->endElement();
										}
									}

									if (empty($cur_meta_values)){
										$xmlWriter->startElement($element_name);
											$xmlWriter->writeCData(apply_filters('pmxe_custom_field', pmxe_filter('', $fieldSnipped), $exportOptions['cc_value'][$ID], $user->ID));
										$xmlWriter->endElement();
									}																																																												
								}								
								break;

							case 'acf':							

								if ( ! empty($exportOptions['cc_label'][$ID]) and class_exists( 'acf' ) ){		

									global $acf;

									$field_value = get_field($exportOptions['cc_label'][$ID], 'user_' . $user->ID);

									$field_options = unserialize($exportOptions['cc_options'][$ID]);

									pmxe_export_acf_field_xml($field_value, $exportOptions, $ID, 'user_' . $user->ID, $xmlWriter, $element_name, $fieldSnipped, $field_options['group_id']);
																																																																					
								}				
												
								break;													
							
							case 'sql':

								if ( ! empty($exportOptions['cc_sql'][$ID]) ){

									global $wpdb;
									$val = $wpdb->get_var( $wpdb->prepare( stripcslashes(str_replace("%%ID%%", "%d", $exportOptions['cc_sql'][$ID])), get_the_ID() ));
									if ( ! empty($exportOptions['cc_php'][$ID]) and !empty($exportOptions['cc_code'][$ID])){
										// if shortcode defined
										if (strpos($exportOptions['cc_code'][$ID], '[') === 0){									
											$val = do_shortcode(str_replace("%%VALUE%%", $val, $exportOptions['cc_code'][$ID]));
										}	
										else{
											$val = eval('return ' . stripcslashes(str_replace("%%VALUE%%", $val, $exportOptions['cc_code'][$ID])) . ';');
										}										
									}
									$xmlWriter->startElement($element_name);
										$xmlWriter->writeCData(apply_filters('pmxe_sql_field', $val, $element_name, get_the_ID()));
									$xmlWriter->endElement();
								}
								break;							

							default:
								# code...
								break;
						}						
					}					
				}
			endif;		

		$xmlWriter->endElement(); // end post		
		
		if ($preview) break;

	endforeach;

	$xmlWriter->endElement(); // end data
	
	if ($preview) return wp_all_export_remove_colons($xmlWriter->flush(true));	

	if ($is_cron)
	{		
		
		$xml = $xmlWriter->flush(true);
		
		if (file_exists($file_path))
		{
			file_put_contents($file_path, wp_all_export_remove_colons(substr(substr($xml, 45), 0, -8)), FILE_APPEND);
		}		
		else
		{		
			// include BOM to export file
			if ($exportOptions['include_bom']) 
			{
				file_put_contents($file_path, chr(0xEF).chr(0xBB).chr(0xBF).wp_all_export_remove_colons(substr($xml, 0, -8)));
			}
			else
			{
				file_put_contents($file_path, wp_all_export_remove_colons(substr($xml, 0, -8)));
			}				
		}
		
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
				file_put_contents($export_file, chr(0xEF).chr(0xBB).chr(0xBF).wp_all_export_remove_colons(substr($xmlWriter->flush(true), 0, -8)));
			}
			else
			{
				file_put_contents($export_file, wp_all_export_remove_colons(substr($xmlWriter->flush(true), 0, -8)));
			}

			PMXE_Plugin::$session->set('file', $export_file);
			
			PMXE_Plugin::$session->save_data();

		}	
		else
		{
			file_put_contents(PMXE_Plugin::$session->file, wp_all_export_remove_colons(substr(substr($xmlWriter->flush(true), 45), 0, -8)), FILE_APPEND);
		}

		return true;

	}	

}