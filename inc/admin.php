<h1>ContentMX Content Publisher</h1>
<?php
//
global $wpdb;
$cmx_table_name = $wpdb->prefix . 'contentmx_ccm';

if(isset($_GET['ctoken']) && $_GET['ctoken'] != ''){
	$connection = contentmx_content_manager\cmx_activate_connection($_GET['ctoken']);
}
$posts = $wpdb->wp_posts;
$result = $wpdb->get_results( " SELECT account_label, account_name, create_date, id FROM $cmx_table_name  "  );?>
<p>To add a connection login to your <a href="https://contentmx.com/b/login.php" target="_blank">ContentMX account</a> and create or edit a wordpress network. Select "plugin" as the type and add a connection to this plugin. Once established the connection will apear in the list below.</p>
<?php
if(count($result) > 0){
?>

<table class="wp-list-table widefat fixed striped table-view-list">
	<thead>
	<tr>
		<th scope="col" id="title" class="manage-column">ContentMX Account</th>
		<th scope="col" id="author" class="manage-column">Date Of Connection</th>
		<th scope="col" id="date" class="manage-column ">&nbsp;</th>	
	</tr>
	</thead>
	<tbody id="the-list">
<?php
	foreach ($result as $connection) {
		?>
		<tr id="post-3" class="iedit author-self level-0 post-3 type-page status-draft hentry entry">
			<td class="title column-title has-row-actions column-primary"><?php echo wp_kses($connection->account_label,[]).' ('.wp_kses($connection->account_name,[]).')<br/>'; ?></td>
			<td class="date column-date"><?php echo wp_kses($connection->create_date,[]); ?></td>
			<td class="date column-remove"><a href="#" data="<?php echo $connection->id; ?>" class="remove-connection">Remove Connection</a></td>
		</tr>
		<?php
	}
	?>
	</tbody>
</table>

<?php
}

?>
<div id="add_manual_connection" style="display:none; padding:20px; width:100%; max-width:300px; border:1px solid #3c434a; margin:10px 0; position:relative;">
<div id="close-add-plugin" style="position:absolute; top:10px; right:10px; cursor:pointer;">x</div>
	Enter the connection code here:<br><input type="text" style="box-sizing: border-box; margin: 0 0 7px 0;" class="form-control" name="plugin_id" id="plugin_id" value=""><br><button type="button" class="btn btn-primary" id="add_wordpress_plugin_id" style="cursor:pointer;">Add Wordpress Connection</button>
</div>

<a href="#" id="manual_add_connection">Manually add connection</a>
<script>
	jQuery(document).ready(function(){
		jQuery(document).on('click','.remove-connection',function(e){
			e.preventDefault();
			var con_id = jQuery(this).attr('data');
			jQuery.ajax({
				type: 'POST',
				url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
				data: {'action':'cmx_delete_id','del_id':con_id},
				dataType:"json",
				success: function(resultData) { 
					if(resultData.success == 1){
						alert("The connection has been removed."); 
						window.location.reload(false);
					}else{
						alert("There was an error. We could not remove the connection.");
						window.location.reload(false); 
					}
				},
				error: function (textStatus, errorThrown) {
					alert("There was an error. We could not remove the connection."); 
				}
			});
		});
		jQuery(document).on('click','#manual_add_connection',function(e){
			e.preventDefault();
			jQuery('#add_manual_connection').css({'display':'block'});
		});
		jQuery(document).on('click','#close-add-plugin',function(e){
			e.preventDefault();
			jQuery('#add_manual_connection').css({'display':'none'});
		});
		jQuery(document).on('click','#add_wordpress_plugin_id',function(e){
			e.preventDefault();
			if(jQuery('#plugin_id').val() != ''){
				window.location.href = 'admin.php?page=cmx-ccm-admin&ctoken='+jQuery('#plugin_id').val();
			}else{
				alert('Connection code can not be empty.');
			}
			
		});
	});
</script>
<?php 
if(isset($_GET['admin_set_cmx_ccm_call_dir_target'])){
	if($_GET['admin_set_cmx_ccm_call_dir_target'] == 'a'){
		update_option('cmx_ccm_call_dir_target', 'a');
	}elseif($_GET['admin_set_cmx_ccm_call_dir_target'] == 'b'){
		update_option('cmx_ccm_call_dir_target', 'b');
	}
}
?>
<!-- cmx_ccm_call_dir_target: <?php echo wp_kses(get_option('cmx_ccm_call_dir_target'),[]); ?> -->