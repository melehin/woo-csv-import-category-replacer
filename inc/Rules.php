<?php
namespace WOOCICR\Inc;

require_once( ABSPATH . '/wp-admin/includes/class-wp-list-table.php' );

class Rules {
    var $cached_rules = array();
    var $importer = null;

    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'woocicr_rules';
    }

    public function create_db() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table_name} (
            `id` int NOT NULL AUTO_INCREMENT,
            `from` text NOT NULL,
            `to` text NOT NULL,
            PRIMARY KEY  (`id`)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        $wpdb->insert( 
            $this->table_name, 
            array( 
                'from' => '', 
                'to' => 'Others', 
            ) 
        );
    }

    public function add_actions() {
        add_filter ( 'woocommerce_product_csv_importer_check_import_file_path', array( $this, 'check_import_file_path' ), 10, 1 );
    }

    public function check_import_file_path( $check ) {
        $this->cached_rules = $this->get_rules();
        add_filter( 'woocommerce_product_importer_formatting_callbacks', array( $this, 'importer_formatting_callbacks' ), 10, 2 );
        return $check;
    }

    public function importer_formatting_callbacks( $callbacks, $importer ) {
        $this->importer = $importer;

        foreach($callbacks as $i => $callback) {
            if('parse_categories_field' == $callback[1]) {
                $callbacks[$i] = array( $this, 'replace_category' );
            }
        }
        return $callbacks;
    }

    public function replace_category ( $value ) {
        if ( empty( $value ) ) {
            return array();
        }

        $row_terms  = explode( ",", $value );
        $default_to = null;

        foreach($this->cached_rules as $j => $rule) {
            if($rule['from'] == "") {
                $default_to = $rule['to'];
                break;
            }
        }

        foreach($row_terms as $i => $term) {
            foreach($this->cached_rules as $j => $rule) {
                if ( $rule['from'] != "" && strpos( $term, $rule['from'] ) !== false ) {
                    if( function_exists( 'preg_replace' ) ) {
                        $row_terms[$i] = preg_replace( "~" . $rule['from'] . "~i", $rule['to'], $term );
                    } else {
                        $row_terms[$i] = str_replace( $rule['from'], $rule['to'], $term );
                    }
                    break;
                } elseif ( $default_to != null ) {
                    $row_terms[$i] = $default_to;
                    break;
                }
            }
        }
        return call_user_func( array( $this->importer, 'parse_categories_field' ), implode( ",", $row_terms ) );
    }

    public function remove_db() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" ); 
    }

    public function drop_rules() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$this->table_name}" ); 
    }

    public function get_rules( $per_page = 5, $page_number = 1 ) {
        global $wpdb;

        $sql = $wpdb->prepare( "SELECT * FROM {$this->table_name}  ORDER BY `from` DESC LIMIT %d OFFSET %d", $per_page, ( $page_number - 1 ) * $per_page );

        $result = $wpdb->get_results( $sql, 'ARRAY_A' );

        return $result;
    }

    /**
     * Returns the count of records in the database.
     *
     */
    public function rules_count() {
        global $wpdb;
    
        $sql = "SELECT COUNT(*) FROM {$this->table_name}";
    
        return $wpdb->get_var( $sql );
    }

    /**
     * Import rules from CSV files.
     *
     */
    public function import_rules( $filepath, $drop_all_before = false ) {
        if($drop_all_before) {
            $this->drop_rules();
        }
        $f = fopen( $filepath, 'r' );
        while ($row = fgetcsv($f)) {
            if(sizeof($row) == 2 && strtolower($row[0]) != 'from' && strtolower($row[1]) != 'to') {
                $this->add_rule( $row[0], $row[1] );
            }
        }
        fclose($f);
    }

    /**
     * Add a rule record.
     *
     */
    public function add_rule( $from, $to ) {
        global $wpdb;
    
        $wpdb->insert( 
            $this->table_name, 
            array( 
                'from' => $from, 
                'to' => $to, 
            ) 
        );
    }

    /**
     * Delete a rule record.
     *
     * @param int $id customer ID
     */
    public function delete_rule( $id ) {
        global $wpdb;
    
        $wpdb->delete(
            $this->table_name,
            [ 'id' => $id ],
            [ '%d' ]
        );

        error_log( json_encode( $wpdb->last_error ) );
    }
}

class RulesTable extends \WP_List_Table {
    var $rules = null;

    public function __construct() {
        parent::__construct();
        $this->rules = new Rules();
    }

    function get_columns(){
        $columns = array(
          'id'   => '',
          'from' => 'From',
          'to'      => 'To'
        );
        return $columns;
    }

    public static function admin_header() {
        $page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
        if( 'woocicr' != $page )
            return; 
        
        echo '<style type="text/css">';
        echo '.wp-list-table .column-id { width: 2%; }';
        echo '.wp-list-table .column-from { width: 49%; }';
        echo '.wp-list-table .column-to { width: 49%; }';
        echo '.rules-add-form .text-input { width: 100%; }';
        echo '.rules-add-form { display: table !important; width: 100% !important; }';
        echo '</style>';
    }

    public function process_bulk_action() {
        if ( sizeof($_POST) == 0 )
            return;
        if ( isset( $_POST['action'] ) && $_POST['action'] == 'rules_import' && isset( $_FILES['file'] )) {
            $file = wp_handle_upload( $_FILES['file'], $overrides = array('action' => 'rules_import') );
            if( !isset($file['error']) && $file['type'] == "text/csv" ) {
                $this->rules->import_rules( $file['file'], isset( $_POST['drop'] ) );
                unlink($file['file']);
            } else {
                error_log( $file['error'] );
            }
        } elseif ( isset( $_POST['action'] ) && $_POST['action'] == 'add' ) {
          $this->rules->add_rule( esc_sql( $_POST['from'] ), esc_sql( $_POST['to'] ) );
        // If the delete bulk action is triggered
        } elseif ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
             || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
        ) {
          $delete_ids = $_POST['id'];
 
          // loop over the array of record IDs and delete them
          foreach ( $delete_ids as $key => $id ) {
            $this->rules->delete_rule( esc_sql( $id ) );
          }
      
          wp_redirect( esc_url( add_query_arg( array() ) ) );
          exit;
        }
      }

    function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();

        /** Process bulk action */
        $this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'customers_per_page', 20 );
        $current_page = $this->get_pagenum();
        $total_items  = $this->rules->rules_count();

        $this->set_pagination_args( [
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page'    => $per_page //WE have to determine how many items to show on a page
        ] );

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $this->rules->get_rules( $per_page, $current_page );
    }

    function column_default( $item, $column_name ) {
        switch( $column_name ) {
          case 'id':
            return "<input type='checkbox' name='id[]' value='{$item[ $column_name ]}' />";
          case 'from':
          case 'to':
            return $item[ $column_name ];
          default:
            return print_r( $item, true ) ; //Мы отображаем целый массив во избежание проблем
        }
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions() {
        $actions = [
            'bulk-delete' => 'Delete'
        ];
    
        return $actions;
    }
}
?>