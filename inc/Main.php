<?php

namespace WOOCICR\Inc;

if ( ! class_exists( 'Main' ) ) {
    class Main {
        
        /**
         * Constructor
         */
        public function __construct() {
            $this->rules = new Rules();

            $this->rules->add_actions();

            $this->setup_actions();
            $this->menu_actions();
        }

        public function menu_actions() {
            add_action( 'admin_menu', array( $this, 'menu_register' ) );
            add_action( 'admin_head', function() {
                RulesTable::admin_header();
            } );
        }

         /**
         * Menu register
         */
        public function menu_register () {
            add_submenu_page(
                'woocommerce',
                'Woo CSV Import Category Replacer',
                'WOOCICR',
                'import',
                'woocicr',
                array( $this, 'main_page' )
            );
        }
        
        /**
         * Setting up Hooks
         */
        public function setup_actions() {
            //Main plugin hooks
            register_activation_hook( WOOCICR_MAIN_FILE, array( $this, 'activate' ) );
            register_deactivation_hook( WOOCICR_MAIN_FILE, array( $this, 'deactivate' ) );
        }
        
        /**
         * Activate callback
         */
        public function activate() {
            $this->rules->create_db();
        }
        
        /**
         * Deactivate callback
         */
        public function deactivate() {
            $this->rules->remove_db();
        }

        /**
         * Main page
         */
        public function main_page () {
            $rules_table = new RulesTable();
            $rules_table->prepare_items();
            ?><div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <h2>Import a CSV rules file</h2>
                <form enctype="multipart/form-data" method="POST">
                    <b>Select a CSV rules file to import (<input name="drop" type="checkbox" value="true" /> - drop all rules before import)</b> <br/> <input type="file" name="file" />
                    <input name="action" value="rules_import" type="hidden" />
                    <?php echo wp_nonce_field( 'rules_import' ); ?>
                    <?php submit_button( 'Upload' ); ?>
                </form>
                <h2>Manual entry of rules</h2>
                <form method="POST">
                    <table class="rules-add-form">
                        <tr>
                            <th>From</th>
                            <th>To</th>
                        </tr>
                        <tr>
                            <td><input name="from" class="text-input" value="" /></td>
                            <td><input name="to" class="text-input" value="" /></td>
                        </tr>
                    </table>
                    <input name="action" value="add" type="hidden" />
                    <?php submit_button( 'Add' ); ?>
                </form>
                <?php echo !function_exists( 'preg_replace' ) ? '<b>REGEX syntax is not available! (install or enable preg_replace)</b><br/>' : ''; ?>
                <form method="POST">
                    <?php echo $rules_table->display(); ?>
                </form>
            </div><?php
        }
    }
}
?>