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
                'woocicr',
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
                <form action="" method="POST">
                    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                    <table class="rules-add-form">
                        <tr>
                            <th>From</th>
                            <th>To</th>
                        </tr>
                        <tr>
                            <td><input name="from" value="" /></td>
                            <td><input name="to" value="" /></td>
                        </tr>
                    </table>
                    <input name="action" value="add" type="hidden" />
                    <?php submit_button( 'Add' ); ?>
                </form>
                <form action="" method="POST">
                    <?php echo $rules_table->display(); ?>
                </form>
            </div><?php
        }
    }
}
?>