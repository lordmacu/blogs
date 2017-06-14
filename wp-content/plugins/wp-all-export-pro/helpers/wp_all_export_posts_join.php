<?php

function wp_all_export_posts_join($join){

	// cron job execution
	if ( empty(PMXE_Plugin::$session)) 
	{		
		if ( ! empty(XmlExportEngine::$exportOptions['joinclause']) ) {
			$join .= implode( ' ', array_unique( XmlExportEngine::$exportOptions['joinclause'] ) );		
		}		
	}
	else
	{
		$customJoin = PMXE_Plugin::$session->get('joinClause');
		if ( ! empty( $customJoin ) ) {
			$join .= implode( ' ', array_unique( $customJoin ) );		
		}
	}		

	return $join;
}