<?php
namespace contentmx_content_manager;
global $wpdb, $cmx_version, $cmx_table_name, $cmx_block_list_table;
$cmx_version = '1.0.6';
$current_cmx_version = get_option('cmx_ccm_vers');
$cmx_table_name = $wpdb->prefix . 'contentmx_ccm';
$cmx_block_list_table = $wpdb->prefix . 'contentmx_block_list';

function activate_cmx_ccm_env(){
	//init the database and environment
	global $cmx_version, $wpdb, $cmx_table_name, $cmx_block_list_table;
	add_option('cmx_ccm_vers', $cmx_version);
	add_option('cmx_ccm_ip_whitelist', '104.239.143.221,104.130.126.50,104.130.134.136,104.130.134.52,104.239.141.252,104.130.253.78,104.130.140.25,192.168.4.4');
	add_option('cmx_ccm_call_dir_target', 'b');

	$charset_collate = $wpdb->get_charset_collate();

	$cmx_sql = "CREATE TABLE $cmx_table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		create_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		connection_token varchar(255) DEFAULT NULL,
		application_token varchar(1500) DEFAULT NULL,
		account_label varchar(200) DEFAULT NULL,
		account_name varchar(200) DEFAULT NULL,
		salt varchar(200) DEFAULT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";

	$cmx_block_list_sql = "CREATE TABLE $cmx_block_list_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		enter_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		ip_address varchar(255) DEFAULT NULL,
		UNIQUE KEY id (id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($cmx_sql);
	dbDelta($cmx_block_list_sql);

}

//update
$current_version = get_option('cmx_ccm_vers');
if($cmx_version != $current_version){
	update_option('cmx_ccm_vers', $cmx_version);
}
//version 1.0.2 update
if($current_cmx_version == '1.0.1'){
	update_option('cmx_ccm_ip_whitelist', '104.239.143.221,104.130.126.50,104.130.134.136,104.130.134.52,104.239.141.252,104.130.253.78,104.130.140.25,192.168.4.4');
}

function cmx_ajxpost(){
	$response = cmx_route_requests($_POST);
	die(json_encode($response)); 
}

function cmx_connect_wp_rest(){
	$response = cmx_route_requests($data);
	return $response; //return response
}

function cmx_delete_post(){
	$response = ['status'=>0];
	if(isset($_POST['pid']) && $_POST['pid'] != ''){
		$post_identifier = intval($_POST['pid']); //sanitize
		$delete = wp_delete_post($post_identifier, true);
		if($delete){
			$response['status'] = 1;
		}
	}

	return $response;
}

function cmx_post_message(){
	$response = ['status'=>0];
	$response['failed_on'] = [];
	$can_post = 1;
	//publish an image
	if(isset($_POST['media_url']) && $_POST['media_url'] != ''){
		//include dmin functions for side-load to work in rest
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		$file_url = sanitize_text_field($_POST['media_url']);
		$response['file'] = $file_url;
		
		$image_base_name = wp_basename($file_url);
		$file_info = pathinfo($image_base_name);
		$image_ext_array = ['jpg','png','gif','jpeg'];

		if(isset($file_info['extension']) && in_array($file_info['extension'] , $image_ext_array)){
			//do the wordpress way
			$download__url = download_url($file_url);
			$file_array  = [ 'name' => $image_base_name, 'tmp_name' => $download__url ];
		}else{
			//use curl
			
			$cmxgetimagesize = getimagesize($_POST['media_url']);
			$image_mime_types = ['image/gif','image/jpeg','image/png'];
			if(isset($cmxgetimagesize['mime']) && in_array($cmxgetimagesize['mime'] , $image_mime_types)){

				$uploaddir = wp_upload_dir();
				$file_name = preg_replace("/[^A-Za-z0-9 ]/", '', uniqid(mt_rand(), true));
				$temp_name = $file_name.'_cmx_temp';

				if($cmxgetimagesize['mime'] == 'image/jpeg'){

					$extension_cmx = '.jpg';
					$uploadfile = $uploaddir['path'] . '/' . $temp_name.$extension_cmx;

					$contents= file_get_contents($_POST['media_url']);
					$savefile = fopen($uploadfile, 'w');
					fwrite($savefile, $contents);
					fclose($savefile);

					$file_array  = [
						'name' => $file_name.$extension_cmx, 
						'type'=>'jpg',
						'tmp_name' => $uploadfile
					];

				}elseif($cmxgetimagesize['mime'] == 'image/png'){

					$extension_cmx = '.png';
					$uploadfile = $uploaddir['path'] . '/' . $temp_name.$extension_cmx;

					$contents= file_get_contents($_POST['media_url']);
					$savefile = fopen($uploadfile, 'w');
					fwrite($savefile, $contents);
					fclose($savefile);

					$file_array  = [
						'name' => $file_name.$extension_cmx,
						'type'=>'png' ,
						'tmp_name' => $uploadfile
					];

				}elseif($cmxgetimagesize['mime'] == 'image/gif'){

					$extension_cmx = '.png';
					$uploadfile = $uploaddir['path'] . '/' . $temp_name.$extension_cmx;

					$contents= file_get_contents($_POST['media_url']);
					$savefile = fopen($uploadfile, 'w');
					fwrite($savefile, $contents);
					fclose($savefile);

					$file_array  = [ 
						'name' => $file_name.$extension_cmx, //isolates and outputs the file name from its absolute path
						'type'=>'gif' , //type of image
						'tmp_name' => $uploadfile //this field passes the actual path to the image
					];
				}

			}
		}
		
		// If error storing temporarily, return the error.
		if ( is_wp_error($file_array['tmp_name']) ) {
			$response['message'] = $file_array['tmp_name'];
		}else{
			// Do the validation and storage stuff.
			$id = media_handle_sideload( $file_array );
			$response['media_id'] = $id;
		}
		
		// If error storing permanently, unlink.
		if ( is_wp_error($id) ) {
			@unlink( $file_array['tmp_name'] );
			$response['message'] = $id;
		}else{
			$response['status'] = 1;
		}
	}

	//publish a post
	if( (isset($_POST['post_content']) && $_POST['post_content'] != '') && (isset($_POST['post_title']) && $_POST['post_title'] != '') ){
		//sanitize and add title and post_content
		$cmx_post = array(
			'post_title'    => sanitize_text_field($_POST['post_title']),
			'post_content'  => wp_filter_post_kses(stripslashes($_POST['post_content'])),
			'post_status'   => 'publish'
		);

		//set post_type validate type sent
		$cmx_post['post_type'] = 'post';
		if((isset($_POST['post_type']) && $_POST['post_type'] != '')){
			//does requested post type match existing post type?
			$post_types = get_post_types(array('public' => true));
			foreach($post_types as $post_type){
				if($post_type == $_POST['post_type']){
					$cmx_post['post_type'] = $post_type;
				}
			}
		}
		//set and validate excerpt if found
		if((isset($_POST['post_excerpt']) && $_POST['post_excerpt'] != '')){
			$cmx_post['post_excerpt'] = wp_trim_excerpt(sanitize_text_field($_POST['post_excerpt']));
		}

		//validate and set author
		if((isset($_POST['post_author']) && $_POST['post_author'] != '')){
			if(user_id_exists(intval($_POST['post_author']))){
				$cmx_post['post_author'] = intval($_POST['post_author']);
			}else{
				$can_post = 0;
				$response['failed_on'][] = 'author:'.intval($_POST['post_author']);
			}	
		}

		//validate and set post status
		if((isset($_POST['post_status']) && $_POST['post_status'] != '' && ($_POST['post_status'] == 'publish' || $_POST['post_status'] == 'draft'))){
			$cmx_post['post_status'] = sanitize_text_field($_POST['post_status']);
		}else{
			$cmx_post['post_status'] = 'publish';
		}

		//validate and set taxonomy/category
		if((isset($_POST['taxonomy']) && $_POST['taxonomy'] == 'category' ) && (isset($_POST['taxonomy_val']) && $_POST['taxonomy_val'] != '')){
			//we have a category (core) validate
			$cat_id = get_cat_ID(sanitize_text_field($_POST['taxonomy_val']));
			if($cat_id != 0){
				$cmx_post['post_category'] = [$cat_id];
			}
		}elseif((isset($_POST['taxonomy']) && $_POST['taxonomy'] == 'post_tag' ) && (isset($_POST['taxonomy_val']) && $_POST['taxonomy_val'] != '')){
			//we have a tag (core) validate
			$check_termObj = get_term_by('name', sanitize_text_field($_POST['taxonomy_val']), 'post_tag');
			if(isset($check_termObj->term_id)){
				$cmx_post['tags_input'] = [$check_termObj->name];
			}
		}

		//add sanitize custom fields
		if((isset($_POST['custom_fields']) && $_POST['custom_fields'] != '')){
			//$_POST['custom_fields'] is parsed into an array then any value from the resulting array is sanitized in the foreach loop before any values are used.
			parse_str($_POST['custom_fields'], $temp_custom_fields_array);
			$custom_fields = [];
			foreach($temp_custom_fields_array as $c_field_val){
				$custom_fields[sanitize_key($c_field_val['key'])] = sanitize_text_field($c_field_val['value']);
			}
			if(count($custom_fields) > 0){
				$cmx_post['meta_input'] = $custom_fields;
			}
		}

		if($can_post == 1){
			//allow iframes and video content
			remove_filter('content_save_pre', 'wp_filter_post_kses');
			remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

			// Insert the post into the database
			$post_id = wp_insert_post( $cmx_post );
			$response['permaLink'] = '';
			$response['post_id'] = $post_id;
			if($post_id != 0){
				$response['status'] = 1;
				$response['permaLink'] = get_permalink($post_id);
			}else{
				$response['failed_on'][] = 'post:'.$post_id;
			}
			//validate and attach media
			if($post_id && (isset($_POST['media_id']) && $_POST['media_id'] != '')){
				set_post_thumbnail( $post_id, intval($_POST['media_id']) );
			}
			//validate and apply terms
			if((isset($_POST['taxonomy']) && $_POST['taxonomy'] != '' ) && (isset($_POST['taxonomy_val']) && $_POST['taxonomy_val'] != '')){
				$taxonomy = sanitize_text_field($_POST['taxonomy']);
				$termObj  = get_term_by('name', sanitize_text_field($_POST['taxonomy_val']), $taxonomy);
				if(isset($termObj->term_id) && $post_id != 0){
					wp_set_object_terms($post_id, $termObj->term_id, $taxonomy);
				}
			}

		}

	}

	return $response;
}

function user_id_exists($user){

    global $wpdb;

    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->users WHERE ID = %d", $user));

    if($count == 1){ return true; }else{ return false; }

}

function cmx_menu_page(){
	add_menu_page( 'ContentMX Connection Management', 'ContentMX', 'publish_pages', 'cmx-ccm-admin', 'contentmx_content_manager\cmx_admin_page_content', 'dashicons-welcome-widgets-menus', 90 );
}

function cmx_admin_page_content(){
	require_once(plugin_dir_path(__FILE__).'admin.php');
}

function cmx_activate_connection($token){
	global $cmx_version, $wpdb, $cmx_table_name;
	$return = 0;

	// set post fields
	$post = [];
	$post['body'] = [
		'cmx_token' => $token
	];
	
	$dir = get_option('cmx_ccm_call_dir_target');
	if($dir == ''){
		$dir = 'b';
	}

	//post to cmx servers
	$cmx_endpoint = 'https://contentmx.com/'.$dir.'/networks_edit_wordpress_plugin_ep.php'; 
	$response = wp_remote_post($cmx_endpoint, $post);
	if(isset($response['body']) && $response['body'] != ''){
		$response = json_decode($response['body'],true);
	}

	if((isset($response['t']) && $response['t'] != '') && (isset($response['s']) && $response['s'] != '') && (isset($response['label']) && $response['label'] != '') && (isset($response['name']) && $response['name'] != '')){
		$return = $wpdb->insert($cmx_table_name, array(
			'application_token' => $response['t'],
			'connection_token' => $token,
			'account_label' => $response['label'], 
			'account_name' => $response['name'],
			'create_date' => current_time('mysql'),
			'salt' => $response['s']
		));
		//$wpdb->print_error();
	}
 return $return; 
}

function cmx_get_user_list(){
	$response = [];
	$args = array(
		'role__in' => ['administrator','editor','author','contributor'],
		'orderby' => 'display_name',
		'order'   => 'ASC'
	);
	$users = get_users( $args );

	foreach( $users as $ind=>$user ){
		$response[$ind] = [];
		$response[$ind]['name'] = esc_html( $user->display_name ); 
		$response[$ind]['id'] = esc_html( $user->ID );
	}

	return $response;
}

function cmx_get_site_name(){
	$response = get_bloginfo('name');
	return $response;
}

function cmx_get_post_types(){

	$response = ['page','post'];
	$args = array(
		'public'   => true,
		'_builtin' => false
	 );
	 
	$post_types = get_post_types($args, 'objects');
	foreach($post_types as $i => $p){
		$response[] = $i;
	}

	return $response;
}

function cmx_get_taxonomies(){
	$response = [];
	$taxonomy = ((isset($_POST["taxonomy"]) && $_POST["taxonomy"] != '')?sanitize_textarea_field($_POST["taxonomy"]):'category');
	$tax = get_terms($taxonomy, array('hide_empty' => false));
	foreach($tax as $ind=>$term){
		$response[$ind] = [];
		$response[$ind]['term_id'] = $term->term_id; 
		$response[$ind]['name'] = $term->name;
		$response[$ind]['slug'] = $term->slug;
	}

	return $response;
}

function cmx_conn_delete(){
	global $wpdb, $cmx_table_name;
	$return = [];
	$return['success'] = 0;
	$delete = false;
	if(isset($_POST['acc']) && $_POST['acc'] != ''){
		$delete = $wpdb->delete($cmx_table_name, ['connection_token' => sanitize_text_field($_POST['acc'])]);
	}

	if($delete){
		$return['success'] = 1;
	}
	return $return;
}

function cmx_validate_connection(){
	global $wpdb, $cmx_table_name, $cmx_block_list_table;
	$return = 0;

	//check for block
	$check_for_block = $wpdb->prepare("SELECT ip_address FROM $cmx_block_list_table WHERE ip_address = %s AND enter_date > NOW() - INTERVAL 5 MINUTE", $_SERVER['REMOTE_ADDR']);
	$check_for_block_result = $wpdb->get_results($check_for_block, ARRAY_A);

	if(count($check_for_block_result) > 6){
		return $return;
	}

	$headers = cmx_get_headers();

	if((isset($_POST['app_tkn']) && $_POST['app_tkn'] != '') && (isset($headers['token']) && $headers['token'] != '')){

		$app_token = sanitize_text_field($_POST['app_tkn']);
		$verify_token_query = $wpdb->prepare("SELECT application_token, salt FROM $cmx_table_name WHERE `connection_token` = %s", $app_token);

		$verify_token_result = $wpdb->get_row($verify_token_query, ARRAY_A);

		if( $verify_token_result['application_token'] == crypt($headers['token'], $verify_token_result['salt']) ){
			$return = 1;
		}
	}

	return $return;
}


function cmx_get_headers()
{
	$headers = array();
	foreach ($_SERVER as $k => $v)
	{
		if (substr($k, 0, 5) == "HTTP_"){
			$k = str_replace('_', ' ', substr($k, 5));
			$k = str_replace(' ', '-', strtolower($k));
			$headers[$k] = $v;
		}
	}
	return $headers;
}

function cmx_validate_hello(){
	global $wpdb, $cmx_table_name;

	$headers = cmx_get_headers();

	$return = 0;
	if(isset($_POST['connection_id']) && $_POST['connection_id'] != ''){

		$query = $wpdb->prepare("SELECT connection_token FROM $cmx_table_name WHERE `connection_token` = %s", sanitize_text_field($_POST['connection_id']));

		$resultat = $wpdb->get_row($query, ARRAY_A);
		if(count($resultat) > 0){
			$return = 1;
		}

	}

	return $return;
}

function record_failed_attempt(){
	global $wpdb, $cmx_block_list_table;
	$ip_white_list = get_option('cmx_ccm_ip_whitelist');
	
	if($ip_white_list != ''){
		$ip_white_list = explode(',',$ip_white_list);
	}else{
		$ip_white_list = [];
	}
	if(!in_array( $_SERVER['REMOTE_ADDR'] ,$ip_white_list)){
		$wpdb->insert($cmx_block_list_table, array(
			'ip_address' => $_SERVER['REMOTE_ADDR'],
			'enter_date' => current_time('mysql', 1)
		));
	}

	//do not let blocks build up
	$wpdb->query("DELETE FROM $cmx_block_list_table WHERE enter_date < NOW() - INTERVAL 1 HOUR");
}

//routing
function cmx_route_requests(){
	$response = [];

		if(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != null && $_SERVER['REMOTE_ADDR'] != '' && cmx_validate_connection() == 1){
			//update whitelist if updated sent
			if(isset($_POST['wl']) && $_POST['wl'] != ''){
				update_option('cmx_ccm_ip_whitelist', sanitize_text_field($_POST['wl']));
			}
			//start routing
			if(isset($_POST['hello']) && $_POST['hello'] == 1){
				$response['hello'] = 1;
			}elseif(isset($_POST['posting']) && $_POST['posting'] == 1){
				$response = cmx_post_message();
			}elseif(isset($_POST['get_user']) && $_POST['get_user'] == 1){
				$response = cmx_get_user_list();
			}elseif(isset($_POST['get_tax']) && $_POST['get_tax'] == 1){
				$response = cmx_get_taxonomies();
			}elseif(isset($_POST['name']) && $_POST['name'] == 1){
				$response = cmx_get_site_name();
			}elseif(isset($_POST['get_post_types']) && $_POST['get_post_types'] == 1){
				$response = cmx_get_post_types();
			}elseif(isset($_POST['delete_post']) && $_POST['delete_post'] == 1){
				$response = cmx_delete_post();
			}elseif(isset($_POST['del']) && $_POST['del'] == 1){
				$response = cmx_conn_delete();
			}else{
				if(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != null && $_SERVER['REMOTE_ADDR'] != ''){
					record_failed_attempt();
				}
				status_header(401);
				exit();
			}
		}else{
			if(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != null && $_SERVER['REMOTE_ADDR'] != ''){
				record_failed_attempt();
			}
			status_header(401);
			exit();
		}

	return $response;

}

function cmx_delete_id(){
	global $wpdb, $cmx_table_name;
	$return = [];
	$return['success'] = 0;
	$delete = false;
	if(isset($_POST['del_id'])){
		$delete = $wpdb->delete($cmx_table_name, [ 'id' => intval($_POST['del_id'])]);
	}

	if($delete){
		$return['success'] = 1;
	}
	echo json_encode($return);
	wp_die();
}