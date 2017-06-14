<?php

function wp_all_export_posts_where($where){
	
	// cron job execution
	if ( empty(PMXE_Plugin::$session)) 
	{		
		if ( ! empty(XmlExportEngine::$exportOptions['whereclause']) ) $where .= XmlExportEngine::$exportOptions['whereclause'];		
	}
	else
	{
		// manual export run
		$customWhere = PMXE_Plugin::$session->get('whereclause');
		$where .= $customWhere;				
	}

	return $where;
}