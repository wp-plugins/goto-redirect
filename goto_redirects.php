<?php
/*
Plugin Name: GoTo Redirect
Plugin URI: http://en.bainternet.info
Description: Track your affiliate links with custom YOURDOMAIN.COM/Go/@#$% links
Version: 0.1
Author: Bainternet
Author URI: http://en.bainternet.info
Author Email: admin@bainternet.info
License:

  Copyright 2012 Bainternet (admin@bainternet.info)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('GoToRedirect')){
        /**
         * @todo add a  better tracking per hit.
         */
        class GoToRedirect {

                /**
                 * Vars
                 * 
                 */
                protected $_status;
                
                
                /**
                 * Constructor
                 */
                public function __construct() {
                        $this->_status = apply_filters('GoToRedirect_status',301);
                        //register post type
                        add_action( 'init', array($this,'register_cpt_goto' ));
                        //add metabox
                        add_action('add_meta_boxes', array($this, 'add_meta_box'));
                        //save meta box
                        add_action('save_post', array($this, 'save_url'));
                        add_filter( 'wp_insert_post_data', array($this, 'update_key_slug'), 10, 1 );

                        //redirect
                        add_filter( "single_template",array($this, "goto_redirect" ) );                        

                        //custom columns
                        add_filter('manage_edit-goto_columns', array($this, 'custom_column'));  
                        add_action('manage_goto_posts_custom_column', array($this, 'column_content'), 10, 2);

                        add_filter( 'plugin_row_meta', array($this,'_my_plugin_links'), 10, 2 );

                }

                public function _my_plugin_links($links, $file) { 
                        $plugin = plugin_basename(__FILE__);  
                        if ($file == $plugin) // only for this plugin 
                                return array_merge( $links, 
                            array( '<a href="http://en.bainternet.info/category/plugins">' . __('Other Plugins by this author' ) . '</a>' ), 
                            array( '<a href="http://wordpress.org/support/plugin/goto-redirect">' . __('Plugin Support') . '</a>' ), 
                            array( '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=K4MMGF5X3TM5L" target="_blank">' . __('Donate') . '</a>' ) 
                        ); 
                        return $links;
                }
                
                public function custom_column($columns_def) {  
                        $columns['cb'] = '<input type="checkbox" />';
                        $columns['title'] = _x('Link name', 'column name');
                        $columns['_url_key'] = __('URL Key');
                        $columns['views'] = __('Hits');
                        $columns['thumb'] = __('Target Thumb');
                        $columns['author'] = __('Author');
                        $columns['date'] = _x('Created at', 'column name');
                       return $columns; 

                }  
                  
                public function column_content($column_name, $post_id) {  
                        global $wpdb;  
                        switch ($column_name) {  
                                case '_url_key':  
                                        $key = get_post_meta($post_id, '_url_key', true);
                                        $target = get_post_meta($post_id, '_target_url', true);
                                        if ($key) {  
                                                echo sprintf('<a target="_blank" href="%s" title="Original URL">%s</a>', get_permalink($post_id), $key);
                                        }  
                                        break;
                                 case 'views':  
                                        $views = get_post_meta($post_id, '_goto_views', true);
                                        if (is_array($views) && isset($views['views']))
                                                echo $views['views'];
                                        else
                                                echo 0;
                                        break;
                                case 'thumb':
                                        $target = get_post_meta($post_id, '_target_url', true);
                                        $title = esc_attr(get_the_title($post_id));
                                        $img = '<img src="http://s.wordpress.com/mshots/v1/' . urlencode($target) . '?w=80&h=80" alt="' . $title . '"/>';
                                        echo $img;
                                        break;
                       }
                }

                public function invalid_key($location, $errnum) {
                        return $location . '&goto_message=2';
                }

                public function invalid_url($location, $errnum) {
                        return $location . '&goto_message=1' ;
                }

                public function add_meta_box(){
                        add_meta_box("goto-meta", "Short URL", array($this, 'meta_box_content'), 'goto', "normal", "high");
                }

                public function update_message($message_num){
                        switch ($message_num) {
                                case 1:
                                        echo '<div class="updated"><p>'.__("Target URL is not valid").'</p></div>';
                                        break;
                                case 2:
                                        echo '<div class="updated"><p>'.__("Your custom key is already existed so we generated other key").'</p></div>';
                                        break;
                        }
                }

                public function update_key_slug($data,$postarr){
                        if ('goto' == $data['post_type']){
                                if (isset($_POST['_url_key']))
                                        $data['post_name'] = sanitize_text_field($_POST['_url_key']);
                        }
                        return $data;
                }

                public function meta_box_content() {
                        global $post;
                        wp_nonce_field('goto_nonce', 'goto_nonce');
                        //get saved data
                        $_url_key = get_post_meta($post->ID, '_url_key', true);
                        $_target_url = get_post_meta($post->ID, '_target_url', true);
                        //show update message if exists
                        if (isset($_GET['goto_message'])|| !empty($_GET['goto_message'])) {
                                $this->update_message((int) $_GET['goto_message']);
                        }
                        //print out fields
                        ?>
                        <p>
                            <label><?php echo __('Target URL') ?>:</label>
                            <input name="_target_url" type="text" style="width: 89%;" value="<?php echo $_target_url ?>" /><br/>
                            <?php _e('Enter the target URL here.'); ?>
                        </p>

                        <p>
                            <label><?php echo __('URL Key') ?>:</label>
                            <input name="_url_key" type="text" value="<?php echo $_url_key ?>" /><br/>
                            <?php _e('You can put your custom url key here if wanted!'); ?>
                        </p>
                        <?php
                        if (!empty($_url_key)) {
                                $link = get_permalink($post->ID);
                            ?>
                            <p>
                                <label><?php echo __('Your Goto url') ?>:</label>
                                <input style="width: 50%;" type="text" value="<?php echo $link; ?>" />
                                <a target="_blank" href="<?php echo $link; ?>">Try it</a>
                            </p>
                                <?php
                        }
                }

                public function save_url($post_id) {
                        global $post;
                        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                            return;

                        //only our post type
                        if ("goto" != get_post_type( $post_id ))
                                return;

                        // if our nonce isn't there, or we can't verify it, bail
                        if (!wp_verify_nonce($_POST['goto_nonce'], 'goto_nonce'))
                                return;

                        // if our current user can't edit this post, bail
                        if (!current_user_can('edit_post'))
                                return;

                        //Also, if the url is invalid, add custom message
                        if (!isset($_POST['_target_url']) || !preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', esc_url_raw($_POST['_target_url']))) {
                            add_filter('redirect_post_location', array($this, 'invalid_url'));
                            return;
                        }
                        if (!isset($_POST['_url_key']))
                                $_POST['_url_key'] = $post->post_title;
                        
                        $this->set_url_key($post_id);

                        $old_url = get_post_meta($post_id, '_target_url', true);
                        $new_url = esc_url_raw($_POST['_target_url']);
                        if ($old_url != $new_url){
                                update_post_meta($post_id, '_target_url', $new_url);
                        }
                }

                public function set_url_key($post_id){
                        global $post;
                        $_url_key = $_POST['_url_key'];
                        $old_key = get_post_meta($post_id, '_url_key', true);
                        if ($_url_key != $old_key) {
                                //We are updating post, and the key is not changed so it's not necessary to save again
                                //If our key already exists! Let regenerate till we get a new key
                                while ($this->_key2url($_url_key)) {
                                    $_url_key = base_convert(time() + rand(1, 10000), 10, 36);
                                    add_filter('redirect_post_location', array($this, 'invalid_key'));
                                }
                                update_post_meta($post_id, '_url_key', $_url_key);
                        }
                }

                public function register_cpt_goto() {

                    $labels = array( 
                        'name' => _x( 'GoTos', 'goto' ),
                        'singular_name' => _x( 'GoTo', 'goto' ),
                        'add_new' => _x( 'Add New', 'goto' ),
                        'add_new_item' => _x( 'Add New GoTo', 'goto' ),
                        'edit_item' => _x( 'Edit GoTo', 'goto' ),
                        'new_item' => _x( 'New GoTo', 'goto' ),
                        'view_item' => _x( 'View GoTo', 'goto' ),
                        'search_items' => _x( 'Search GoTos', 'goto' ),
                        'not_found' => _x( 'No gotos found', 'goto' ),
                        'not_found_in_trash' => _x( 'No gotos found in Trash', 'goto' ),
                        'parent_item_colon' => _x( 'Parent GoTo:', 'goto' ),
                        'menu_name' => _x( 'GoTos', 'goto' ),
                    );

                    $args = array( 
                        'labels' => $labels,
                        'hierarchical' => false,
                        'description' => 'Short Url post type',
                        'supports' => array( 'title', 'author'),                        
                        'public' => true,
                        'show_ui' => true,
                        'show_in_menu' => true,
                        'menu_position' => 65,
                        'menu_icon' => 'http://i.imgur.com/a8GQC.png',
                        'show_in_nav_menus' => false,
                        'publicly_queryable' => true,
                        'exclude_from_search' => true,
                        'has_archive' => false,
                        'query_var' => true,
                        'can_export' => true,
                        'rewrite' => array('slug' => 'Go','with_front' => false),
                        'capability_type' => 'post'
                    );

                    register_post_type( 'goto', $args );
                }


                private function _key2url($key,$pid = false) {
                        global $wpdb;
                        $sql = "
                            SELECT m.post_id, p.post_title as url
                                FROM {$wpdb->prefix}postmeta as m
                                LEFT JOIN {$wpdb->prefix}posts as p ON m.post_id=p.id
                                WHERE  m.meta_key='_url_key' AND m.meta_value='%s'
                            ";
                        $result = $wpdb->get_row($wpdb->prepare($sql, $key));
                        if (!$result) {
                            return false;
                        }
                        $url = get_post_meta($result->post_id,'_target_url',true);
                        if ($pid)
                                return array('post_id' => $result->post_id, 'url' => $url);
                        return $url;
                }

                public function get_guest_browser(){
                        $agent = $_SERVER['HTTP_USER_AGENT'];
                        $browserArray = array(
                                'Windows Mobile' => 'IEMobile',
                                'Android Mobile' => 'Android',
                                'iPhone Mobile' => 'iPhone',
                                'Firefox' => 'Firefox',
                                'Google Chrome' => 'Chrome',
                                'Internet Explorer' => 'MSIE',
                                'Opera' => 'Opera',
                                'Safari' => 'Safari'
                        ); 
                        foreach ($browserArray as $k => $v) {

                            if (preg_match("/$v/", $agent)) {
                                 break;
                            }else{
                                        $k = "Browser Unknown";
                            }
                        }
                        return $k;
                }

                public function get_guest_os(){
                        $agent = $_SERVER['HTTP_USER_AGENT'];
                        // Create the Associative Array for the Operating Systems to sniff out
                        $osArray = array(
                                'Windows 98' => '(Win98)|(Windows 98)',
                                'Windows 2000' => '(Windows 2000)|(Windows NT 5.0)',
                                'Windows ME' => 'Windows ME',
                                'Windows XP' => '(Windows XP)|(Windows NT 5.1)',
                                'Windows Vista' => 'Windows NT 6.0',
                                'Windows 7' => '(Windows NT 6.1)|(Windows NT 7.0)',
                                'Windows NT 4.0' => '(WinNT)|(Windows NT 4.0)|(WinNT4.0)|(Windows NT)',
                                'Linux' => '(X11)|(Linux)',
                                'Mac OS' => '(Mac_PowerPC)|(Macintosh)|(Mac OS)'
                        ); 
                        foreach ($osArray as $k => $v) {

                            if (preg_match("/$v/", $agent)) {
                                 break;
                            }   else {
                                 $k = "Unknown OS";
                            }
                        } 
                        return $k;
                }

                public function get_guest_ip(){
                        return $_SERVER['REMOTE_ADDR'];
                }

                //check if user has viewed already today
                public function get_views($post_id){
                        $guest_ip = $this->get_guest_ip();
                        $today = getdate();
                        $u_meta = get_post_meta($post_id, '_goto_views',true);
                        if ($u_meta){
                                if (is_array($u_meta)){
                                        $new_u_meta = $u_meta;
                                        $day = $today['mday'];
                                        $month = $today['mon'];
                                        
                                        if ($u_meta['month'] == $month){
                                                if ($u_meta['day'] == $day){
                                                        if (in_array($guest_ip,$u_meta['ips'])){
                                                                return $u_meta['views'];
                                                        }else{
                                                                //add current ip
                                                                $new_u_meta['ips'][] = $guest_ip;
                                                                //add view to count
                                                                $new_u_meta['views'] = $u_meta['views'] + 1;
                                                                update_post_meta($post_id, '_goto_views',$new_u_meta);
                                                                return $new_u_meta['views'];
                                                        }
                                                }else{
                                                        //set new day
                                                        $new_u_meta['day'] = $day;
                                                        //empty old ips
                                                        $new_u_meta['ips'] = array();
                                                        //add current ip
                                                        $new_u_meta['ips'][] = $guest_ip;
                                                        //add view to count
                                                        $new_u_meta['views'] = $u_meta['views'] + 1;
                                                        update_post_meta($post_id, '_goto_views',$new_u_meta);
                                                        return $new_u_meta['views'];
                                                }
                                        }else{
                                                //set new month
                                                $new_u_meta['month'] = $month;
                                                //set new day
                                                $new_u_meta['day'] = $day;
                                                //empty old ips
                                                $new_u_meta['ips'] = array();
                                                //add current ip
                                                $new_u_meta['ips'][] = $guest_ip;
                                                //add view to count
                                                $new_u_meta['views'] = $u_meta['views'] + 1;
                                                update_post_meta($post_id, '_goto_views',$new_u_meta);
                                                return $new_u_meta['views'];
                                        }
                                }else{
                                        //set new month
                                        $new_u_meta['month'] = $month;
                                        //set new day
                                        $new_u_meta['day'] = $day;
                                        //empty old ips
                                        $new_u_meta['ips'] = array();
                                        //add current ip
                                        $new_u_meta['ips'][] = $guest_ip;
                                        //add view to count
                                        $new_u_meta['views'] =  1;
                                        update_post_meta($post_id, '_goto_views',$new_u_meta);
                                        return $new_u_meta['views'];
                                }
                        }else{
                                //set new month
                                $new_u_meta['month'] = $month;
                                //set new day
                                $new_u_meta['day'] = $day;
                                //empty old ips
                                $new_u_meta['ips'] = array();
                                //add current ip
                                $new_u_meta['ips'][] = $guest_ip;
                                //add view to count
                                $new_u_meta['views'] =  1;
                                update_post_meta($post_id, '_goto_views',$new_u_meta);
                                return $new_u_meta['views'];
                        }
                }//end get views

                public function goto_redirect($single_template) {
                
                        global $post,$wp_query;
                        if ($post->post_type == 'goto') {
                                $location = get_post_meta($post->ID, '_target_url', true);
                                if ($location){
                                        $this->get_views($post->ID);
                                        do_action('goto_redirect_tracked_hit',$post->ID);
                                        wp_redirect( $location, $this->_status );
                                        exit;
                                }
                        }
                        return $single_template;
                }

        } // end class
}//end if
new GoToRedirect();