<?php

/*
  Plugin Name: Purge Varnish
  Description: Purge Varnish Cache provides integration between your WordPress site and multiple Varnish Cache servers. Purge Varnish Cache sends a PURGE request to the URL of a page or post every time based on configured actions and trigger by site administrator. Varnish is a web application accelerator also known as a caching HTTP reverse proxy.
  Version: 2.5
  Author: Dsingh <dev.firoza@gmail.com>
  Author URI: https://profiles.wordpress.org/devavi
  License: GPLv2+
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Purge_Varnish {

    /**
     * Constant
     */
    const PURGE_VARNISH_DEFAULT_TREMINAL = '127.0.0.1:6082';
    const PURGE_VARNISH_DEFAULT_TIMEOUT = 100;
    const PURGE_VARNISH_COUNT_DEFAULT_URLS = 7;

    /*
     * Constructor
     */

    function __construct() {
        add_action('admin_menu', array($this, 'purge_varnish_add_menu'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'purge_varnish_settings_link'));
        register_activation_hook(__FILE__, array($this, 'purge_varnish_install'));
        register_deactivation_hook(__FILE__, array($this, 'purge_varnish_uninstall'));
    }

    /**
     * Actions perform at loading of admin menu
     */
    function purge_varnish_add_menu() {
        add_menu_page('Purge Varnish', 'Purge Varnish', 'manage_options', 'purge-varnish-settings', array($this, 'purge_varnish_page_file_path'), plugins_url('images/purge16x16.png', __FILE__), '2.2.9');

        if (current_user_can('manage_options')) {
            add_submenu_page('purge-varnish-settings', 'Terminal', 'Terminal', 'manage_options', 'purge-varnish-settings', array(
                $this, 'purge_varnish_page_file_path'));
            add_submenu_page('purge-varnish-settings', 'Purge all', 'Purge all', 'manage_options', 'purge-varnish-all', array(
                $this, 'purge_varnish_page_file_path'));

            add_submenu_page('purge-varnish-settings', 'Expire', 'Expire', 'manage_options', 'purge-varnish-expire', array(
                $this, 'purge_varnish_page_file_path'));
        }

        if (current_user_can('edit_posts')) {
            add_submenu_page('purge-varnish-settings', 'Purge URLs', 'Purge URLs', 'edit_posts', 'purge-varnish-urls', array(
                $this, 'purge_varnish_page_file_path'));
        }
    }

    /**
     * Implements hook for add settings link.
     */
    function purge_varnish_settings_link($links) {
        $settings_link = '<a href="' . esc_url(admin_url('/admin.php?page=purge-varnish-settings')) . '">' . __('Settings', 'Purge Varnish') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Implements hook for register styles and scripts.
     */
    function purge_varnish_register_styles() {
        wp_enqueue_style('purge-varnish', plugins_url('/css/purge_varnish.css', __FILE__), false, '1.0');
        wp_enqueue_script('purge-varnish', plugins_url('/js/purge_varnish.js', __FILE__), false, '1.0');
    }

    /**
     * Actions perform on loading of menu pages
     */
    function purge_varnish_page_file_path() {
        global $title;
        $screen = get_current_screen();

        if (strpos($screen->base, 'purge-varnish-settings') !== false) {
            require_once('includes/terminal.php');
        } elseif (strpos($screen->base, 'purge-varnish-all') !== false) {
            require_once('includes/purge_all.php');
        } elseif (strpos($screen->base, 'purge-varnish-expire') !== false) {
            require_once('includes/expire.php' );
        } elseif (strpos($screen->base, 'purge-varnish-urls') !== false) {
            require_once('includes/purge_urls.php');
        }
    }

    /**
     * Utilizes sockets to talk to varnish terminal and send the command to Varnish.
     */
    function purge_varnish_terminal_run($commands) {
        if (!is_array($commands)) {
            $commands = array($commands);
        }

        $uploads_path = wp_upload_dir();
        $basedir = $uploads_path['basedir'];

        // Prevent fatal errors if requirements don't have meet.
        if (!extension_loaded('sockets')) {
            $logdata = 'You need to enable/install sockets module.';
            $this->purge_varnish_debug($basedir, $logdata);
            error_log($log, 0);
            return false;
        }

        // Convert single commands to an array to handle everything in the same way.
        $ret = array();

        // Convert varnish_socket_timeout timeout into milliseconds.
        $timeout = get_option('varnish_socket_timeout', self::PURGE_VARNISH_DEFAULT_TIMEOUT);
        $seconds = (int) ($timeout / 1000);
        $microseconds = (int) ($timeout % 1000 * 1000);

        $terminals = explode(' ', get_option('varnish_control_terminal', self::PURGE_VARNISH_DEFAULT_TREMINAL));
        foreach ($terminals as $terminal) {
            list($server, $port) = explode(':', $terminal);
            $client = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
            socket_set_option($client, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $seconds, 'usec' => $microseconds));
            socket_set_option($client, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $seconds, 'usec' => $microseconds));

            if (@!socket_connect($client, $server, $port)) {
                $logdata = 'Unable to connect to server socket ' . $server . ':' . $port . ' -: ' . socket_strerror(socket_last_error($client));
                $this->purge_varnish_debug($basedir, $logdata);
                error_log($logdata, 0);
                $ret[$terminal] = FALSE;
                // If a varnish server is unavailable, check the next terminal.
                continue;
            }

            // If there is a CLI banner message (varnish >= 2.1.x), try to read it and
            // move on.
            if (floatval(get_option('varnish_version', 2.1)) > 2.0) {
                $status = $this->purge_varnish_read_socket($client);

                // Do we need to authenticate?
                // Require authentication.
                if ($status['code'] == 107) {
                    $secret = get_option('varnish_control_key', '');
                    $challenge = substr($status['msg'], 0, 32);
                    $pack = $challenge . "\x0A" . $secret . "\x0A" . $challenge . "\x0A";
                    $key = hash('sha256', $pack);
                    socket_write($client, "auth $key\n");
                    $status = $this->purge_varnish_read_socket($client);

                    if ($status['code'] != 200) {
                        $logdata = 'Authentication to server failed!';
                        $this->purge_varnish_debug($basedir, $logdata);
                        error_log($logdata, 0);
                    }
                }
            }

            foreach ($commands as $command) {
                $logdata = '';
                $this->purge_varnish_debug($basedir, $command);

                $result = socket_write($client, "$command\n");
                if ($status = $this->purge_varnish_execute_command($client, $command)) {
                    $ret[$terminal][$command] = $status;
                }
                if (is_array($status)) {
                    $this->purge_varnish_debug($basedir, json_encode($status));
                }
            }
            socket_close($client);
        }

        return $ret;
    }

    /**
     * Send command to varnish..
     */
    function purge_varnish_execute_command($client, $command) {
        // Send command and get response.
        $result = socket_write($client, "$command\n");
        $status = $this->purge_varnish_read_socket($client);
        if ($status['code'] != 200) {
            error_log('Recieved status code <i>' . $status['code'] . '</i> running ' . $command . '. Full response text: <i>' . $status['msg'] . '</i>', 0);
            return $status;
        } else {
            // Successful connection.
            return $status;
        }
    }

    /**
     * Socket read function.
     *
     * @params
     *   $client an initialized socket client
     *
     *   $retty how many times to retry on "temporarily unavalble" errors
     */
    function purge_varnish_read_socket($client, $retry = 2) {
        // Status and length info is always 13 characters.
        $header = socket_read($client, 13, PHP_BINARY_READ);

        if ($header == FALSE) {
            $error = socket_last_error();
            // 35 = socket-unavailable, so it might be blocked from our write.
            // This is an acceptable place to retry.
            if ($error == 35 && $retry > 0) {
                return $this->purge_varnish_read_socket($client, $retry - 1);
            } else {
                error_log('Socket error: ' . socket_strerror($error), 0);
                return array(
                    'code' => $error,
                    'msg' => socket_strerror($error),
                );
            }
        }
        $msg_len = (int) substr($header, 4, 6) + 1;

        $status = array(
            'code' => substr($header, 0, 3),
            'msg' => socket_read($client, $msg_len, PHP_BINARY_READ),
        );

        return $status;
    }

    /**
     * Configuration save validator.
     */
    function purge_varnish_validate($post) {

        $message = array();
        $varnish_version = $post['varnish_version'];
        $varnish_control_terminal = trim($post['varnish_control_terminal']);
        $varnish_control_terminal = !empty($varnish_control_terminal) ? $varnish_control_terminal : '';
        $varnish_control_key = trim($post['varnish_control_key']);
        $varnish_socket_timeout = trim($post['varnish_socket_timeout']);
        $varnish_bantype = $post['varnish_bantype'];

        if (empty($varnish_control_terminal) === true) {
            $message['error']['terminal_empty'] = esc_html('Varnish Control Terminal can\'t be empty.');
        }

        if (empty($varnish_control_key) === true) {
            $message['error']['key_empty'] = esc_html('Varnish Control Key can\'t be empty.');
        }

        if (empty($post['varnish_socket_timeout']) === true) {
            $message['error']['terminal_timeout'] = esc_html('Varnish connection timeout can\'t be empty.');
        }

        if (!empty($varnish_control_terminal)) {
            $terminals = explode(' ', $varnish_control_terminal);
            foreach ($terminals as $terminal) {
                list($server, $port) = explode(':', $terminal);

                if (isset($server) && (filter_var($server, FILTER_VALIDATE_IP) === false)) {
                    $message['error']['terminal_' . $server] = $server . ' ' . esc_html('is not a valid IP address');
                }
                if (empty($port) || (int) $port <= 0) {
                    $message['error']['terminal_' . $port] = esc_html('You need to enter the valid Port Number with IP address');
                }
            }
        }

        if (!is_numeric($varnish_socket_timeout) || $varnish_socket_timeout <= 0) {
            $message['error']['varnish_socket_timeout'] = esc_html('Varnish connection timeout must be a positive number.');
        }

        $msg = '';
        if (isset($message['error']) && count($message['error'])) {
            $msg .= '<ul>';
            foreach ($message['error'] as $value) {
                $msg .= '<li style="color:#FF0000;">' . $value . '</li>';
            }
            $msg .= '</ul>';
        }

        return $msg;
    }

    /**
     * Build command to purge the cache.
     */
    function purge_varnish_get_command($url, $flag = NULL) {
        $parse_url = $this->purge_varnish_parse_url($url);
        $host = $parse_url['host'];
        $path = $parse_url['path'];
        $command = "ban req.http.host == \"$host\" && req.url ~ \"^$path$\"";
        if ($flag == 'front') {
            $command = "ban req.http.host == \"$host\" && req.url ~ \"^$path/$\"";
        } elseif ($flag == 'purgeall') {
            $command = "ban req.http.host == \"$host\"";
        }

        return $command;
    }

    /**
     * Helper function to clear all varnish cache.
     */
    function purge_varnish_all_cache_manually() {
        $url = get_home_url();
        $parse_url = $this->purge_varnish_parse_url($url);
        $host = $parse_url['host'];

        $command = "ban req.http.host == \"$host\"";
        $response = $this->purge_varnish_terminal_run(array($command));

        $varnish_control_terminal = get_option('varnish_control_terminal', '');
        $terminals = explode(' ', $varnish_control_terminal);

        $msg = '';
        foreach ($terminals as $terminal) {
            $resp_stats = $response[$terminal][$command];
            $stats_code = $resp_stats['code'];
            $stats_msg = $resp_stats['msg'];

            if ($stats_code == 200) {
		$path = isset($parse_url['path']) & !empty($parse_url['path']) ? $parse_url['path'] : '';
		$url = isset($_POST['url']) & !empty($_POST['url']) ? $_POST['url'] : '';
		
                $stats_msg = 'The page url <a href="' . esc_url($url) . '" target="@target" style="color:#228B22;"><i>"' . esc_url($path) . '"</i></a> has been purged.';
                $msg .= '<li style="color:#228B22;list-style-type: circle;">All Varnish cache has been purged successfuly!</li>';
            } else {
                $msg .= '<li style="color:#8B0000;list-style-type: circle;">There is an error, Please try later!</li>';
            }
        }

        return $msg;
    }

    /**
     * Helper function to parse the host from the global $base_url.
     */
    function purge_varnish_url($urls) {
        $outout = '';
        $purge_msg = '';
        if (!count($urls)) {
            return '<ul><li style="color:#8B0000;">' . esc_html_e('Please enter at leat one url for purge.') . '</li></ul>';
        }

        foreach ($urls as $url) {
            $url = trim(esc_url($url));
            $command = $this->purge_varnish_get_command($url);
            $response = $this->purge_varnish_terminal_run(array($command));
            $purge_msg = $this->purge_varnish_url_msg($url, $command, $response, $msg);
        }

        $outout .= '<ul>';
        $outout .= $purge_msg;
        $outout .= '</ul>';

        return $outout;
    }

    function purge_varnish_number_suffix($num) {
        if (!in_array(($num % 100), array(11, 12, 13))) {
            switch ($num % 10) {
                // Handle 1st, 2nd, 3rd
                case 1: return $num . 'st';
                case 2: return $num . 'nd';
                case 3: return $num . 'rd';
            }
        }
        return $num . 'th';
    }

    /**
     * Helper function to parse the host from the global $base_url.
     */
    function purge_varnish_parse_url($url) {
        $parts = parse_url($url);
        return $parts;
    }

    function purge_varnish_url_msg($urls, $command, $response, &$msg) {
        $parse_url = $this->purge_varnish_parse_url($urls);
        $varnish_control_terminal = get_option('varnish_control_terminal', '');
        $terminals = explode(' ', $varnish_control_terminal);
        $post_url = isset($_POST['url']) &!empty($_POST['url']) ? $_POST['url'] : '/';
        foreach ($terminals as $terminal) {
            $resp_stats = $response[$terminal][$command];
            $stats_code = $resp_stats['code'];
            $stats_msg = $resp_stats['msg'];

            if ($stats_code == 200) {
                if (esc_url($parse_url['path']) == '/') {
                    $stats_msg = 'The <a href="' . esc_url($post_url) . '" target="@target" style="color:#228B22;"><i>"front/home page url"</i></a> has been purged.';
                    $msg .= '<li style="color:#228B22;list-style-type: circle;">' . $stats_msg . '</li>';
                } else {
                    $stats_msg = 'The page url <a href="' . esc_url($post_url) . '" target="@target" style="color:#228B22;"><i>"' . esc_url($parse_url['path']) . '"</i></a> has been purged.';
                    $msg .= '<li style="color:#228B22;list-style-type: circle;">' . $stats_msg . '</li>';
                }
            } else {
                $stats_msg = 'The url <a href="' . esc_url($post_url) . '" target="@target" style="color:#8B0000;"><i>"' . esc_url($parse_url['path']) . '"</i></a> has not been purged.';
                $msg .= '<li style="color:#8B0000;list-style-type: circle;">' . $stats_msg . '</li>';
            }
        }

        return $msg;
    }

    /*
     * Purges front page object.
     */

    function purge_varnish_front_page() {
        $url = get_home_url();
        $command = $this->purge_varnish_get_command($url, 'front');
        $this->purge_varnish_terminal_run(array($command));
    }

    /*
     * Purges entire cache.
     */

    function purge_varnish_all_cache_automatically() {
        $url = get_home_url();
        $command = $this->purge_varnish_get_command($url, 'front');
        $this->purge_varnish_terminal_run(array($command));
    }

    /*
     * Purges post object.
     */

    function purge_varnish_post_item($post) {
        $url = get_permalink($post->ID);
        $command = $this->purge_varnish_get_command($url);
        $this->purge_varnish_terminal_run(array($command));
    }

    /*
     * Purges post releated category objects.
     */

    function purge_varnish_category_page($post) {
        $post_id = $post->ID;
        $cats = wp_get_post_categories($post_id);
        foreach ($cats as $category_id) {
            $url = get_category_link($category_id);
            $command = $this->purge_varnish_get_command($url);
            $this->purge_varnish_terminal_run(array($command));
        }
    }

    function purge_varnish_is_post_object($post) {
        if ((!is_object($post) || !isset($post->post_type))) {
            return false;
        }
    }

    function purge_varnish_is_publish_post_object($post) {
        if (is_object($post) || $post->post_status <> 'publish') {
            return;
        }
    }

    function purge_varnish_post_is_attachment($post) {
        if (is_object($post->post_type) && $post->post_type == 'attachment') {
            return;
        }
    }

    function purge_varnish_post_is_nav_menu_item($post) {
        if (is_object($post->post_type) && $post->post_type == 'nav_menu_item') {
            return;
        }
    }

    function purge_varnish_post_custom_urls() {
        $purge_varnish_expire = get_option('purge_varnish_expire', '');
        if (!empty($purge_varnish_expire)) {
            $expire = unserialize($purge_varnish_expire);
            $expire_custom_url = isset($expire['post_custom_url']) ? $expire['post_custom_url'] : '';
            if (!empty($expire_custom_url)) {
                $custom_urls = isset($expire['post_custom_urls']) ? $expire['post_custom_urls'] : '';
                $custom_urls = explode("\r\n", $custom_urls);
                if (count($custom_urls)) {
                    foreach ($custom_urls as $url) {
                        $url = trim($url);
                        if (!empty($url)) {
                            $command = $this->purge_varnish_get_command($url);
                            $this->purge_varnish_terminal_run(array($command));
                        }
                    }
                }
            }
        }
    }

    function purge_varnish_comment_custom_urls() {
        $purge_varnish_expire = get_option('purge_varnish_expire', '');
        if (!empty($purge_varnish_expire)) {
            $expire = unserialize($purge_varnish_expire);
            $expire_custom_url = isset($expire['comment_custom_url']) ? $expire['comment_custom_url'] : '';
            if (!empty($expire_custom_url)) {
                $custom_urls = isset($expire['comment_custom_urls']) ? $expire['comment_custom_urls'] : '';
                $custom_urls = explode("\r\n", $custom_urls);
                if (count($custom_urls)) {
                    foreach ($custom_urls as $url) {
                        $command = $this->purge_varnish_get_command($url);
                        $this->purge_varnish_terminal_run(array($command));
                    }
                }
            }
        }
    }

    function purge_varnish_navmenu_custom_urls() {
        $purge_varnish_expire = get_option('purge_varnish_expire', '');
        if (!empty($purge_varnish_expire)) {
            $expire = unserialize($purge_varnish_expire);
            $expire_custom_url = isset($expire['navmenu_custom_url']) ? $expire['navmenu_custom_url'] : '';
            if (!empty($expire_custom_url)) {
                $custom_urls = isset($expire['navmenu_custom_urls']) ? $expire['navmenu_custom_urls'] : '';
                $custom_urls = explode("\r\n", $custom_urls);
                if (count($custom_urls)) {
                    foreach ($custom_urls as $url) {
                        $command = $this->purge_varnish_get_command($url);
                        $this->purge_varnish_terminal_run(array($command));
                    }
                }
            }
        }
    }

    function purge_varnish_wp_theme_custom_urls() {
        $purge_varnish_expire = get_option('purge_varnish_expire', '');
        if (!empty($purge_varnish_expire)) {
            $expire = unserialize($purge_varnish_expire);
            $expire_custom_url = isset($expire['wp_theme_custom_url']) ? $expire['wp_theme_custom_url'] : '';
            if (!empty($expire_custom_url)) {
                $custom_urls = isset($expire['wp_theme_custom_urls']) ? $expire['wp_theme_custom_urls'] : '';
                $custom_urls = explode("\r\n", $custom_urls);
                if (count($custom_urls)) {
                    foreach ($custom_urls as $url) {
                        $command = $this->purge_varnish_get_command($url);
                        $this->purge_varnish_terminal_run(array($command));
                    }
                }
            }
        }
    }

    /*
     * Prepare call for trigger post expire.
     */

    function purge_varnish_trigger_post_expire($post) {
        // Fetch expiry configuration details.
        $purge_varnish_expire = get_option('purge_varnish_expire', '');

        if (!empty($purge_varnish_expire)) {
            $expire = unserialize($purge_varnish_expire);
            if (is_array($expire)) {
                $uploads_path = wp_upload_dir();
                foreach ($expire as $page) {
                    switch ($page) {
                        case 'front_page':
                            $this->purge_varnish_front_page();
                            break;

                        case 'post_item':
                            $this->purge_varnish_post_item($post);
                            break;

                        case 'category_page':
                            $this->purge_varnish_category_page($post);
                            break;

                        case 'custom_url':
                            $this->purge_varnish_post_custom_urls();
                            break;
                    }
                }
            }
        }
    }

    /*
     * Callback to purge various type of varnish objects on publish post inserted/updated.
     */

    function purge_varnish_post_updated_trigger($ID) {
        $post = get_post($ID);

        // Halt exection if not have post object
        $this->purge_varnish_is_post_object($post);
        // Halt exection if post type is nav_menu_item.
        $this->purge_varnish_post_is_nav_menu_item($post);
        // Halt exection if post staus in not publish.
        $this->purge_varnish_is_publish_post_object($post);
        // Halt exection if post type is attachment.
        $this->purge_varnish_post_is_attachment($post);
        // Callback to purge
        $this->purge_varnish_trigger_post_expire($post);
    }

    /*
     * Callback to purge post object on comment status changed form 
     * approved => unapproved or unapproved => approved.
     */

    function purge_varnish_comment_status_trigger($new_status, $old_status, $comment) {
        // Cause with wp_update_nav_menu
        if ($new_status == $old_status) {
            return;
        }

        if (($new_status <> $old_status) && ($new_status == 'approved')) {
            $comment_post_id = $comment->comment_post_ID;
            $post = get_post($comment_post_id);

            // Halt exection if not have post object
            $this->purge_varnish_is_post_object($post);
            // Halt exection if post type is attachment.
            $this->purge_varnish_post_is_attachment($post);
            $this->purge_varnish_trigger_post_expire($post);
        } else {
            return;
        }
    }

    /*
     * Callback to purge various type of varnish objects on post changed.
     */

    function purge_varnish_post_status_trigger($new_status, $old_status, $post) {

        // Halt exection if not have post object
        $this->purge_varnish_is_post_object($post);
        // Halt exection if post type is attachment.
        $this->purge_varnish_post_is_attachment($post);

        // Cause with wp_update_nav_menu
        if ($new_status == $old_status) {
            return;
        }

        // Cause with wp_update_nav_menu
        if ($new_status == 'auto-draft' && $old_status == 'new') {
            return;
        }

        if ($new_status == 'publish' || $old_status == 'publish') {
            $this->purge_varnish_trigger_post_expire($post);
        }
    }

    /*
     * Callback to purge various type of varnish objects on post trash action.
     */

    function purge_varnish_wp_trash_post_trigger($ID) {
        // Fetch post object.
        $post = get_post($ID);

        // Halt exection if not have post object
        $this->purge_varnish_is_post_object($post);
        // Halt exection if post staus in not publish.
        $this->purge_varnish_is_publish_post_object($post);
        // Halt exection if post type is attachment.
        $this->purge_varnish_post_is_attachment($post);
        // Call to purge
        $this->purge_varnish_trigger_post_expire($post);
    }

    /*
     * Prepare call for trigger comment expire.
     */

    function purge_varnish_trigger_comment_expire($post) {
        // Fetch expiry configuration details.
        $purge_varnish_expire = get_option('purge_varnish_expire', '');
        if (!empty($purge_varnish_expire)) {
            $expire = unserialize($purge_varnish_expire);
            if (is_array($expire)) {
                foreach ($expire as $page) {
                    switch ($page) {
                        case 'front_page':
                            $this->purge_varnish_front_page();
                            break;

                        case 'post_item':
                            $this->purge_varnish_post_item($post);
                            break;

                        case 'custom_url':
                            $this->purge_varnish_comment_custom_urls();
                            break;
                    }
                }
            }
        }
    }

    /*
     * Callback to purge publish post object whenever comment is posted
     */

    function purge_varnish_comment_insert_trigger($comment_id, $comment_object) {
        // Cause with wp_update_nav_menu
        if (is_object($comment_object)) {
            $post_id = $comment_object->comment_post_ID;
            $post = get_post($post_id);

            // Halt exection if not have post object
            $this->purge_varnish_is_post_object($post);
            if ($post->post_status == 'publish') {
                $this->purge_varnish_trigger_post_expire($post);
            }
        } else {
            return;
        }
    }

    /*
     * Callback to purge publish post object whenever comment is posted
     */

    function purge_varnish_comment_edit_trigger($comment_id, $comment_object) {
        // Cause with wp_update_nav_menu
        if (is_object($comment_object)) {
            $post_id = $comment_object->comment_post_ID;
            $post = get_post($post_id);

            // Halt exection if not have post object
            $this->purge_varnish_is_post_object($post);
            if ($post->post_status == 'publish') {
                $this->purge_varnish_trigger_post_expire($post);
            }
        } else {
            return;
        }
    }

    /*
     * Callback trigger to purge nav menu items.
     */

    function purge_varnish_update_nav_menu_trigger($nav_menu_id) {

        $items = wp_get_nav_menu_items($nav_menu_id);
        if (is_object($items) && count($items) > 0) {
            $purge_varnish_expire = get_option('purge_varnish_expire', '');
            if (!empty($purge_varnish_expire)) {
                $expire = unserialize($purge_varnish_expire);
                if (is_array($expire)) {
                    foreach ($expire as $page) {
                        switch ($page) {
                            case 'front_page':
                                $this->purge_varnish_front_page();
                                break;

                            case 'post_item':
                                $this->purge_varnish_menunav_links($items);
                                break;

                            case 'custom_url':
                                $this->purge_varnish_navmenu_custom_urls();
                                break;
                        }
                    }
                }
            }
        }
    }

    /*
     * Callback to purge nav menu items.
     */

    function purge_varnish_menunav_links($items) {
        array_unique($a, SORT_STRING);
        array_unique($a, SORT_NUMERIC);
        foreach ($items as $item) {
            $url = $item->url;
            $url = isset($url) && !empty($url) ? $url : '/';
            $command = $this->purge_varnish_get_command($url);
            $this->purge_varnish_terminal_run(array($command));    
        }
    }

    /*
     * Callback for generate the logs.
     */

    function purge_varnish_debug($basedir, $logtext) {

        if (defined('WP_VARNISH_PURGE_DEBUG') && WP_VARNISH_PURGE_DEBUG == true) {
            $filename = $basedir . '/purge_varnish_log.txt';
            try {
                $handle = fopen($filename, 'a');
                fwrite($handle, $logtext . "\n");
                fclose($handle);
            } catch (Exception $e) {
                error_log("Cannot open file $filename " . $e->getMessage() . "\n", 0);
            }
        }
    }

    /*
     * Callback to validate post once.
     */

    function purge_varnish_nonce($vp_nonce) {
        $wp_nonce = $_REQUEST['_wpnonce'];
        $referer_nonce = $vp_nonce . '_referer';

        if (!wp_verify_nonce($wp_nonce, $vp_nonce)) {
            return '<ul><li style="color:#8B0000;">Sorry! Invalid nonce.</li></ul>';
        }

        return true;
    }

    /*
     * Key=>Value pair of default actions.
     */

    function purge_varnish_default_actions() {
        return array(
            'post_insert_update' => 'post_updated',
            'post_status' => 'transition_post_status',
            'post_trash' => 'wp_trash_post',
            'comment_insert' => 'wp_insert_comment',
            'comment_update' => 'edit_comment',
            'navmenu_insert_update' => 'wp_update_nav_menu',
            'theme_switch' => 'after_switch_theme',
        );
    }

    /*
     * Sanitize actions values
     */

    function purge_varnish_sanitize_actions($post) {
        $default_actions = $this->purge_varnish_default_actions();
        $additional_actions = array(
            'comment_status_changed' => 'transition_comment_status',
        );
        $actions = $default_actions + $additional_actions;
        $sanitize = array();
        foreach ($post as $key => $value) {
            if (array_key_exists($key, $actions) && ($actions[$key] == $value)) {
                $sanitize[$key] = $value;
            }
        }
        return $sanitize;
    }

    /*
     * Key=>Value pair of default actions.
     */

    function purge_varnish_default_tiggers() {
        return array(
            'post_front_page' => 'front_page',
            'post_custom_url' => 'custom_url',
            'post_post_item' => 'post_item',
            'post_category_page' => 'category_page',
            'comment_front_page' => 'front_page',
            'comment_custom_url' => 'custom_url',
            'navmenu_front_page' => 'front_page',
            'navmenu_custom_url' => 'custom_url',
            'wp_theme_front_page' => 'front_page',
            'wp_theme_custom_url' => 'custom_url',
        );
    }

    /*
     * Sanitize tiggers values
     */

    function purge_varnish_sanitize_tiggers($post) {
        $default_tiggers = $this->purge_varnish_default_tiggers();
        $additional_tiggers = array(
            'comment_post_item' => 'post_item',
            'navmenu_menu_link' => 'menu_item',
            'wp_theme_purge_all' => 'purge_all',
            'post_custom_urls' => '?',
            'comment_custom_urls' => '?',
            'navmenu_custom_urls' => '?',
            'wp_theme_custom_urls' => '?',
        );
        $tiggers = $default_tiggers + $additional_tiggers;

        $sanitize = array();
        foreach ($post as $key => $value) {
            if (array_key_exists($key, $tiggers) && ($tiggers[$key] == $value)) {
                $sanitize[$key] = $value;
            } else if (array_key_exists($key, $tiggers) && ($tiggers[$key] == '?')) {
                $sanitize[$key] = $value;
            }
        }

        return $sanitize;
    }

    /*
     * Actions perform on activation of plugin
     */

    function purge_varnish_switch_theme_trigger($theme) {
        $purge_varnish_expire = get_option('purge_varnish_expire', '');
        if (!empty($purge_varnish_expire)) {
            $expire = unserialize($purge_varnish_expire);
            if (is_array($expire)) {
                foreach ($expire as $page) {
                    switch ($page) {
                        case 'front_page':
                            $this->purge_varnish_front_page();
                            break;

                        case 'purge_all':
                            $this->purge_varnish_all_cache_automatically();
                            break;

                        case 'custom_url':
                            $this->purge_varnish_wp_theme_custom_urls();
                            break;
                    }
                }
            }
        }
    }

    /*
     * Actions perform on activation of plugin
     */

    function purge_varnish_install() {
        $actions = $this->purge_varnish_default_actions();
        update_option('purge_varnish_action', serialize($actions));

        $tiggers = $this->purge_varnish_default_tiggers();
        update_option('purge_varnish_expire', serialize($tiggers));
    }

    /*
     * Actions perform on de-activation of plugin
     */

    function purge_varnish_uninstall() {
        delete_option('varnish_version');
        delete_option('varnish_control_terminal');
        delete_option('varnish_control_key');
        delete_option('varnish_socket_timeout');
        delete_option('varnish_bantype');
        delete_option('purge_varnish_action');
        delete_option('purge_varnish_expire');
    }

}

