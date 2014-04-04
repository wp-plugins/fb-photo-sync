<?php
/*
 * Plugin Name: FB Photo Sync
 * Description: Import and manage Facebook photo ablums on your WordPress website.
 * Author: Mike Auteri 
 * Version: 0.1
 * Author URI: http://www.mikeauteri.com/
 * Plugin URI: http://www.mikeauteri.com/projects/fb-photo-sync
 */

class FB_Photo_Sync {
	
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'fbps_scripts' ) );
		add_action( 'wp_ajax_fbps_save_album', array( $this, 'ajax_save_photos' ) );
		add_action( 'wp_ajax_fbps_delete_album', array( $this, 'ajax_delete_photos' ) );

		add_shortcode( 'fb_album', array( $this, 'fb_album_shortcode' ) );
	}

	public function admin_scripts() {
		wp_enqueue_style( 'fbps-admin-styles', plugin_dir_url( __FILE__ ) . 'css/admin.css', array(), 1.0 );
		wp_enqueue_script( 'fbps-zero-clipboard', plugin_dir_url( __FILE__ ) . 'js/jquery.zclip.js', array( 'jquery' ), 1.0, true );
		wp_enqueue_script( 'fbps-admin-script', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery', 'fbps-zero-clipboard' ), 1.0, true );
	}

	public function fbps_scripts() {
		wp_enqueue_style( 'fbps-styles', plugin_dir_url( __FILE__ ) . 'css/styles.css', array(), 1.0 );
		wp_enqueue_style( 'fancybox-css', plugin_dir_url( __FILE__ ) . 'fancybox/jquery.fancybox.css', array(), 1.0 );
    wp_enqueue_script( 'fancybox-js', plugin_dir_url( __FILE__ ) . 'fancybox/jquery.fancybox.js', array( 'jquery' ), 1.0, false );
	}

	public function closest_image_size( $width, $height, $photos ) {
		$current = null;

		foreach( $photos as $photo ) {
			if ( ! $this->valid_image_size( $width, $height, $photo ) ) {
				continue;
			}

			$current = $this->get_closest_image( $width, $height, $photo, $current );
		}
		
		if( $current == null ) {
			$current['source'] = $photos[0]['source'];
		}
		
		return $current['source'];
	}

	private function valid_image_size( $width, $height, $photo ) {
		return $width <= $photo['width'] && $height <= $photo['height'];
	}

	private function get_image_diff_sum( $width, $height, $photo ) {
		$width_diff = $photo['width'] - $width;
		$height_diff = $photo['height'] - $height;

		return $width_diff + $height_diff;
	}

	private function get_closest_image( $width, $height, $photo, $current = null ) {
		if ( ! $current ) {
			return $photo;
		}

		$photo_sum = $this->get_image_diff_sum( $width, $height, $photo );
		$current_sum = $this->get_image_diff_sum( $width, $height, $current );

		if ( $current_sum > $photo_sum ) {
			return $photo;
		}

		return $current;
	}

	public function fb_album_shortcode( $atts ) {
		extract( shortcode_atts( array(
			'id' => '',
			'width' => 130,
			'height' => 130
		), $atts ) );

		if( empty( $id ) || is_Nan( $id ) ) {
			return;
		}
		
		$album = get_option( 'fbps_album_'.$id );
		
		if( !isset( $album['items'] ) )
			return;

		ob_start();
		?>
		<div id="fbps-album-<?php echo esc_attr( $album['id']	); ?>" class="fbps-album">
			<h3><?php echo esc_html( $album['name'] ); ?></h3>
			<ul>
			<?php
			foreach( $album['items'] as $item ) {
				$thumbnail = $this->closest_image_size( $width, $height, $item['photos'] );
				$image = $this->closest_image_size( 960, 960, $item['photos'] );
				?>
				<li id="fbps-photo-<?php echo esc_attr( $item['id'] ); ?>" class="fbps-photo">
					<a rel="fbps-album" class="fbps-photo" href="<?php echo esc_url( $image ); ?>" style="width: <?php echo intval( $width ); ?>px; height: <?php echo intval( $height ); ?>px; background-image: url(<?php echo esc_url( $thumbnail ); ?>);" alt="<?php echo esc_attr( $item['name'] ); ?>" title="<?php echo esc_attr( $item['name'] ); ?>">
					</a>
				</li>
				<?php
			}
			?>
			</ul>
			<div style="clear: both;"></div>
		</div>
		<script type="text/javascript">
			(function($) {
				$('a[rel="fbps-album"]').fancybox();
			})(jQuery);	
		</script>	
		<?php
		return ob_get_clean();
	}
	
	public function admin_tabs( $current = 'import' ) {
		$tabs = array( 'import' => 'Import', 'albums' => 'Albums' );
		echo '<div class="wrap">';
		echo '<h2>FB Photo Sync</h2>';
		echo '<div id="icon-themes" class="icon32"><br /></div>';
    echo '<h2 class="nav-tab-wrapper">';
    foreach( $tabs as $tab => $name ){
			$class = ( $tab == $current ) ? ' nav-tab-active' : '';
			echo "<a class='nav-tab$class' href='?page=fb-photo-sync&tab=$tab'>$name</a>";
    }
    echo '</h2>';	
	}
	
	public function admin_content( $current = 'import' ) {
		switch( $current ) {
			case 'import':
				$this->import_page();
				break;
			case 'albums':
				$this->albums_page();
				break;
			default:
				$this->import_page();
		}
		?>
		<hr style="clear: both;" />
		<div style="text-align: center;">
		<h3>Like this plugin? Buy me a beer!</h3>
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
				<input type="hidden" name="cmd" value="_s-xclick">
				<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----
				">
				<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
				<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
			</form>
		</div>
		<?php
		echo '</div>'; // wrap
	}

	public function import_page() {
		global $facebook;
		?>
		<h3>Import Facebook Albums</h3>
		<div id="import-form">
			<ul>
			</ul>
			<p class="submit">
				<input type="button" id="import-button" class="button button-primary" value="Import Albums">
			</p>
		</div>
		<h3>Find Albums on a Public Page</h3>
		<p>Type in a Page ID below and click the <em>Find Albums</em> button to pull in available albums.
		<p>Check the albums you want to import into WordPress, and then click the <em>Import Albums</em> button above to import them.</p>
		<p>When completed, click the <em>Albums</em> tab for the album shortcode to include in your post or page.</p>
		<p>
			<input type="text" id="fbps-page-input" placeholder="Enter Facebook Page ID" />
			<input type="button" name="" id="fbps-load-albums" class="button" value="Find Albums" />
		</p>
		<p class="description">http://facebook.com/this-is-the-page-id</p>
		<ul id="fbps-page-album-list" class="fbps-list">
		</ul>

		<?php
	}
	
	public function albums_page() {
		global $wpdb;
		
		$query = $wpdb->prepare( "
			SELECT option_name, option_value 
			FROM {$wpdb->options} 
			WHERE
			option_name LIKE %s
			", 'fbps_album_%' );

		$albums = $wpdb->get_results( $query );
		echo '<ul id="fbps-album-list">';
		foreach( $albums as $album ) {
			$dump = unserialize( $album->option_value );
			?>
			<li data-id="<?php echo esc_attr( $dump['id'] ); ?>">
				<h3><?php echo esc_html( $dump['name'] ); ?></h3>
				<a href="#" class="fbps-image-sample" style="background-image: url(<?php echo esc_url( $dump['picture'] ); ?>);"></a>
				<div class="fbps-options">
					<p><code>[fb_album id="<?php echo esc_attr( $dump['id'] ); ?>"]</code></p>
					<p><a href="#" class="delete-album">Delete</a> | <a href="#" class="sync-album">Sync</a></p>
				</div>
			</li>
			<?php
		}
		echo '</ul>';
	}

	public function ajax_save_photos() {
		header( 'Content-type: application/json' );
		$album = json_decode( stripslashes( $_POST['album'] ), true );
		if( is_array( $album ) && current_user_can( 'manage_options' ) ) {
			update_option( 'fbps_album_' . esc_attr( $album['id'] ), $album );
			echo json_encode( array( 'id' => $album['id'], 'success' => true, 'error' => false ) );
		} else {
			echo json_encode( array( 'id' => $album['id'], 'success' => false, 'error' => true ) );
		}
		die();
	}

	public function ajax_delete_photos() {
		$id = $_POST['id'];
		header( 'Content-type: application/json' );
		if( isset( $id ) && current_user_can( 'manage_options' ) ) {
			delete_option( 'fbps_album_' . esc_attr( $id ) );
			echo json_encode( array( 'id' => esc_attr( $id ), 'success' => true, 'error' => false ) );
		} else {
			echo json_encode( array( 'id' => esc_attr( $id ), 'success' => false, 'error' => true ) );
		}
		die();
	}

	public function add_menu_page() {
		add_options_page( 'FB Photo Sync', 'FB Photo Sync', 'manage_options', 'fb-photo-sync', array( $this, 'display_options_page' ) );
	}
	
	public function display_options_page() {
		$current = isset( $_GET['tab'] ) ? $_GET['tab'] : 'import';
		$this->admin_tabs( $current );
		$this->admin_content( $current );
		?>	
		<div id="fb-root"></div>
		<script>
			window.fbAsyncInit = function() {
				FB.init({
					//appId      : '', public data, no need for now...
					status     : true,
					xfbml      : true
				});
			};

			(function(d, s, id){
				 var js, fjs = d.getElementsByTagName(s)[0];
				 if (d.getElementById(id)) {return;}
				 js = d.createElement(s); js.id = id;
				 js.src = "//connect.facebook.net/en_US/all.js";
				 fjs.parentNode.insertBefore(js, fjs);
			 }(document, 'script', 'facebook-jssdk'));
		</script>
		<?php
	}
}
new FB_Photo_Sync();
