<?PHP
/*
Copyright 2024 Mateusz Pieła

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
/*
 * Plugin Name: Piwigo Gallery
 * Plugin URI: https://github.com/mateuszpiela/wp-plugin-piwigogallery
 * Description: Plugin for wordpress to show from piwigo galleries on shortcode
 * Version: 0.0.3
 * Requires at least: 6.4.3
 * Requires PHP: 8.0
 * Author: Mateusz Pieła
 * Author URI: https://mateuszpiela.eu/
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * Text Domain: piwigogallery
 * Domain Path: /languages
 */


 /**
  * Call piwigo api for getting the category list
  *
  * @param string $url Piwigo web app url address
  * @return array|WP_ERROR
  */
function piwigogallery_callapiforcattegorylist( $url ) {
    $url = $url . '/ws.php?format=json&method=pwg.categories.getList&public=true&thumbnail_size=xlarge';

    return wp_remote_get( $url );
}

/**
 * Generate HTML for gallery using response from web service call
 * 
 * @param string $response the body from wp_remote_get
 * @return string
 */
function piwigogallery_generategalleryhtml( $response, $limit ) {
    $json = json_decode( $response, true );
    $html = '<div class="piwigogallery">';

    $count = 0;

    foreach ( $json['result']['categories'] as $album ) {
        if( $count > $limit ) {
            break;
        }

        $title = htmlspecialchars( $album['name'] );
        $comment = htmlspecialchars( $album['comment'] );
        $thumbnail_url = filter_var( $album['tn_url'], FILTER_SANITIZE_URL );
        $url = filter_var( $album['url'], FILTER_SANITIZE_URL );

        $html .= sprintf( '<a target="_blank" href="%s" class="card">', $url );
        $html .= sprintf( ' <img src="%s" alt="%s">', $thumbnail_url, $title );
        $html .= sprintf( '<div class="caption caption-title">%s</div>', $title );
        $html .= sprintf( '<div class="caption">%s</div>', $comment );
        $html .= '</a>';
        $count++;
    }

    $html .= "</div>";
    return $html;
}

/**
 * Check if url is valid and doesn't contains /ws.php
 * 
 * @param string $url string for checking url
 * @return bool
 */
function piwigogallery_validurl( $url ) {
    $check_valid_url = filter_var( $url, FILTER_VALIDATE_URL );
    $check_if_url_not_contains_ws_at_end = strpos( $url, "/ws.php" ) === false;

    return $check_valid_url && $check_if_url_not_contains_ws_at_end;
}

/**
 * Check if limit is an integer and the limit is more than 0
 * 
 * @param int $limit an integer to check
 * @return bool
 */
function piwigogallery_validlimit( $limit ) {
    return is_int($limit) && $limit > 0;
}

/**
 * Sanitize input data
 * 
 * @param array $a shortcode attributes 
 * @return array
 */
function piwigogallery_sanitizedata( $a ) {
    $a['url'] = filter_var( $a['url'], FILTER_SANITIZE_URL );
    $a['limit'] = (int)filter_var( $a['limit'], FILTER_SANITIZE_NUMBER_INT );

    return $a;
}

/**
 * Render piwigo gallery using wordpress shortcode
 * 
 * @param array $atts shortcode attributes
 * @return string
 */
function piwigogallery_render( $atts ) {
    $atts = array_change_key_case( (array) $atts, CASE_LOWER );
    $a = shortcode_atts( array(
        'url' => '',
        'limit' => 20,
    ), $atts );

    $a = piwigogallery_sanitizedata( $a );

    $valid_url = piwigogallery_validurl( $a['url'] );
    $valid_limit = piwigogallery_validlimit( $a['limit'] );

    if( $valid_url && $valid_limit ) {
        $resp = piwigogallery_callapiforcattegorylist( $a['url'] );
        
        if( is_wp_error( $resp ) ) {
            $html = sprintf( '<div style="color: red">%s: %s ! </div>', 
            __( 'Piwigo Gallery Alert', 'piwigogallery' ), 
            $resp->get_error_messages() 
            );

            return $html;
        }

        $html = piwigogallery_generategalleryhtml( $resp['body'], $a['limit'] );
    } else {
        $html = sprintf( '<div style="color: red">%s: %s</div>' ,
         __( 'Piwigo Gallery Alert', 'piwigogallery' ),
         __( 'URL or LIMIT is invalid please check parameters !', 'piwigogallery')
         );
    }
    return $html;
}
add_shortcode( 'piwigogallery', 'piwigogallery_render' );

function piwigogallery_stylesheet() {
    $options = get_option( 'piwigogallery_options' );

    if( !(bool)$options['disable_builtin_css'] ){
        wp_enqueue_style( 'piwigogallery', plugins_url('/assets/css/piwigogallery.css', __FILE__) );
    }
}
add_action( 'wp_footer', 'piwigogallery_stylesheet' );

function piwigogallery_activationhook() {
    $options = array(
        'disable_builtin_css' => 'false'
    );
    add_option( 'piwigogallery_options', $options );
}
register_activation_hook(
    __FILE__,
    'piwigogallery_activationhook'
);

function piwigogallery_uninstallhook() {
    delete_option( 'piwigogallery_options' );
}
register_uninstall_hook(
    __FILE__,
    'piwigogallery_uninstallhook'
);

/*
 * Admin panel settings code under this comment
 */ 
function piwigogallery_settings_init() {
    register_setting( 'piwigogallery', 'piwigogallery_options' );

    add_settings_section(
        'piwigogallery_options',
        __( 'General options', 'piwigogallery' ), 'piwigogallery_options_callback',
        'piwigogallery'
    );

    add_settings_field(
        'piwigogallery_field_cssbuiltindisable',
        __( 'Disable builtin CSS rules? ', 'piwigogallery' ),
        'piwigogallery_field_cssbuiltindisable_cb',
        'piwigogallery',
        'piwigogallery_options',
        array(
            'label_for' => 'disable_builtin_css',
            'class' => 'piwigogallery_row',
            'piwigogallery_custom_data' => 'custom',
        )
        );

}
add_action( 'admin_init', 'piwigogallery_settings_init' );

function piwigogallery_options_callback( $args ) {

}

function piwigogallery_field_cssbuiltindisable_cb( $args ) {
    $options = get_option('piwigogallery_options');
    ?>
    <input 
        type="checkbox" 
        id="<?php echo esc_attr( $args['label_for'] ); ?>" 
        name="piwigogallery_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
        value="true"
        <?php echo isset( $options[ $args['label_for'] ] ) ? ( checked( (bool)$options[ $args['label_for'] ], true, false ) ) : ( '' ); ?>
        >
        
    <?php
}

function piwigogallery_options_menu() {
	add_submenu_page(
		'upload.php',
		__( 'Piwigo Gallery Options', 'piwigogallery' ),
        __( 'Piwigo Gallery Options', 'piwigogallery' ),
		'manage_options',
		'piwigogallery-options',
		'piwigogallery_options_cb'
	);
}
add_action( 'admin_menu', 'piwigogallery_options_menu' );

function piwigogallery_options_cb() {
    if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

    if ( isset( $_GET['settings-updated'] ) ) {
		// add settings saved message with the class of "updated"
		add_settings_error( 'piwigogallery_messages', 'piwigogallery_message', __( 'Settings Saved', 'piwigogallery' ), 'updated' );
	}

    settings_errors( 'piwigogallery_messages' );
    ?>
	<div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
                settings_fields( 'piwigogallery' );
                do_settings_sections( 'piwigogallery' );
                submit_button( __( 'Save Settings', 'piwigogallery' ));
            ?>
        </form>
	</div>
	<?php
}