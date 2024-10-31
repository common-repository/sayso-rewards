<?php
/*
Plugin Name: SaySo Rewards | Monetize & Engage Your Visitors
Plugin URI: https://wordpress.org/plugins/sayso-rewards/
Description: Monetize your website with our free survey offerwall. Your visitors give opinions and earn gift cards, you generate revenue and we donate to charity!
Version: 1.1.2
Author: SaySo Rewards
Author URI: http://home.saysoforgood.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpL-2.0.html
*/

class SaySoRewardsWidget extends WP_Widget {

    function __construct() {
        // Instantiate the parent object
        parent::__construct( "SaySoSurvey", __('SaySo User Panel'), array("description"=>__("Allow your users to access their SaySo Survey user page.")) );
    }

    function widget( $args, $instance ) {
        global $wpdb;
        $table_name = $wpdb->prefix . "RFG";
        $id = $wpdb->get_var("SELECT _id FROM $table_name WHERE id = 1");
        // Widget output
        $widget = "<div style='position:relative;'><iframe src=\"https://www.saysoforgood.com/wp/wpWidgetRouter.zul?id="
            .$id."\" height='500px' style='border: none; width: 100%; max-width: 300px; min-width: 300px;'></iframe></div>";
        echo $widget;
    }

    function update( $new_instance, $old_instance ) {
        // Save widget options
    }

    function form( $instance ) {
        // Output admin widget options form
    }
}

class SaySoRewards {
    private static $instance;

