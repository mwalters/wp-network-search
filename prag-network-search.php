<?php
/*
Plugin Name: Network Wide Search
Plugin URI: https://www.pragmatticode.com
Description: Search entire network of sites
Version: 1.0
Author: Pragmatticode
Author URI: https://www.pragmatticode.com
*/

if (!class_exists('PragNetworkSearch')) {
    class PragNetworkSearch {

        private $settings;
        private $td = 'prag-network-search';
        private $dbVersion = '13';
        private $dbTable;
        private $searchResults;
        private $totalResults;
        private $recordsPerPage;

        public function __construct() {
            global $wpdb;

            // Load settings
            $this->get_settings();
            $this->dbTable = $wpdb->base_prefix . 'prag_post_index';
            $this->searchResults = array();
            $this->recordsPerPage = 10;

            // Set location values
            $this->path = untrailingslashit( plugin_dir_path( __FILE__ ) );
            $this->url  = untrailingslashit( plugin_dir_url( __FILE__ ) );

            $this->db_check();

            // Hook in where necessary
            add_action('network_admin_menu', array(&$this, 'settings'));
            add_action('admin_post_network_search-network-settings',  array(&$this, 'update_settings'));
            add_action('save_post', array(&$this, 'save_post'));

           $this->addRoute();
        }

        /**
         * Save posts to a network wide table for indexing
         */
        public function save_post($postId) {
            global $wpdb;
            $post = get_post($postId);

            // Make sure we have a post object suitable for indexing
            if (is_object($post)) {
                $post = $this->get_searchable_post($post);
            } else {
                return;
            }

            // If the post is not set to `publish` then make sure it is not in the index
            if ($post->post_status != 'publish') {
                $this->delete_post($post->post_id);
                return;
            }

            // If the post type is one of the configured post types to index, then add it to the index
            if (in_array($post->post_type, $this->settings['post_types'])) {
                $test = $wpdb->replace(
                    $this->dbTable,
                    array(
                        'site_id'      => $post->site_id,
                        'post_id'      => $post->post_id,
                        'post_date'    => $post->post_date,
                        'post_status'  => $post->post_status,
                        'post_type'    => $post->post_type,
                        'post_title'   => $post->post_title,
                        'post_excerpt' => $post->post_excerpt,
                        'post_content' => $post->post_content,
                        'permalink'    => $post->permalink,
                        'thumbnail'    => $post->thumbnail
                    ),
                    array(
                        '%d',
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s'
                    )
                );
            } else {
                return;
            }
        }

        /**
         * Deletes post from network wide indexing table
         */
        public function delete_post($postId) {
            global $wpdb;

            $siteId = get_current_blog_id();

            if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $this->dbTable . ' WHERE site_id = %d AND post_id = %d', $siteId, $postId ) ) ) {
                $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->dbTable . ' WHERE site_id = %d AND post_id = %d', $siteId, $postId ) );
            }
        }

        /**
         * Return a post object suitable for putting into network wide table
         * @return object Post
         */
        private function get_searchable_post($post) {
            $returnPost = (object) array(
                'site_id'      => get_current_blog_id(),
                'post_id'      => $post->ID,
                'post_date'    => $post->post_date,
                'post_status'  => $post->post_status,
                'post_type'    => $post->post_type,
                'post_title'   => apply_filters('the_title', $post->post_title),
                'post_excerpt' => ($post->post_excerpt != '') ? $post->post_excerpt : wp_trim_words($post->post_content),
                'post_content' => do_shortcode(apply_filters('the_content', $post->post_content), $post->ID),
                'permalink'    => get_permalink($post->ID)
            );

            if (has_post_thumbnail( $post->ID ) ) {
                $returnPost->thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ))[0];
            }

            return $returnPost;
        }

        /**
         * Perform search on database
         * @return array Search results
         */
        private function getSearchResults() {
            global $wpdb;

            $searchString = trim(urldecode(substr($_GET['q'], 0)));

            if (isset($_GET['searchPage']) && trim($_GET['searchPage']) != '') {
                $page = trim($_GET['searchPage']);
            } else {
                $page = 0;
            }

            if ($searchString == '') {
                return array();
            }

            $searchMode = $this->getSearchMode($searchString);

            $this->totalResults = $wpdb->get_results($wpdb->prepare("SELECT count(id) AS recordCount
                      FROM vdh8_prag_post_index
                      WHERE MATCH(post_title, post_excerpt, post_content)
                      AGAINST ('%s' IN " . $searchMode . ");", $searchString))[0]->recordCount;

            $offSet = $this->recordsPerPage * $page;

            $results = $wpdb->get_results($wpdb->prepare("SELECT post_title, post_excerpt, permalink, thumbnail
                      FROM vdh8_prag_post_index
                      WHERE MATCH(post_title, post_excerpt, post_content)
                      AGAINST ('%s' IN " . $searchMode . ") LIMIT " . $offSet . "," . $this->recordsPerPage . ";", $searchString));

            if (is_array($results) && count($results) > 0) {
                $this->searchResults = $results;
            } else {
                $this->searchResults = array();
            }
        }

        /**
         * Determine best MySQL search mode to use based on search string
         */
        private function getSearchMode($searchString = '') {
            if ($searchString === '') {
                return 'NATURAL LANGUAGE MODE';
            }

            if (strpos($searchString, '-') !== false || strpos($searchString, '+')) {
                return 'BOOLEAN MODE';
            }

            if (strlen($searchString) < 10) {
                return 'NATURAL LANGUAGE MODE WITH QUERY EXPANSION';
            }

            return 'NATURAL LANGUAGE MODE';
        }

        /**
         * Adds a search results page to the site
         */
        public function makeSearchPage($posts) {
            global $wp, $wp_query;

            if (count($posts) == 0) {

                $this->getSearchResults();

                if (count($this->searchResults) > 0) {
                    ob_start();
                    require_once('templates/search-results.php');
                    $this->searchResultsHtml = ob_get_contents();
                    ob_end_clean();

                } else {
                    $this->searchResultsHtml = 'No results found.';
                }

                $post = new stdClass;
                $post->ID                    = -1;
                $post->post_author           = 0;
                $post->post_date             = current_time('mysql');
                $post->post_date_gmt         = current_time('mysql', 1);
                $post->post_content          = $this->searchResultsHtml;
                $post->post_title            = 'Search Results';
                $post->post_excerpt          = '';
                $post->post_status           = 'publish';
                $post->comment_status        = 'closed';
                $post->ping_status           = 'closed';
                $post->post_password         = '';
                $post->post_name             = 'sitesearch';
                $post->to_ping               = '';
                $post->pinged                = '';
                $post->modified              = current_time('mysql');
                $post->modified_gmt          = current_time('mysql', 1);
                $post->post_content_filtered = '';
                $post->post_parent           = 0;
                $post->guid                  = get_home_url('/sitesearch');
                $post->menu_order            = 0;
                $post->post_type             = 'page';
                $post->post_mime_type        = '';
                $post->comment_count         = 0;

                // set filter results
                $posts = array($post);

                // reset wp_query properties to simulate a found page
                unset($wp_query->query['error']);
                $wp_query->is_page             = TRUE;
                $wp_query->is_singular         = TRUE;
                $wp_query->is_home             = FALSE;
                $wp_query->is_archive          = FALSE;
                $wp_query->is_category         = FALSE;
                $wp_query->query_vars['error'] = '';
                $wp_query->is_404              = FALSE;

                add_action('template_redirect', function() {
                    if (file_exists(TEMPLATEPATH.'/page-searchresults.php')) {
                        include(TEMPLATEPATH . '/page-searchresults.php');
                        exit;
                    }
                });
            }

            return ($posts);
        }

        /**
         * Adds a URL for a search results page to the site
         */
        public function addRoute() {
            $url = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

            if (substr($url, 0, strlen('sitesearch')) == 'sitesearch') {
                add_filter('the_posts', array(&$this, 'makeSearchPage'));
            }
        }

        /**
         * Adds settings page to Network admin
         * @return void
         */
        public function settings() {
            add_submenu_page(
                'settings.php',
                __('Network Search', $this->td),
                __('Network Search', $this->td),
                'manage_network_options',
                'network_search-network-settings',
                array( &$this, 'render_settings_form' )
            );

            return;
        }

        /**
         * Renders settings form
         * @return void]
         */
        public function render_settings_form() {
            require_once( 'templates/form-settings.php' );

            return;
        }

        /**
         * Retrieves settings from _options table
         * @return void
         */
        private function get_settings() {
            $this->settings = maybe_unserialize( get_site_option( 'prag-network_search-settings' ) );
            return;
        }

        /**
         * Save settings to _options table
         * @return void
         */
        private function save_settings() {
            update_site_option( 'prag-network_search-settings', maybe_serialize( $this->settings ) );

            return;
        }

        /**
         * Processes form fields from settings form
         * @return void
         */
        private function process_settings_form() {
            if (isset($_POST['save-network_search-settings']) && $_POST['save-network_search-settings'] == 1) {
                $types = explode(',', trim($_POST['post_types']));
                $post_types = array_map(function($item) {
                    return trim($item);
                }, $types);
                $this->settings['post_types'] = $post_types;
            }

            return;
        }

        /**
         * Updates network settings
         */
        public function update_settings() {
            check_admin_referer('save-network_search-settings');

            if ( ! current_user_can( 'manage_network_options' ) ) { wp_die( 'Access denied' ); }

            $this->process_settings_form();
            $this->save_settings();

            wp_redirect( admin_url( 'network/settings.php?page=network_search-network-settings' ) );

            exit;
        }

        /**
         * Checks database version to ensure we're up to date on schema
         */
        public function db_check() {
            $installed_ver = get_site_option( "prag_network_search_db_version" );

            if ( ! is_admin() && ! is_network_admin() ) {
                return;
            }

            if ( $installed_ver != $this->dbVersion ) {
                $sql = "CREATE TABLE $this->dbTable (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    site_id bigint(20) unsigned NOT NULL,
                    post_id bigint(20) unsigned NOT NULL,
                    post_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                    post_status varchar(20) NOT NULL,
                    post_type varchar(20) NOT NULL,
                    post_title text NOT NULL,
                    post_excerpt text DEFAULT '' NOT NULL,
                    post_content longtext DEFAULT '' NOT NULL,
                    permalink text NOT NULL,
                    thumbnail text,
                    UNIQUE KEY id (id),
                    UNIQUE KEY site_post (site_id, post_id),
                    FULLTEXT INDEX searching (post_title, post_excerpt, post_content)
                ) ENGINE=MyISAM;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);

                update_site_option("prag_network_search_db_version", $this->dbVersion);
            }
        }
    }
}

// Create object if needed
if ( ! @$PragNetworkSearch && function_exists( 'add_action' )) { $PragNetworkSearch = new PragNetworkSearch(); }
