<?php
/*
Plugin Name: Nextt Google Forms
Plugin URI: http://www.nextt.com.br
Description: The simplest way to add your google form in your POST.
Version: 0.0.1
Author: Nextt - We maximize your results with intelligent interfaces
Author URI: http://www.nextt.com.br
License: GPL
*/

define('ngfID','ngf');

add_action( 'load-post.php', ngfID.'_post_meta_boxes_setup' );
add_action( 'load-post-new.php', ngfID.'_post_meta_boxes_setup' );
add_shortcode('nextt-google-form', ngfID.'_render_form');
add_action('wp_head', ngfID.'_header');
add_action('init', ngfID.'_submit');

function ngf_post_meta_boxes_setup() {

	/* Add meta boxes on the 'add_meta_boxes' hook. */
	add_action( 'add_meta_boxes', ngfID.'_add_post_meta_boxes' );

	/* Save post meta on the 'save_post' hook. */
	add_action( 'save_post', ngfID.'_save_postmeta', 10, 2 );
	
}

function ngf_add_post_meta_boxes() {
	$screens = array( 'post', 'page' );
  foreach ($screens as $screen) {
		add_meta_box(
			ngfID.'-google-form-box',			// Unique ID
			esc_html__( 'Nextt Google Form', ngfID),		// Title
			ngfID.'_metabox',		// Callback function
			$screen,					// Admin page (or post type)
			'normal',					// Context
			'default'					// Priority
		);
	}
}


function ngf_metabox( $object, $box ) { ?>

	<?php wp_nonce_field( basename( __FILE__ ), ngfID.'_nonce' ); ?>

	<p>
		<label for="<?php echo ngfID; ?>_URL"><?php _e( "Add the Google Form URL <span class='small'>(It must looks like \"https://docs.google.com/forms/d/1UDF75-4240wAnScaqc923HH6weQovNujRRiQ1EvwmyY/viewform\")</span>", ngfID ); ?></label>
		<br />
		<input class="widefat" type="text" name="<?php echo ngfID; ?>_URL" id="<?php echo ngfID; ?>_URL" value="<?php echo esc_attr( get_post_meta( $object->ID, ngfID.'_URL', true ) ); ?>" size="30" />
	</p>
	<p>
		<label for="<?php echo ngfID; ?>_URL_response"><?php _e( "Add the Google Form response URL <span class='small'>(It must looks like \"https://docs.google.com/spreadsheet/ccc?key=0Ap89SWZuags8dG1jNFpsbjdLVTJhWVpZblRNRkZxT1E#gid=0\")</span>", ngfID ); ?></label>
		<br />
		<input class="widefat" type="text" name="<?php echo ngfID; ?>_URL_response" id="<?php echo ngfID; ?>_URL_response" value="<?php echo esc_attr( get_post_meta( $object->ID, ngfID.'_URL_response', true ) ); ?>" size="30" />
	</p>
	<p>
		<label for="<?php echo ngfID; ?>_page_form_sent"><?php _e( "The page to be redirected after submit the form."); ?></label>
		<select name="<?php echo ngfID; ?>_page_form_sent" id="<?php echo ngfID; ?>_page_form_sent">
			<?php
				$pages = get_pages();
				$page_form_sent = get_post_meta( $object->ID, ngfID.'_page_form_sent', true );					
				foreach ( $pages as $page ) {
					$page_link = get_page_link( $page->ID );
					$option = '<option value="' . $page_link .'" '.selected( $page_link, $page_form_sent ).'>';
					$option .= $page->post_title;
					$option .= '</option>';
					echo $option;
				}
			?>
		</select>
	</p>
	<p>
		<?php
			$checked = '';
			if($is_checked = get_post_meta( $object->ID, ngfID.'_keep_style', true )){
				$checked = ' checked = "checked" ';
			}
		?>
		<input type="checkbox" name="<?php echo ngfID; ?>_keep_style" id="<?php echo ngfID; ?>_keep_style" value="true" <?php echo $checked;	 ?> />
		<label for="<?php echo ngfID; ?>_keep_style"><?php _e( "Include Google styles? Keep this checked if you want to keep the form with the Google style", ngfID ); ?></label>
	</p>
<?php }