    public static function getInstance(){
        if(self::$instance == NULL){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(){
        add_action('init', array($this, "set_up_DB"));
        add_action('init', array($this, "create_survey_page"));
        add_action('admin_menu', array($this, "add_admin_bar_menu"));
        add_action( 'widgets_init', array($this, 'myplugin_register_widgets' ));
    }

    function myplugin_register_widgets() {
        register_widget( 'SaySoRewardsWidget' );
    }

    function set_up_DB(){
        global $wpdb;
        $table_name = $wpdb->prefix . "RFG";
        $result = $wpdb->get_results("SELECT _id from $table_name WHERE `_id` IS NOT NULL");
        if(count($result) <= 0) {//table doesn't exists yet
            //create table. _id defaults to ''
            $charset_collate = $wpdb->get_charset_collate();
            // add -> "activated Bool DEFAULT FALSE NOT NULL," to DB if going json route
            $sql = "CREATE TABLE $table_name (
                      id mediumint(9) NOT NULL AUTO_INCREMENT,
                      _id text DEFAULT '' NOT NULL,
                      secret text DEFAULT '' NOT NULL,
                      surveyFrameHeight INT DEFAULT NULL,
                      surveyFrameWidth INT DEFAULT NULL,
                      PRIMARY KEY  (id)
                    ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            $wpdb->insert($table_name, array("_id"=>""));
        }
    }

    static function create_survey_page(){
        global $wpdb;
        $table_name = $wpdb->prefix . "RFG";
        $id = $wpdb->get_var("SELECT _id FROM $table_name WHERE id = 1");
        $secret = $wpdb->get_var("SELECT secret FROM $table_name WHERE id = 1");
        $frameHeight = $wpdb->get_var("SELECT surveyFrameHeight FROM $table_name WHERE id = 1");
        $frameWidth = $wpdb->get_var("SELECT surveyFrameWidth FROM $table_name WHERE id = 1");
        if ($id != null && $secret != null){
            if ($frameHeight != null && $frameWidth != null) {
                $pages = apply_filters('woocommerce_create_pages', array(
                    'survey' => array(
                        'name' => _x('survey', 'Page slug', 'SaySoSurvey'),
                        'title' => _x('', 'Page title', 'SaySoSurvey'),
                        'content' => '<iframe style="border: none; width: '.$frameWidth.'px; height: '.$frameHeight.'px; position: absolute; left: 0;" src="https://www.saysoforgood.com/wp/wpSurveyRouter.zul?id=' . $id . '"></iframe><div style="position: relative; height: '.($frameHeight - 50).'px; z-index:-1"></div>'
                    )
                ));
            } else {
                $pages = apply_filters('woocommerce_create_pages', array(
                    'survey' => array(
                        'name' => _x('survey', 'Page slug', 'SaySoSurvey'),
                        'title' => _x('', 'Page title', 'SaySoSurvey'),
                        'content' => '<iframe style="border: none; width: 100%; height: 800px; position: absolute; left: 0;" src="https://www.saysoforgood.com/wp/wpSurveyRouter.zul?id=' . $id . '"></iframe><div style="position: relative; height: 750px; z-index:-1"></div>'
                    )
                ));
            }
            foreach ( $pages as $key => $page ) {
                $slug = esc_sql( $page['name'] );
                $option = 'SaySoSurvey_' . $key . '_page_id';
                $page_title = $page['title'];
                $page_content = $page['content'];
                $post_parent = '';

                $option_value     = get_option( $option );

                if ( $option_value > 0 ) {
                    $page_object = get_post( $option_value );

                    if ( 'page' === $page_object->post_type && ! in_array( $page_object->post_status, array( 'pending', 'trash', 'future', 'auto-draft' ) ) ) {
                        // Valid page is already in place
                        return $page_object->ID;
                    }
                }

                if ( strlen( $page_content ) > 0 ) {
                    // Search for an existing page with the specified page content (typically a shortcode)
                    $valid_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' ) AND post_content LIKE %s LIMIT 1;", "%{$page_content}%" ) );
                } else {
                    // Search for an existing page with the specified page slug
                    $valid_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' )  AND post_name = %s LIMIT 1;", $slug ) );
                }

                $valid_page_found = apply_filters( 'woocommerce_create_page_id', $valid_page_found, $slug, $page_content );

                if ( $valid_page_found ) {
                    if ( $option ) {
                        update_option( $option, $valid_page_found );
                    }
                    return $valid_page_found;
                }

                // Search for a matching valid trashed page
                if ( strlen( $page_content ) > 0 ) {
                    // Search for an existing page with the specified page content (typically a shortcode)
                    $trashed_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_content LIKE %s LIMIT 1;", "%{$page_content}%" ) );
                } else {
                    // Search for an existing page with the specified page slug
                    $trashed_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_name = %s LIMIT 1;", $slug ) );
                }

                if ( $trashed_page_found ) {
                    $page_id   = $trashed_page_found;
                    $page_data = array(
                        'ID'             => $page_id,
                        'post_status'    => 'publish',
                    );
                    wp_update_post( $page_data );
                } else {
                    $page_data = array(
                        'post_status'    => 'publish',
                        'post_type'      => 'page',
                        'post_author'    => 1,
                        'post_name'      => $slug,
                        'post_title'     => $page_title,
                        'post_content'   => $page_content,
                        'post_parent'    => $post_parent,
                        'comment_status' => 'closed'
                    );
                    $page_id = wp_insert_post( $page_data );
                }

                if ( $option ) {
                    update_option( $option, $page_id );
                }
                $url = get_permalink($page_id);
                file_get_contents("https://www.saysoforgood.com/wp/setPermalink.zul?id=".$id."&secret=".$secret."&url=".$url);
                return $page_id;
            }
        }
    }

    function reset_survey_page(){
        wp_trash_post($this->create_survey_page());
        $this->create_survey_page();
    }

    function add_admin_bar_menu() {
        add_menu_page( "SaySo Rewards", "SaySo Rewards", "administrator", "SaySoSurveys", array($this, "admin_plugin_page"), "dashicons-desktop", 40);
    }

    function admin_plugin_page(){
        global $wpdb;
        $baseURL = "https://www.saysoforgood.com/wp/wpPlugin.zul";
        $baseURLLink = "https://www.saysoforgood.com/wp/wpPluginLink.zul";
        $table_name = $wpdb->prefix . "RFG";
        $id = $wpdb->get_var("SELECT _id FROM $table_name WHERE id = 1");
        $secret = $wpdb->get_var("SELECT secret FROM $table_name WHERE id = 1");
        $urlHost = $_SERVER['HTTP_HOST'];
        $paramURL = $baseURL."?id=".$id."&url=".$urlHost."&secret=".$secret;
        $paramURLLink = $baseURLLink."?id=".$id."&url=".$urlHost."&secret=".$secret;
        $iframe = "<iframe src=$paramURL height='850px' width='100%'></iframe>"; //main dashboard iframe
        $iframeLink = "<iframe src=$paramURLLink height='140px' width='270px'></iframe>"; //link status
        if( isset( $_GET[ 'tab' ] ) ) {
            $active_tab = $_GET[ 'tab' ];
        } else {
            $active_tab = "dashboard";
        }
        ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=SaySoSurveys&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
            <a href="?page=SaySoSurveys&tab=linkAccount" class="nav-tab <?php echo $active_tab == 'linkAccount' ? 'nav-tab-active' : ''; ?>">Link Account</a>
            <a href="?page=SaySoSurveys&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
        </h2>
        <?php
        if($active_tab == "dashboard") {
            echo $iframe;
        } elseif ($active_tab == "linkAccount") {
        ?>
            <form method="post" style="margin-top: 20px">
                <input type="hidden" name="oscimp_hidden" value="Y">
                <label style="font-weight: bold">Activation Code: </label>
                <input type="text" name="activationString" style="width: 420px">
                <input type="submit" value="Link Account!">
            </form>
            <p>(This will link your WordPress plugin with our servers)</p>
            <p>Once you have linked your account, go back to the 'Dashboard' tab above to complete the setup.</p>
        <?php
            echo $iframeLink;
            if($_POST['oscimp_hidden'] == 'Y') {
                $cleanString = sanitize_text_field($_POST['activationString']);
                if(strlen($cleanString) == 57) {
                    //Form data sent
                    $codes = explode(":", $cleanString);
                    $wpdb->update($table_name, array("_id" => $codes[0], "secret" => $codes[1]), array("id" => 1));
                    //header("Location: ?page=SaySoSurveys&tab=dashboard");
                    echo "<script type='text/javascript'>window.location='?page=SaySoSurveys&tab=dashboard';</script>";
                    exit;
                }
            } else {
                //Normal page display
            }
        } elseif ($active_tab == "settings") {
        ?>
            <div style="font-weight: bold; font-size: 20px; margin-top: 20px;">Adjust Survey Frame Size</div>
            <form method="post" style="margin-top: 20px">
                <input type="hidden" name="oscimp_hidden" value="Frame">
                <div style="display: inline-block; width: 60px; font-weight: bold">Width:</div>
                <input type="number" name="frameWidth" id="frameWidth" style="width: 100px" placeholder="in px" value="<?php echo $wpdb->get_var("SELECT surveyFrameWidth FROM $table_name WHERE id = 1"); ?>">
                <div style="display: inline-block">px</div>
                <br/>
                <div style="display: inline-block; width: 60px; font-weight: bold">Height:</div>
                <input type="number" name="frameHeight" id="frameHeight" style="width: 100px" placeholder="in px" value="<?php echo $wpdb->get_var("SELECT surveyFrameHeight FROM $table_name WHERE id = 1"); ?>">
                <div style="display: inline-block">px</div>
                <br/>
                <br/>
                <input type="submit" value="Adjust Survey Size" id="submitFrameSize" style="width: 150px;">
                <br/>
                <input type="button" value="Reset Survey Size" style="width: 150px;" onclick="
                    document.getElementById('frameHeight').value=0;
                    document.getElementById('frameWidth').value=0;
                    document.getElementById('submitFrameSize').click();
                ">
            </form>
        <?php
            if($_POST['oscimp_hidden'] == 'Frame') {
                if (!$this->mycode_table_column_exists($table_name, "surveyFrameHeight")){
                    $wpdb->query("ALTER TABLE $table_name ADD surveyFrameHeight INT DEFAULT 800 NOT NULL");
                }
                if (!$this->mycode_table_column_exists($table_name, "surveyFrameWidth")){
                    $wpdb->query("ALTER TABLE $table_name ADD surveyFrameWidth INT DEFAULT 650 NOT NULL");
                }
                $frameWidth = sanitize_text_field($_POST['frameWidth']);
                $frameHeight = sanitize_text_field($_POST['frameHeight']);
                $surveyFrameHeight = $frameHeight;
                $surveyFrameWidth = $frameWidth;
                if (is_int($surveyFrameWidth) && is_int($surveyFrameHeight)) {
                    if ($frameHeight == 0) {
                        $surveyFrameHeight = null;
                    }
                    if ($frameWidth == 0) {
                        $surveyFrameWidth = null;
                    }
                    $wpdb->update($table_name, array("surveyFrameHeight" => $surveyFrameHeight, "surveyFrameWidth" => $surveyFrameWidth), array("id" => 1));
                    $this->reset_survey_page();
                    echo "<script type='text/javascript'>
                        if ($frameHeight == 0)
                            document.getElementById('frameHeight').value = '';
                        else
                            document.getElementById('frameHeight').value = $frameHeight;
                        if ($frameWidth == 0)
                            document.getElementById('frameWidth').value = '';
                        else
                            document.getElementById('frameWidth').value = $frameWidth;
                    </script>";
                }
            } else {
                //Normal page display
            }
        }
    }

    function mycode_table_column_exists( $table_name, $column_name ) {
        global $wpdb;
        $column = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
            DB_NAME, $table_name, $column_name
        ) );
        if ( ! empty( $column ) ) {
            return true;
        }
        return false;
    }
}


SaySoRewards::getInstance();

?>