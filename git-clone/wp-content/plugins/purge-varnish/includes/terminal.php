<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Initialize the class object.
$purge_varnish = new Purge_Varnish();

// Variable to set the error messages.
$validate_msg = '';

// Save the options value.
if (isset($_POST['savecode'])) {
    if ($purge_varnish->purge_varnish_nonce('pvTsetting') == true) {
        $validate_msg = $purge_varnish->purge_varnish_validate($_POST);
        update_option('varnish_version', sanitize_text_field($_POST['varnish_version']));
        update_option('varnish_control_terminal', sanitize_text_field($_POST['varnish_control_terminal']));
        update_option('varnish_control_key', sanitize_text_field($_POST['varnish_control_key']));
        update_option('varnish_socket_timeout', (int) $_POST['varnish_socket_timeout']);
        update_option('varnish_bantype', sanitize_text_field($_POST['varnish_bantype']));
    }
}

// Get the options value.
$varnish_version = esc_html(get_option('varnish_version', ''));
$varnish_control_terminal = esc_html(get_option('varnish_control_terminal', ''));
$varnish_control_key = esc_html(get_option('varnish_control_key', ''));
$varnish_socket_timeout = (int) get_option('varnish_socket_timeout', Purge_Varnish::PURGE_VARNISH_DEFAULT_TIMEOUT);
$varnish_bantype = esc_html(get_option('varnish_bantype', ''));

// Callback to test the terminal status.
$terminal_resp = $purge_varnish->purge_varnish_terminal_run(array('status'));
?>
<div class="purge_varnish">
    <div class="screen">
        <h2><?php print esc_html_e($title); ?></h2>
        <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post" >
            <table cellpadding="5">
                <tbody>
                    <?php if (!empty($validate_msg)) { ?>
                        <tr>
                            <td ></td>
                            <td><?php print $validate_msg; ?> </td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <th width="30%" valign="top"><?php esc_html_e('Debug:') ?> </th>
                        <td width="60%">
                            <code style="background-color: #fff;">
                                define('WP_VARNISH_PURGE_DEBUG', true);
                            </code><br />
                            <span class="terminal_desc"><?php esc_html_e('For debug add the above constant in wp-config.php file'); ?><span>
                                    </td>
                                    <td width="60%">&nbsp;</td>
                                    </tr>
                                    <tr>
                                        <th valign="top"><?php esc_html_e('Varnish version:') ?> </th>
                                        <td>

                                            <select name="varnish_version" class="form-select ajax-processed">
                                                <option value="4" <?php
                                                if ($varnish_version == 4) {
                                                    echo 'selected="selected"';
                                                }
                                                ?> >4.x / 5.x</option>
                                                <option value="3" <?php
                                                if ($varnish_version == 3) {
                                                    echo 'selected="selected"';
                                                }
                                                ?> >3.x</option>
                                                        <?php ?>
                                            </select>
                                            <br />
                                            <span class="terminal_desc"><?php esc_html_e('Select your varnish version') ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th valign="top"><?php esc_html_e('Varnish Control Terminal:') ?> </th>
                                        <td>
                                            <input name="varnish_control_terminal" value="<?php print $varnish_control_terminal; ?>" size="60" maxlength="128" type="text" />
                                            <br />
                                            <span class="terminal_desc"><?php esc_html_e('Set this to the server IP or hostname that varnish runs on (e.g. ' . Purge_Varnish::PURGE_VARNISH_DEFAULT_TREMINAL . '). This must be configured for Wordpress to talk to Varnish. Separate multiple servers with spaces.') ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th valign="top"><?php esc_html_e('Varnish Control Key:') ?> </th>
                                        <td>
                                            <input name="varnish_control_key" value="<?php print $varnish_control_key; ?>" size="60" maxlength="128" type="text">
                                            <br />
                                            <span class="terminal_desc"><?php esc_html_e('If you have established a secret key for control terminal access, please put it here.') ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th valign="top"><?php esc_html_e('Varnish connection timeout (milliseconds):') ?> </th>
                                        <td>
                                            <input name="varnish_socket_timeout" value="<?php print $varnish_socket_timeout; ?>" size="60" maxlength="128" type="text">
                                            <br />
                                            <?php esc_html_e('If Varnish is running on a different server, you may need to increase this value.') ?> 
                                        </td>
                                    </tr>
                                    <tr>
                                        <th valign="top"><?php esc_html_e('Varnish ban type:') ?> </th>
                                        <td>
                                            <select name="varnish_bantype" class="form-select">
                                                <option value="0" <?php
                                                if ($varnish_version == '0') {
                                                    echo 'selected="selected"';
                                                }
                                                ?>>Normal</option>
                                                <option value="1" <?php
                                                if ($varnish_version == '1') {
                                                    echo 'selected="selected"';
                                                }
                                                ?>>Ban Lurker</option>
                                            </select>
                                            <br />
                                            <span class="terminal_desc"><?php esc_html_e('Select the type of varnish ban you wish to use. Ban lurker support requires you to add beresp.http.x-url and beresp.http.x-host entries to the response in vcl_fetch.') ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th ><?php esc_html_e('Status:') ?> </th>
                                        <td>
                                            <div class="item-list">
                                                <ul>
                                                    <?php
                                                    $terminals = explode(' ', $varnish_control_terminal);
                                                    foreach ($terminals as $terminal) {
                                                        $resp_stats = isset($terminal_resp[$terminal]['status']) ? $terminal_resp[$terminal]['status'] : '';
                                                        $stats_code = isset($resp_stats['code']) ? $resp_stats['code'] : '';
                                                        $stats_msg = isset($resp_stats['msg']) ? $resp_stats['msg'] : '';
                                                        $error_msg = 'Error code: ' . $stats_code . ', ' . $stats_msg;
                                                        if ($stats_code == 200) {
                                                            ?>
                                                            <li>
                                                                <img src="<?php print plugins_url('../images/ok.png', __FILE__); ?>" align="middle" alt="Server OK: <?php print $varnish_control_terminal; ?>" title="<?php print $varnish_control_terminal; ?>"> Varnish running.
                                                                <?php
                                                            } else {
                                                                ?><img src="<?php print plugins_url('../images/error.png', __FILE__); ?>" align="middle" alt="<?php print $error_msg; ?>" title="<?php print $error_msg; ?>"> The Varnish control terminal is not responding at <?php print $terminal; ?><?php }
                                                            ?>
                                                        </li>
                                                    <?php } ?>
                                                </ul>
                                            </div> 
                                        </td>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <td>
                                            <?php wp_nonce_field('pvTsetting'); ?>
                                            <input type="submit" value="Save Configurations" name="savecode" />
                                        </td>
                                    </tr>
                                    </tbody>
                                    </table>
                                    </form>
                                    </div>
                                    </div>