function ngf_save_postmeta( $post_id, $post ) {
	if ( ! wp_is_post_revision( $post_id ) ){
	
		/* Verify the nonce before proceeding. */
		if ( !isset( $_POST[ngfID.'_nonce'] ) || !wp_verify_nonce( $_POST[ngfID.'_nonce'], basename( __FILE__ ) ) )
			return $post_id;

		/* Get the post type object. */
		$post_type = get_post_type_object( $post->post_type );

		/* Check if the current user has permission to edit the post. */
		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
			return $post_id;

		$meta_options = array(ngfID.'_keep_style' , ngfID.'_URL' , ngfID.'_URL_response', ngfID.'_page_form_sent');

		$havemeta = false;
		$keepCSS = false;
		foreach ($meta_options as $opt) {
			/* Get the posted data */
			$new_meta_value = ( isset( $_POST[$opt] ) ? trim( $_POST[$opt] ) : '' );

			/* Checks if at least one meta is set to verify shortcode and stuffs later */
			if((!empty($new_meta_value)) && $opt!=ngfID.'_page_form_sent'){
				$havemeta = true;
				if($opt == ngfID.'_keep_style') $keepCSS = true;
			}

			/* Get the meta value of the custom field key. */
			$meta_value = get_post_meta( $post_id, $opt, true );

			/* If a new meta value was added and there was no previous value, add it. */
			if ( $new_meta_value && '' == $meta_value )
				add_post_meta( $post_id, $opt, $new_meta_value, true );
				
			/* If the new meta value does not match the old value, update it. */
			elseif ( $new_meta_value && $new_meta_value != $meta_value )
				update_post_meta( $post_id, $opt, $new_meta_value );

			/* If there is no new meta value but an old value exists, delete it. */
			elseif ( '' == $new_meta_value && $meta_value )
				delete_post_meta( $post_id, $opt, $meta_value );
		}

		
		if($havemeta){
			/* Save the HTML google form */
			$url = get_post_meta( $post_id, ngfID.'_URL', true );
			$str = file_get_contents($url);

			//cleanup Google Forms empty divs
			$str = preg_replace('/(<div[^>]*\/>)/i', '', $str);

			$dom = new DOMDocument();
			@$dom->loadHTML($str);
			$form = $dom->getElementsByTagName('form')->item(0);
			$form->removeAttribute('action');
			$form->removeAttribute('onsubmit');

			$form_str = $dom->saveXML($form, LIBXML_NOEMPTYTAG);
			update_post_meta( $post_id, ngfID.'_form', $form_str);

			/* Save the CSS */
			if($keepCSS){
				$itens = $dom->getElementsByTagName('link');
				$css = '';
				foreach ($itens as $item) {
				    if($item->getAttribute('rel')=='stylesheet'){
				        $href = $item->getAttribute('href');
				        $item->setAttribute('href','https://docs.google.com'.$href);
				        $css .= $dom->saveXML($item);
				    }
				}
				update_post_meta( $post_id, ngfID.'_CSS', $css );
			}

			/* Save the JS */
			$itens = $dom->getElementsByTagName('script');
			$js ='';
			$jsclose='';
			foreach ($itens as $item) {
			    if($src = $item->getAttribute('src')){
			        $item->setAttribute('src','https://docs.google.com'.$src);
			        $jsclose = '</script>';
			    }
			    $js .= $dom->saveXML($item).$jsclose;
			}
			update_post_meta( $post_id, ngfID.'_js', $js );

			/* Checks if have shortcode on content */
			$pattern = get_shortcode_regex();
			$short = 'nextt-google-form';
			if ( !(  preg_match_all( '/'. $pattern .'/s', $post->post_content, $matches ) && array_key_exists( 2, $matches ) && in_array( $short, $matches[2] ) )){
				$my_post = array();
				$my_post['ID'] = $post->ID;
				$my_post['post_content'] = $post->post_content.'

				['.$short.']';

				// unhook this function so it doesn't loop infinitely
				remove_action('save_post', 'ngf_save_postmeta');
			
				// update the post, which calls save_post again
				wp_update_post( $my_post );

				// re-hook this function
				add_action('save_post', 'ngf_save_postmeta');
			}
		}
	}
}

