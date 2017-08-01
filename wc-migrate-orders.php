<?php
/*
Plugin Name: Woocommerce migrate orders
Plugin URI: http://worcesterwideweb.com
Description: This plugin allows you to migrate orders from one installation to another, really useful when you have 2 installation and you did a bunch of work on a beta site while the live server was still taking orders
Author: Anthony Brown 
Version: 1.0
*/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
  

  
 
class wc_migrate_orders{
	
	function __construct(){
	
	#set your remote database information here, the database must have remote access if its not on the local server.
	$this->db_username = '';
	$this->db_password = '';
	$this->db_database = '';
	$this->db_host = 'localhost';
	$this->db_prefix = 'wp_';
	}
	#insert a row into the database making sure to leave out the ID's
	function insert_row($data,$table){
				
			global $wpdb;
			
		
			foreach($data as $column=>$value){
			
			
			if($column != 'ID' ){	
			$insert[$column] = $value;	
			}
			}
			
		$insert_id = $wpdb->insert($table, $insert);
		return $wpdb->insert_id;
	}
	
	#insert order meta data
	function insert_post_meta($old_id,$post_id){
		global  $wpdb;
	$old_db = new wpdb($this->db_username ,$this->db_password ,$this->db_database,$this->db_host );
	$query = "SELECT  * FROM ".$this->db_prefix."postmeta where  post_id = '".$old_id."'  ";
	$r = $old_db->get_results($query, ARRAY_A);
	
	
		for ($i = 0; $i < count($r); $i++) {	
			
			$data['post_id'] = $post_id;
			$data['meta_key'] = $r[$i]['meta_key'];
			$data['meta_value'] = $r[$i]['meta_value'];
			$this->insert_row($data,''.$wpdb->prefix.'postmeta');	
			
			unset($data);
		}	
		
	}
	#insert order item meta data
	function insert_order_items_meta($old_id,$post_id){
		global $wpdb;
	$old_db = new wpdb($this->db_username ,$this->db_password ,$this->db_database,$this->db_host );
	$query = "SELECT  * FROM ".$this->db_prefix."woocommerce_order_itemmeta where  order_item_id = '".$old_id."'  ";
	$r = $old_db->get_results($query, ARRAY_A);
	
		for ($i = 0; $i < count($r); $i++) {	
			
			$data['order_item_id'] = $post_id;
			$data['meta_key'] = $r[$i]['meta_key'];
			$data['meta_value'] = $r[$i]['meta_value'];
			$item_meta_id = $this->insert_row($data,''.$wpdb->prefix.'woocommerce_order_itemmeta');			
				
			unset($data);
		}	
		
	}
	#insert order items
	function insert_order_items($old_id,$post_id){
		global  $wpdb;
	$old_db = new wpdb($this->db_username ,$this->db_password ,$this->db_database,$this->db_host );
	$query = "SELECT  * FROM ".$this->db_prefix."woocommerce_order_items where  order_id = '".$old_id."'  ";
	$r = $old_db->get_results($query, ARRAY_A);
	
		for ($i = 0; $i < count($r); $i++) {	
			
			$data['order_id'] = $post_id;
			$data['order_item_name'] = $r[$i]['order_item_name'];
			$data['order_item_type'] = $r[$i]['order_item_type'];
			$item_meta_id = $this->insert_row($data,''.$wpdb->prefix.'woocommerce_order_items');
			
			$this->insert_order_items_meta($r[$i]['order_item_id'],$item_meta_id);
			
				
			unset($data);
		}	
		
	}
	#process the order, insert into the database, copy the meta data and order item data
	function migrate(){
		global $wpdb;
		
			
		$old_db = new wpdb($this->db_username ,$this->db_password ,$this->db_database,$this->db_host );
			    
				
				$order_id = $_POST['order_id'];
				
			
			   $query = "SELECT  * FROM ".$this->db_prefix."posts where ID = ".$order_id ."   ";
			
			   $r = $old_db->get_results($query, ARRAY_A);
			
			
				
				  for ($i = 0; $i < count($r); $i++) {					
					
					
					$post_id = $this->insert_row($r[$i],''.$wpdb->prefix.'posts');					
					
					$this->insert_post_meta($r[$i]['ID'], $post_id);			
					$this->insert_order_items($r[$i]['ID'], $post_id);
					update_post_meta($post_id, '_wc_import_old_order', $r[$i]['ID']);
					 
					 echo 'Inserted New Order: '.$post_id.' from old order '.$order_id .' <br> ';
				 } 
				
		exit;	   
	



	
	}
	#get all the ids from the remote database and conver them to json (ajax)
	function get_ids(){
		$old_db = new wpdb($this->db_username ,$this->db_password ,$this->db_database,$this->db_host );
		
		  $query = "SELECT  * FROM ".$this->db_prefix."posts where  post_type = 'shop_order' and post_status = 'wc-completed'  ";
			
			   $r = $old_db->get_results($query, ARRAY_A);
			
			
				
				  for ($i = 0; $i < count($r); $i++) {					
					
					
					$id[]= $r[$i]['ID'];
					
				  }
				  
				  echo json_encode($id);
				  exit;
					
					
	}
	
	#show the admin panel page
	function view(){
		echo '<h1>Woocommerce Migrate Orders</h1>';
		echo '<script type="text/javascript">
		jQuery( document ).ready(function() {
  			
			
			jQuery(".start-migrate-orders").on("click", function(){
				
				var data = {
			"action": "woocommerce_migrate_orders_get_ids"
		};

		
		jQuery.post(ajaxurl, data, function(response) {
				obj = jQuery.parseJSON( response );
				
				console.log(obj);
				
				jQuery.each( obj, function( key, value ) {
					
							
		
								jQuery.post(ajaxurl, {
									"action": "woocommerce_migrate_orders_process",
									"order_id": value
								}, function(entry_response) {	
											jQuery(".wc-migrate-orders-output").prepend(entry_response);
											
								});
				});
			
		});
				
				
				return false;
			});
		});
		
		</script>
		
		<div style="padding:10px;margin:10px 0px"><a href="#" class="start-migrate-orders">Start Migrating Orders</a></div>
		<div class="wc-migrate-orders-output" style="padding:10px;margin:20px 0px;background-color:#FFF"></div>
		
		';
	}
	#add page to menu
	function menu(){
		  add_menu_page( 
        __( 'Woocommerce Migrate Orders', 'textdomain' ),
        'Woocommece Migrate Orders',
        'manage_options',
        'wc-migrate-orders',
        array($this,'view')
     
    ); 
		
	}
	
	
}

 $wc_migrate_orders = new wc_migrate_orders;
 
 add_action( 'admin_menu',  array($wc_migrate_orders, 'menu'));
 add_action( 'wp_ajax_woocommerce_migrate_orders_get_ids', array($wc_migrate_orders, 'get_ids') );
 add_action( 'wp_ajax_woocommerce_migrate_orders_process', array($wc_migrate_orders, 'migrate') );
}