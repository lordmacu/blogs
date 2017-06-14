<?php

if ( ! class_exists('XmlExportFiltering') ){

	class XmlExportFiltering{

		private $queryWhere = "";
		private $queryJoin = array();
		private $options;		
		private $tax_query = false;
		private $meta_query = false;

		public function __construct($args = array()){

			$this->options = $args;						

		}

		public function parseQuery(){

			if ( empty($this->options['filter_rules_hierarhy'])) return false;

			$filter_rules_hierarhy = json_decode($this->options['filter_rules_hierarhy']);

			if ( ! empty($filter_rules_hierarhy) and is_array($filter_rules_hierarhy) ): 				

				$this->queryWhere = " AND (";								

				foreach ($filter_rules_hierarhy as $rule) {
					
					if ( is_null($rule->parent_id) )
					{												

						$this->parse_single_rule($rule);

						
					}
				}
				
				global $wpdb;

				// Apply strict or permissive matching for products
				if ( ! XmlExportEngine::$is_user_export and ! empty(XmlExportEngine::$post_types) and @in_array("product", XmlExportEngine::$post_types) and class_exists('WooCommerce'))
				{					

					switch ($this->options['product_matching_mode']) {											

						case 'permissive':							

							$tmp_queryWhere = $this->queryWhere;
							$tmp_queryJoin  = $this->queryJoin;							
							
							$this->queryJoin = array();

							$this->queryWhere = " $wpdb->posts.post_type = 'product_variation' AND (($wpdb->posts.post_status <> 'trash' AND $wpdb->posts.post_status <> 'auto-draft')) AND (";

								foreach ($filter_rules_hierarhy as $rule) {
					
									if ( is_null($rule->parent_id) )
									{												

										$this->parse_single_rule($rule);
										
									}
								}

							$this->queryWhere .= ")";

							$where = $this->queryWhere;							
							$join  = implode( ' ', array_unique( $this->queryJoin ) );		

							$this->queryWhere = $tmp_queryWhere;
							$this->queryJoin  = $tmp_queryJoin;

							$this->queryWhere .= ") OR ($wpdb->posts.ID IN (
								SELECT DISTINCT $wpdb->posts.post_parent
								FROM $wpdb->posts $join
								WHERE $where
							)) GROUP BY $wpdb->posts.ID";

							break;
						
						default:
							
							if ($this->meta_query || $this->tax_query)
							{
								$this->queryWhere .= " ) GROUP BY $wpdb->posts.ID";
							}
							else
							{
								$this->queryWhere .= ")";		
							}								

							break;
					}

				}
				else
				{ 

					if ($this->meta_query || $this->tax_query)
					{
						if (XmlExportEngine::$is_user_export)
						{
							$this->queryWhere .= " ) GROUP BY $wpdb->users.ID";					
						}
						else
						{
							$this->queryWhere .= " ) GROUP BY $wpdb->posts.ID";
						}						
					}
					else
					{
						$this->queryWhere .= ")";					
					}					
				}

			endif;

		}	

		protected function parse_single_rule($rule){

			global $wpdb;

			if ( XmlExportEngine::$is_user_export )
			{
				switch ($rule->element) {
					case 'ID':
						$this->queryWhere .= "$wpdb->users.$rule->element " . $this->parse_condition($rule, true);			
						break;								
					case 'user_role':
						$cap_key = $wpdb->prefix . 'capabilities';
						$this->queryJoin[] = " INNER JOIN $wpdb->usermeta ON ($wpdb->usermeta.user_id = $wpdb->users.ID) ";
						$this->queryWhere .= "$wpdb->usermeta.meta_key = '$cap_key' AND $wpdb->usermeta.meta_value LIKE '%\"{$rule->value}\"%'";
						if ( ! empty($rule->clause)) $this->queryWhere .= " " . $rule->clause . " ";
						break;
					case 'user_registered':
						$rule->value = date("Y-m-d H:i:s", strtotime($rule->value));															
						$this->queryWhere .= "$wpdb->users.$rule->element " . $this->parse_condition($rule);
						break;								
					case 'user_status':
					case 'display_name':
					case 'user_login':
					case 'user_nicename':
					case 'user_email':
					case 'user_url':
						$this->queryWhere .= "$wpdb->users.$rule->element " . $this->parse_condition($rule);
						break;
					case 'blog_id':																
						
						break;
					default:
						if (strpos($rule->element, "cf_") === 0)
						{
							$this->meta_query = true;
							$meta_key = str_replace("cf_", "", $rule->element);
							
							if ($rule->condition == 'is_empty')
							{
								$this->queryJoin[] = " LEFT JOIN $wpdb->usermeta ON ($wpdb->usermeta.user_id = $wpdb->users.ID AND $wpdb->usermeta.meta_key = '$meta_key') ";
								$this->queryWhere .= "$wpdb->usermeta.umeta_id " . $this->parse_condition($rule);
							}
							else
							{
								$this->queryJoin[] = " INNER JOIN $wpdb->usermeta ON ($wpdb->usermeta.user_id = $wpdb->users.ID) ";
								$this->queryWhere .= "$wpdb->usermeta.meta_key = '$meta_key' AND $wpdb->usermeta.meta_value " . $this->parse_condition($rule);
							}										
																	
						}
						break;
				}
			}
			else
			{
				switch ($rule->element) {
					
					case 'ID':								
					case 'post_parent':		
					case 'post_author':						
						$this->queryWhere .= "$wpdb->posts.$rule->element " . $this->parse_condition($rule, true);																
						break;
					case 'post_status':
					case 'post_title':
					case 'post_content':
						$this->queryWhere .= "$wpdb->posts.$rule->element " . $this->parse_condition($rule);
						break;		
					case 'post_date':
						if (strpos($rule->value, "+") !== 0 
								&& strpos($rule->value, "-") !== 0 
									&& (strpos($rule->value, "second") !== false || strpos($rule->value, "minute") !== false || strpos($rule->value, "hour") !== false || (strpos($rule->value, "day") !== false && strpos($rule->value, "today") === false && strpos($rule->value, "yesterday") === false) || strpos($rule->value, "week") !== false || strpos($rule->value, "month") !== false || strpos($rule->value, "year") !== false))
						{
							$rule->value = "-" . $rule->value;
						}
						
						$rule->value = date("Y-m-d", strtotime($rule->value));															
						$this->queryWhere .= "$wpdb->posts.$rule->element " . $this->parse_condition($rule);
						break;								
					default:
						
						if (strpos($rule->element, "cf_") === 0)
						{
							$this->meta_query = true;
							$meta_key = str_replace("cf_", "", $rule->element);
							
							if ($rule->condition == 'is_empty')
							{
								$this->queryJoin[] = " LEFT JOIN $wpdb->postmeta ON ($wpdb->postmeta.post_id = $wpdb->posts.ID AND $wpdb->postmeta.meta_key = '$meta_key') ";
								$this->queryWhere .= "$wpdb->postmeta.meta_id " . $this->parse_condition($rule);
							}
							else
							{
								$table_alias = (count($this->queryJoin) > 0) ? 'meta' . count($this->queryJoin) : 'meta';
								$this->queryJoin[] = " INNER JOIN $wpdb->postmeta AS $table_alias ON ($wpdb->posts.ID = $table_alias.post_id) ";
								$this->queryWhere .= "$table_alias.meta_key = '$meta_key' AND $table_alias.meta_value " . $this->parse_condition($rule, false);
							}										
																	
						}
						elseif (strpos($rule->element, "tx_") === 0)
						{
							if ( ! empty($rule->value) ){
								$this->tax_query = true;
								$tx_name = str_replace("tx_", "", $rule->element);

								$terms = array();
								$txs = explode(",", $rule->value);

								foreach ($txs as $tx) {
									if (is_numeric($tx)){
										$terms[] = $tx;													
									}
									else{
										$term = term_exists($tx, $tx_name);													
										if (!is_wp_error($term)){
											$terms[] = $term['term_taxonomy_id'];
										}
									}
								}

								if ( ! empty($terms) ){
									
									$terms_str = implode(",", $terms);

									switch ($rule->condition) {
										case 'in':
											
											$this->queryJoin[] = " INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id)";														
											$this->queryWhere .= "$wpdb->term_relationships.term_taxonomy_id IN ($terms_str)";
											if ( ! empty($rule->clause)) $this->queryWhere .= " " . $rule->clause . " ";

											break;
										case 'not_in':
											$this->queryWhere .= "$wpdb->posts.ID NOT IN (
												SELECT object_id
												FROM $wpdb->term_relationships
												WHERE term_taxonomy_id IN ($terms_str)
											)";
											if ( ! empty($rule->clause)) $this->queryWhere .= " " . $rule->clause . " ";
											break;
										default:
											# code...
											break;
									}
								}
							}
						}

						break;
				}
			}

			$this->recursion_parse_query($rule);
		}

		protected function recursion_parse_query($parent_rule){

			$filter_rules_hierarhy = json_decode($this->options['filter_rules_hierarhy']);

			$sub_rules = array();
			
			foreach ($filter_rules_hierarhy as $j => $rule) if ($rule->parent_id == $parent_rule->item_id and $rule->item_id != $parent_rule->item_id) { $sub_rules[] = $rule; }

			if ( ! empty($sub_rules) ){

				$this->queryWhere .= "(";		

				foreach ($sub_rules as $rule){
					
					$this->parse_single_rule($rule);

				}

				$this->queryWhere .= ")";

			}
		}

		protected function parse_condition($rule, $is_int = false){
				
			$value = $rule->value;
			$q = "";
			switch ($rule->condition) {
				case 'equals':
					if ($rule->element == 'post_date')
					{
						$q = "LIKE '%". $value ."%'";
					}
					else
					{
						$q = "= " . (($is_int or is_numeric($value)) ? $value : "'" . $value . "'");
					}					
					break;
				case 'not_equals':
					if ($rule->element == 'post_date')
					{
						$q = "NOT LIKE '%". $value ."%'";
					}
					else
					{
						$q = "!= " . (($is_int or is_numeric($value)) ? $value : "'" . $value . "'");
					}					
					break;
				case 'greater':
					$q = "> " . (($is_int or is_numeric($value)) ? $value : "'" . $value . "'");
					break;
				case 'equals_or_greater':
					$q = ">= " . (($is_int or is_numeric($value)) ? $value : "'" . $value . "'");
					break;
				case 'less':
					$q = "< " . (($is_int or is_numeric($value)) ? $value : "'" . $value . "'");
					break;
				case 'equals_or_less':
					$q = "<= " . (($is_int or is_numeric($value)) ? $value : "'" . $value . "'");
					break;
				case 'contains':
					$q = "LIKE '%". $value ."%'";
					break;
				case 'not_contains':
					$q = "NOT LIKE '%". $value ."%'";
					break;
				case 'is_empty':
					$q = "IS NULL";
					break;
				case 'is_not_empty':
					$q = "IS NOT NULL";
					break;
				// case 'in':

				// 	break;
				// case 'not_in':

				// 	break;
				// case 'between':

				// 	break;
				
				default:
					# code...
					break;

			}

			if ( ! empty($rule->clause)) $q .= " " . $rule->clause . " ";

			return $q;

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