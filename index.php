<?php
/*
 * Plugin Name:       Zanphar's Accept Before Download
 * Plugin URI:        https://www.chware.org/
 * Description:       Forces users to accept a license agreement before downloading specific files via a secure shortcode.
 * Version:           1.0.1
 * Author:            CharlieWARE SOFTWARE
 * Author URI:        https://www.chware.org/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// 1. Hook up the Admin Settings Menu
add_action( 'admin_menu', 'fld_add_settings_page' );
function fld_add_settings_page() {
    add_options_page(
        'License Download Settings',
        'License Downloads',
        'manage_options',
        'fld-settings',
        'fld_render_settings_page'
    );
}

// 2. Register Plugin Settings
add_action( 'admin_init', 'fld_register_settings' );
function fld_register_settings() {
    register_setting( 'fld_settings_group', 'fld_global_license_text' );
    register_setting( 'fld_settings_group', 'fld_box_background' );
    register_setting( 'fld_settings_group', 'fld_border_color' );
    register_setting( 'fld_settings_group', 'fld_text_color' );
}

// 3. Render the Admin Settings HTML
function fld_render_settings_page() {
    // Set up default values if settings don't exist yet
    $global_text = get_option( 'fld_global_license_text', 'I have read, understand and accept the terms and conditions of the included license agreement. I agree that I will only download directly form this site, or sites that are authorized by this site. A list of authorized sites may also be available directly through this site to confirm or deny authorization of third parties..' );
    $bg_color    = get_option( 'fld_box_background', '#f9f9f9' );
    $border_color= get_option( 'fld_border_color', '#cccccc' );
    $text_color  = get_option( 'fld_text_color', '#333333' );
    ?>
    <div class="wrap">
        <h1>Zanphar's License Download Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'fld_settings_group' ); ?>
            <?php do_settings_sections( 'fld_settings_group' ); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Default License Text</th>
                    <td>
                        <textarea name="fld_global_license_text" rows="3" cols="50" class="large-text"><?php echo esc_textarea( $global_text ); ?></textarea>
                        <p class="description">This text shows up by default unless you override it directly inside the shortcode attribute.</p>
			<p class="description"><p><ol><b><p>Examples:</b></p></p></p>
			<p class="description"><li>[license_download files="https://example.com/wp-content/uploads/2026/05/my-software.zip"]</p></li>
			<p class="description"><li>[license_download files="https://example.com/wp-content/uploads/2026/05/my-software.zip" license_text="I certify that I am using these files for non-commercial purposes only."]]</p></li>
			<p class="description"><li>[license_download files="https://example.com/file1.pdf, https://example.com/file2.zip"]</p></li>
			<p class="description"><li>[license_download files="https://example.com/file1.pdf, https://example.com/file2.zip" license_text="I certify that I am using these files for non-commercial purposes only."]</p></li></ol>
			This works both on internal sites and external sites. Please keep in mind that <b>hotlinking</b> is more often than not, frowned upon.
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">Container Background Color</th>
                    <td>
                        <input type="color" name="fld_box_background" value="<?php echo esc_attr( $bg_color ); ?>" />
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">Border Color</th>
                    <td>
                        <input type="color" name="fld_border_color" value="<?php echo esc_attr( $border_color ); ?>" />
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Text Color</th>
                    <td>
                        <input type="color" name="fld_text_color" value="<?php echo esc_attr( $text_color ); ?>" />
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// 4. Enqueue Frontend JavaScript/CSS
add_action( 'wp_enqueue_scripts', 'fld_enqueue_assets' );
function fld_enqueue_assets() {
    $custom_js = "
        document.addEventListener('DOMContentLoaded', function() {
            const wrappers = document.querySelectorAll('.fld-download-wrapper');
            
            wrappers.forEach(wrapper => {
                const checkbox = wrapper.querySelector('.fld-checkbox');
                const links = wrapper.querySelectorAll('.fld-download-link');
                
                checkbox.addEventListener('change', function() {
                    links.forEach(link => {
                        if (checkbox.checked) {
                            link.classList.remove('fld-disabled');
                            link.style.pointerEvents = 'auto';
                            link.style.opacity = '1';
                        } else {
                            link.classList.add('fld-disabled');
                            link.style.pointerEvents = 'none';
                            link.style.opacity = '0.5';
                        }
                    });
                });
            });
        });
    ";
    wp_add_inline_script( 'jquery', $custom_js );
}

// 5. Register and Render the Shortcode
add_shortcode( 'license_download', 'fld_render_download_box' );
function fld_render_download_box( $atts ) {
    // Pull options from settings database, falling back to clean defaults
    $default_global_text = get_option( 'fld_global_license_text', 'I read and accept the license agreement before downloading.' );
    $bg_color            = get_option( 'fld_box_background', '#f9f9f9' );
    $border_color        = get_option( 'fld_border_color', '#cccccc' );
    $text_color          = get_option( 'fld_text_color', '#333333' );

    $attributes = shortcode_atts( array(
        'files'        => '', 
        'license_text' => $default_global_text, // Uses admin setting if shortcode attribute is missing
    ), $atts );

    if ( empty( $attributes['files'] ) ) {
        return '<p style="color:red;">Error: No files specified for download.</p>';
    }

    $file_list = explode( ',', $attributes['files'] );
    $unique_id = uniqid('fld_');

    ob_start();
    ?>
    <div class="fld-download-wrapper" id="<?php echo esc_attr($unique_id); ?>" 
         style="border: 1px solid <?php echo esc_attr($border_color); ?>; 
                padding: 20px; 
                margin: 20px 0; 
                background: <?php echo esc_attr($bg_color); ?>; 
                color: <?php echo esc_attr($text_color); ?>; 
                border-radius: 5px;">
        
        <div class="fld-license-box" style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 500;">
                <input type="checkbox" class="fld-checkbox" value="1">
                <span><?php echo esc_html( $attributes['license_text'] ); ?></span>
            </label>
        </div>
        
        <div class="fld-links-container" style="display: flex; flex-direction: column; gap: 8px;">
            <?php 
            foreach ( $file_list as $file_url ) {
                $file_url = trim($file_url);
                $file_name = basename($file_url);
                
                $secure_download_url = add_query_arg(array(
                    'fld_download' => '1',
                    'file_key'     => base64_encode($file_url),
                    'nonce'        => wp_create_nonce('fld_download_nonce_' . $file_name)
                ), home_url());
                ?>
                <a href="<?php echo esc_url($secure_download_url); ?>" 
                   class="fld-download-link fld-disabled" 
                   style="pointer-events: none; opacity: 0.5; color: #0073aa; text-decoration: underline;"
                   download>
                    Download: <?php echo esc_html($file_name); ?>
                </a>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// 6. Handle Secure File Processing
add_action( 'init', 'fld_handle_secure_download' );
function fld_handle_secure_download() {
    if ( isset($_GET['fld_download']) && $_GET['fld_download'] === '1' && isset($_GET['file_key']) && isset($_GET['nonce']) ) {
        
        $file_url = base64_decode(sanitize_text_field($_GET['file_key']));
        $file_name = basename($file_url);
        
        if ( ! wp_verify_nonce( $_GET['nonce'], 'fld_download_nonce_' . $file_name ) ) {
            wp_die( 'Security check failed. Please refresh the page and try again.', 'Access Denied', array( 'response' => 403 ) );
        }

        $wp_upload_dir = wp_upload_dir();
        if ( strpos($file_url, $wp_upload_dir['baseurl']) !== false ) {
            $file_path = str_replace($wp_upload_dir['baseurl'], $wp_upload_dir['basedir'], $file_url);
        } else {
            $file_path = $file_url; 
        }

        if ( ! empty($file_path) ) {
            if (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . esc_attr($file_name) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            if ( file_exists($file_path) ) {
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
            } else {
                $response = wp_remote_get($file_url);
                if (!is_wp_error($response)) {
                    echo wp_remote_retrieve_body($response);
                } else {
                    wp_die('File not found.');
                }
            }
            exit;
        }
    }
}

