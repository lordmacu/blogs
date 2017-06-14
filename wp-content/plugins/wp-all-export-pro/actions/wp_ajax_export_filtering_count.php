<?php

function pmxe_wp_ajax_export_filtering_count(){

	if ( ! check_ajax_referer( 'wp_all_export_secure', 'security', false )){
		exit( json_encode(array('html' => __('Security check', 'wp_all_export_plugin'))) );
	}

	if ( ! current_user_can('manage_options') ){
		exit( json_encode(array('html' => __('Security check', 'wp_all_export_plugin'))) );
	}

	ob_start();

	$input = new PMXE_Input();
	
	$post = $input->post('data', array());

	$filter_args = array(
		'filter_rules_hierarhy' => $post['filter_rules_hierarhy'],
		'product_matching_mode' => $post['product_matching_mode']
	);

	XmlExportEngine::$is_user_export = ( 'users' == $post['cpt'] ) ? true : false;
	XmlExportEngine::$post_types = array($post['cpt']);

	$filters = new XmlExportFiltering($filter_args);

	$filters->parseQuery();
				
	PMXE_Plugin::$session->set('whereclause', $filters->get('queryWhere'));
	PMXE_Plugin::$session->set('joinclause',  $filters->get('queryJoin'));
	PMXE_Plugin::$session->save_data();		

	$found_records = 0;

	if ( 'users' == $post['cpt'] )
	{
		add_action('pre_user_query', 'wp_all_export_pre_user_query', 10, 1);
		$exportQuery = new WP_User_Query( array( 'orderby' => 'ID', 'order' => 'ASC', 'number' => 10 ));		

		if ( ! empty($exportQuery->results)){
			$found_records = $exportQuery->get_total();			
		}
		remove_action('pre_user_query', 'wp_all_export_pre_user_query');
	}
	else
	{					
		add_filter('posts_where', 'wp_all_export_posts_where', 10, 1);
		add_filter('posts_join', 'wp_all_export_posts_join', 10, 1);
				
		$cpt = ($post['cpt'] == 'product') ? array('product', 'product_variation') : $post['cpt'];

		$exportQuery = new WP_Query( array( 'post_type' => $cpt, 'post_status' => 'any', 'orderby' => 'ID', 'order' => 'ASC', 'posts_per_page' => 10 ));							

		if ( ! empty($exportQuery->found_posts)){
			$found_records = $exportQuery->found_posts;			
		}

		remove_filter('posts_join', 'wp_all_export_posts_join');			
		remove_filter('posts_where', 'wp_all_export_posts_where');
	}	

	?>
	<div class="founded_records">			
		<?php if ($found_records > 0) :?>
		<h3><span class="matches_count"><?php echo $found_records; ?></span> <strong><?php echo wp_all_export_get_cpt_name(array($post['cpt']), $found_records); ?></strong> will be exported</h3>
		<h4><?php _e("Continue to Step 2 to choose data to include in the export file."); ?></h4>		
		<?php else: ?>
		<h4 style="line-height:60px;"><?php printf(__("No matching %s found for selected filter rules"), wp_all_export_get_cpt_name(array($post['cpt']))); ?></h4>
		<?php endif; ?>
	</div>
	<?php	
	
	exit(json_encode(array('html' => ob_get_clean()))); die;

}