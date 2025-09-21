<?php
/**
 * Plugin Name: WP Delete Attributes
 * Plugin URI: https://kevin-benabdelhak.fr/plugins/wp-delete-attributes/
 * Description: WP Delete Attributes a pour objectif de simplifier le nettoyage de vos contenus en éliminant les attributs HTML superflus. Il ajoute une action groupée, intitulée "Supprimer les attributs HTML", qui permet de nettoyer les articles et les pages en masse, directement depuis la liste des contenus.
 * Version: 1.0
 * Author: Kevin Benabdelhak
 * Author URI: https://kevin-benabdelhak.fr/
 * Contributors: kevinbenabdelhak
 */

if (!defined('ABSPATH')) exit;





if ( !class_exists( 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
    require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
}
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$monUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/kevinbenabdelhak/WP-Delete-Attributes/', 
    __FILE__,
    'wp-delete-attributes' 
);
$monUpdateChecker->setBranch('main');






if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

class WP_Delete_Attribute {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_wpda_delete_attributes', [ $this, 'ajax_delete_attributes' ] );
        $this->add_bulk_action_hooks();
    }

    public function add_admin_menu() {
        add_options_page(
            __( 'WP Delete Attribute', 'wp-delete-attribute' ),
            __( 'WP Delete Attribute', 'wp-delete-attribute' ),
            'manage_options',
            'wp-delete-attribute',
            [ $this, 'render_options_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'wpda_options_group', 'wpda_options', [ $this, 'sanitize_options' ] );

        add_settings_section(
            'wpda_main_section',
            __( 'Options du plugin', 'wp-delete-attribute' ),
            null,
            'wp-delete-attribute'
        );

        add_settings_field(
            'wpda_exceptions',
            __( 'Attributs qui ne doivent pas être supprimés', 'wp-delete-attribute' ),
            [ $this, 'render_exceptions_field' ],
            'wp-delete-attribute',
            'wpda_main_section'
        );

        add_settings_field(
            'wpda_post_types',
            __( 'Activer les types de contenus', 'wp-delete-attribute' ),
            [ $this, 'render_post_types_field' ],
            'wp-delete-attribute',
            'wpda_main_section'
        );
    }

    public function render_options_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'wpda_options_group' );
                do_settings_sections( 'wp-delete-attribute' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_exceptions_field() {
        $options = get_option( 'wpda_options', [
            'exceptions' => 'href,src,alt,title',
        ] );
        $exceptions = isset( $options['exceptions'] ) ? $options['exceptions'] : 'href,src,alt,title';
        ?>
        <input type="text" name="wpda_options[exceptions]" value="<?php echo esc_attr( $exceptions ); ?>" class="regular-text">
        <p class="description"><?php _e( 'Entrez les attributs qui ne doivent pas être effacés. Exemple: href,src,alt,title', 'wp-delete-attribute' ); ?></p>
        <?php
    }

    public function render_post_types_field() {
        $options = get_option( 'wpda_options' );
        $enabled_post_types = isset( $options['post_types'] ) && is_array( $options['post_types'] ) ? $options['post_types'] : [ 'post', 'page' ];

        $post_types = get_post_types( [ 'public' => true ], 'objects' );

        foreach ( $post_types as $post_type ) {
            $checked = in_array( $post_type->name, $enabled_post_types ) ? 'checked' : '';
            echo "<label><input type='checkbox' name='wpda_options[post_types][]' value='" . esc_attr( $post_type->name ) . "' " . esc_attr($checked) . "> " . esc_html( $post_type->label ) . "</label><br>";
        }
    }
    
    public function sanitize_options( $input ) {
        $new_input = [];

        if ( isset( $input['exceptions'] ) ) {
            $new_input['exceptions'] = sanitize_text_field( $input['exceptions'] );
        }

        if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            $new_input['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
        } else {
            $new_input['post_types'] = [];
        }

        return $new_input;
    }

    private function add_bulk_action_hooks() {
        $options = get_option( 'wpda_options' );
        $enabled_post_types = isset( $options['post_types'] ) && is_array( $options['post_types'] ) ? $options['post_types'] : [ 'post', 'page' ];

        foreach ( $enabled_post_types as $post_type ) {
            add_filter( "bulk_actions-edit-{$post_type}", [ $this, 'add_bulk_action' ] );
            add_filter( "handle_bulk_actions-edit-{$post_type}", [ $this, 'handle_bulk_action' ], 10, 3 );
        }
    }

    public function add_bulk_action( $bulk_actions ) {
        $bulk_actions['delete_attributes'] = __( 'Supprimer les attributs HTML', 'wp-delete-attribute' );
        return $bulk_actions;
    }

    public function handle_bulk_action( $redirect_to, $doaction, $post_ids ) {
        if ( $doaction !== 'delete_attributes' ) {
            return $redirect_to;
        }
        $redirect_to = add_query_arg( 'bulk_delete_attributes_posts', implode( ',', $post_ids ), $redirect_to );
        return $redirect_to;
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( 'edit.php' !== $hook ) {
            return;
        }
        
        wp_enqueue_script(
            'wpda-admin-script',
            plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );

        wp_localize_script( 'wpda-admin-script', 'wpda_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpda_delete_attributes_nonce' ),
            'processing_message' => __( 'Attributs en cours de suppression...', 'wp-delete-attribute' ),
            'done_message' => __( 'Attributs supprimés', 'wp-delete-attribute' ),
        ] );
    }

    public function ajax_delete_attributes() {
        check_ajax_referer( 'wpda_delete_attributes_nonce', 'nonce' );

        if ( ! isset( $_POST['post_id'] ) || ! current_user_can( 'edit_post', (int) $_POST['post_id'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-delete-attribute' ) ] );
        }

        $post_id = (int) $_POST['post_id'];
        $post = get_post( $post_id );

        if ( ! $post ) {
            wp_send_json_error( [ 'message' => __( 'Post not found.', 'wp-delete-attribute' ) ] );
        }

        $updated_content = $this->strip_attributes_from_content( $post->post_content );

        if ( $updated_content === $post->post_content ) {
            wp_send_json_success( [ 'message' => __( 'No attributes to remove.', 'wp-delete-attribute' ) ] );
        } else {
            $result = wp_update_post( [
                'ID'           => $post_id,
                'post_content' => $updated_content,
            ], true );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            } else {
                wp_send_json_success( [ 'message' => __( 'Attributes deleted successfully.', 'wp-delete-attribute' ) ] );
            }
        }
    }

    private function strip_attributes_from_content( $content ) {
        if ( empty( trim( $content ) ) ) {
            return $content;
        }

        $options = get_option( 'wpda_options', [
            'exceptions' => 'href,src,alt,title',
        ] );
        $exceptions_str = isset( $options['exceptions'] ) ? $options['exceptions'] : 'href,src,alt,title';
        $exceptions = array_map( 'trim', explode( ',', $exceptions_str ) );

        $dom = new DOMDocument();
      
        @$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

        $elements = $dom->getElementsByTagName( '*' );

        foreach ( $elements as $element ) {
            $attributes_to_remove = [];
            foreach ( $element->attributes as $attribute ) {
                if ( ! in_array( strtolower( $attribute->name ), $exceptions, true ) ) {
                    $attributes_to_remove[] = $attribute->name;
                }
            }
            foreach($attributes_to_remove as $attrName){
                $element->removeAttribute($attrName);
            }
        }

        return $dom->saveHTML();
    }
}

WP_Delete_Attribute::get_instance();