function ngf_render_form(){
	global $post;

	do_action( 'render_form_notices' );
	$htmlform = get_post_meta( $post->ID, ngfID.'_form', true );
	$htmlform = str_replace('value="Submit"', 'value="Enviar seu voto" class="btn btn-large"', $htmlform);
	$htmlform = str_replace('Never submit passwords through Google Forms.', '', $htmlform);
	$htmlform = str_replace('<div class="required-message">This is a required question</div>', '', $htmlform);
	$htmlform = str_replace('This is a required question', '', $htmlform);
	$nonce = wp_nonce_field( basename( __FILE__ ).'_submit', ngfID.'_nonce_submit',true,false );
	$return = substr($htmlform, 0, -7) . $nonce . '<input type="hidden" name="id_hidden" id="id_hidden" value="'.$post->ID.'"/> '. substr($htmlform, -7);
	//$return .= get_post_meta( $post->ID, ngfID.'_js', true);

	//Fill form with 
	$return .= "<script>jQuery(document).ready(function(){";
	foreach ($_POST as $key => $value) {
		if (substr($key, 0, 5) != 'entry') continue;
		$return .= "jQuery('input:radio[name=\"".str_replace("_", ".", $key)."\"][value=\"".$value."\"]').attr('checked',true);\n";
		$return .= "jQuery('input:checkbox[name=\"".str_replace("_", ".", $key)."\"][value=\"".$value."\"]').attr('checked',true);\n";
		$return .= "jQuery('input:text[name=\"".str_replace("_", ".", $key)."\"], textarea[name=\"".str_replace("_", ".", $key)."\"]').val('".$value."');\n\n";
	}
	$return .= "});</script>";

	wp_enqueue_script( 'ngf-validator', plugin_dir_url( __FILE__ ) . '/static/js/ngf-validator.js', array( 'jquery' )	);
	wp_enqueue_style( 'ngf-style', plugin_dir_url( __FILE__ ) . '/static/css/ngf-style.css', array(), '1.0.0', 'all' );

	return apply_filters( ngfID.'_filter_rendered_form', $return );
}

function ngf_header(){
	global $post;
	if(is_single()){
		if(get_post_meta($post->ID, ngfID.'_keep_style', true)){
			echo get_post_meta($post->ID, ngfID.'_CSS', true);
		}
	}
}
function ngf_submit(){
	// if ( !empty($_POST[ngfID.'_nonce_submit']) && wp_verify_nonce($_POST[ngfID.'_nonce_submit'],basename( __FILE__ ).'_submit') ){
	if ( !empty($_POST[ngfID.'_nonce_submit']) ){
		$entry = '/^entry_/';
		$postdata='';
		$vir='';
		$vars = apply_filters(ngfID.'_filter_post_data', $_POST);
		if ( !is_wp_error($vars) ){

			foreach ($vars as $key => $value) {
				if(preg_match ( $entry , $key)){
					$postdata .= $vir.urlencode(str_replace("_", "." , $key)) . "=" . urlencode($value);
					$vir = "&";
				}
			}

			$url = str_replace("viewform", "formResponse",get_post_meta( $_POST['id_hidden'], ngfID.'_URL', true ));
			$ch = curl_init();
			curl_setopt ($ch, CURLOPT_URL,$url);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt ($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
			curl_setopt ($ch, CURLOPT_POST, 1);
			$data = curl_exec ($ch);
			curl_close($ch);

			$do_redirect = apply_filters(ngfID.'_after_gform_submit', $vars, $data);

			if (!is_wp_error($do_redirect)){
				wp_redirect( get_post_meta( $_POST['id_hidden'], ngfID.'_page_form_sent', true ) );
				exit;
			
			} else {
				// Lets let the admin know whats going on.
  			$msg = '<div class="error"><h3>Encontramos um problema.</h3><p>'.$do_redirect->get_error_message().'</p></div>';
  			add_action( 'render_form_notices', $c = create_function( '', 'echo "' . addcslashes( $msg, '"' ) . '";' ) );
			}

		} else {
 			$msg = '<div class="error"><h3>Encontramos um problema.</h3><p>'.$vars->get_error_message().'</p></div>';
			add_action( 'render_form_notices', $c = create_function( '', 'echo "' . addcslashes( $msg, '"' ) . '";' ) );
		}
	}
}
?>