$purge_varnish = new Purge_Varnish();
add_action('admin_head', array($purge_varnish, 'purge_varnish_register_styles'));

// Trigger post action to purge varnish objects.
$purge_varnish_action = get_option('purge_varnish_action', '');
if (!empty($purge_varnish_action)) {
    $actions = unserialize($purge_varnish_action);
    if (is_array($actions)) {       
        foreach ($actions as $action) {
            switch ($action) {
                case 'post_updated':
                    add_action($action, array($purge_varnish, 'purge_varnish_post_updated_trigger'));
                    break;
                case 'transition_post_status':
                    add_action($action, array($purge_varnish, 'purge_varnish_post_status_trigger'), 10, 3);
                    break;

                case 'wp_trash_post':
                    add_action($action, array($purge_varnish, 'purge_varnish_wp_trash_post_trigger'));
                    break;

                case 'edit_attachment':
                    add_action($action, array($purge_varnish, 'purge_varnish_attachment_update_trigger'));
                    break;

                case 'wp_insert_comment':
                    add_action($action, array($purge_varnish, 'purge_varnish_comment_insert_trigger'), 10, 3);
                    break;

                case 'edit_comment':
                    add_action($action, array($purge_varnish, 'purge_varnish_comment_edit_trigger'), 10, 3);
                    break;

                case 'transition_comment_status':
                    add_action($action, array($purge_varnish, 'purge_varnish_comment_status_trigger'), 10, 3);
                    break;

                case 'wp_update_nav_menu':
                    add_action($action, array($purge_varnish, 'purge_varnish_update_nav_menu_trigger'));
                    break;

                case 'after_switch_theme':
                    add_action($action, array($purge_varnish, 'purge_varnish_switch_theme_trigger'));
                    break;
            }
        }
    }
}
?>
