<?php
namespace WOOCICR\Inc;

require_once( ABSPATH . '/wp-admin/includes/class-wp-list-table.php' );

class Rules {
    var $cached_rules = array();
    var $importer = null;
    var $last_filtered_rules = null;

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
            `filter` text NOT NULL,
            `to` text NOT NULL,
            PRIMARY KEY  (`id`)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        $wpdb->insert( 
            $this->table_name, 
            array( 
                'from' => '', 
                'filter' => '', 
                'to' => 'Others', 
            ) 
        );
    }

    public function add_actions() {
        add_filter ( 'woocommerce_product_csv_importer_check_import_file_path', array( $this, 'check_import_file_path' ), 10, 1 );
    }

    public function check_import_file_path( $check ) {
        $this->cached_rules = $this->get_rules( $per_page = 100000 );
        add_filter( 'woocommerce_product_importer_formatting_callbacks', array( $this, 'importer_formatting_callbacks' ), 10, 2 );
        return $check;
    }

    public function importer_formatting_callbacks( $callbacks, $importer ) {
        $this->importer = $importer;

        foreach($callbacks as $i => $callback) {
            if('parse_categories_field' == $callback[1]) {
                $callbacks[$i] = array( $this, 'replace_category' );
            } elseif('parse_skip_field' == $callback[1]) {
                $callbacks[$i] = array( $this, 'check_by_filter' );
            }
        }
        return $callbacks;
    }

    public function check_by_filter ( $value ) {
        foreach($this->cached_rules as $j => $rule) {
            if( $rule['filter'] == "" 
            || (function_exists( 'preg_match' ) && preg_match( "~" . $rule['filter'] . "~i", $value ) === 1)
            || (stripos( $value, $rule['filter'] ) !== false)) {
                if( $this->last_filtered_rules == null ) {
                    $this->last_filtered_rules = array($rule);
                }elseif( !in_array($rule, $this->last_filtered_rules) ){
                    $this->last_filtered_rules[] = $rule;
                }
            }
        }
        return call_user_func( array( $this->importer, 'parse_skip_field' ), $value );
    }

    public function replace_category ( $value ) {
        if ( empty( $value ) ) {
            return array();
        }

        $rules = array();

        if ( $this->last_filtered_rules !== null ) {
            $rules = $this->last_filtered_rules;
            $this->last_filtered_rules = null;
        } else {
            $rules = $this->cached_rules;
        }

        // Replace escaped comma to _ESCAPED_COMMA_ constant
        $value = str_replace("\,", "_ESCAPED_COMMA_", $value);
        $row_terms  = explode( ",", $value );
        $default_to = null;

        foreach($this->cached_rules as $j => $rule) {
            if($rule['from'] == "" && $rule['filter'] == "") {
                $default_to = $rule['to'];
                break;
            }
        }

        foreach($row_terms as $i => $term) {
            $term = str_replace("_ESCAPED_COMMA_", "\\,", $term);
            $rules_not_found = true;
            foreach($rules as $j => $rule) {
                $rule['from'] = str_replace("\\", "\\\\", $rule['from']);
                $rule['to'] = str_replace("\\", "\\\\", $rule['to']);
                $check = $rule['from'] == ""
                || (function_exists( 'preg_match' ) && preg_match( "~" . $rule['from'] . ".*~i", $term ) === 1)
                || (stripos( $term, $rule['from'] ) !== false);
                
                if ( $check ) {
                    
                    $rule['from_regex'] = $rule['from'];
                    if(stripos( $rule['to'], '$1' ) !== false) {
                        $rule['from_regex'] .= '.*';
                    }

                    if($rule['from'] == ""){
                        $row_terms[$i] = $rule['to'];
                    } elseif ( $rule['filter'] == "" && function_exists( 'preg_replace' ) ) {
                        $row_terms[$i] = preg_replace( "~" . $rule['from_regex'] . "~i", $rule['to'], $term );
                    } elseif ( $rule['filter'] == "" ) {
                        $row_terms[$i] = str_replace( $rule['from'], $rule['to'], $term );
                    } else {
                        $row_terms[$i] = $rule['to'];
                    }
                    $rules_not_found = false;
                    break;
                }
            }
            if ( $default_to != null && $rules_not_found ) {
                $row_terms[$i] = $default_to;
            }
        }

        $cat_ids = array();
        // Split each term_group to terms
        foreach($row_terms as $i => $term_group) {
            $term_group = str_replace("\\,", "_ESCAPED_COMMA_", $term_group);
            // Converts each term to numberic category_id
            foreach(explode(',', $term_group) as $i => $term) {
                $term = str_replace("_ESCAPED_COMMA_", "\\,", $term);
                if( strpos( $term, '#' ) !== false ) {
                    $cat_ids[] = trim( str_replace( '#', '', $term ) );
                } else {
                    $cats = call_user_func( array( $this->importer, 'parse_categories_field' ), $term );
                    if( sizeof($cats) ) {
                        $cat_ids[] = $cats[0];
                    }
                }
            }
        }

        return $cat_ids;
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

        $sql = $wpdb->prepare( "SELECT * FROM {$this->table_name}  ORDER BY `from` DESC, `filter` DESC LIMIT %d OFFSET %d", $per_page, ( $page_number - 1 ) * $per_page );

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
            if(sizeof($row) >= 3 && strtolower($row[0]) != 'from' && strtolower($row[1]) != 'filter' && strtolower($row[2]) != 'to') {
                $this->add_rule( $row[0], $row[1], $row[2] );
            }
        }
        fclose($f);
    }

    /**
     * Add a rule record.
     *
     */
    public function add_rule( $from, $filter, $to ) {
        global $wpdb;
    
        $wpdb->insert( 
            $this->table_name, 
            array( 
                'from' => $from, 
                'filter' => $filter,
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
          'filter' => '(or) Filter',
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
        echo '.wp-list-table .column-from { width: 32%; }';
        echo '.wp-list-table .column-filter { width: 32%; }';
        echo '.wp-list-table .column-to { width: 32%; }';
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
          $this->rules->add_rule( esc_sql( $_POST['from'] ), esc_sql( $_POST['filter'] ), esc_sql( $_POST['to'] ) );
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
          case 'filter':
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