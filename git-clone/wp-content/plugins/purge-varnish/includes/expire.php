<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$purge_varnish = new Purge_Varnish();
// Variable to set the error messages.
$validate_msg = '';

// Save the options value.
if (isset($_POST['save_configurations']) && $_POST['save_configurations']) {
    if ($purge_varnish->purge_varnish_nonce('pvEsetting') == true) {
        $post_actions = (array) $_POST['purge_varnish_action'];

        $sanitize_actions = $purge_varnish->purge_varnish_sanitize_actions($post_actions);
        update_option('purge_varnish_action', serialize($sanitize_actions));

        $post_expire = (array) $_POST['purge_varnish_expire'];
        $sanitize_tiggers = $purge_varnish->purge_varnish_sanitize_tiggers($post_expire);
        update_option('purge_varnish_expire', serialize($sanitize_tiggers));
    }
}

// Get the post action options value
$purge_varnish_action = get_option('purge_varnish_action', '');
$post_insert_update = '';
$post_status = '';
$post_trash = '';
$comment_insert = '';
$comment_update = '';
$comment_status_changed = '';
$navmenu_insert_update = '';
$after_switch_theme = '';
if (!empty($purge_varnish_action)) {
    $action = unserialize($purge_varnish_action);
    $post_insert_update = isset($action['post_insert_update']) ? $action['post_insert_update'] : '';
    $post_status = isset($action['post_status']) ? $action['post_status'] : '';
    $post_trash = isset($action['post_trash']) ? $action['post_trash'] : '';
    $comment_insert = isset($action['comment_insert']) ? $action['comment_insert'] : '';
    $comment_update = isset($action['comment_update']) ? $action['comment_update'] : '';
    $comment_status_changed = isset($action['comment_status_changed']) ? $action['comment_status_changed'] : '';
    $navmenu_insert_update = isset($action['navmenu_insert_update']) ? $action['navmenu_insert_update'] : '';
    $switch_theme = isset($action['theme_switch']) ? $action['theme_switch'] : '';
}

// Get the post expire options value
$purge_varnish_expire = get_option('purge_varnish_expire', '');
$expire_post_front_page = '';
$expire_post_post_item = '';
$expire_post_category_page = '';
$expire_post_custom_url = '';
$expire_comment_front_page = '';
$expire_comment_post_item = '';
$expire_comment_custom_url = '';
$expire_navmenu_front_page = '';
$expire_navmenu_link = '';
$purge_all = '';
$expire_navmenu_custom_url = '';
$expire_wp_theme_custom_url = '';
if (!empty($purge_varnish_expire)) {
    $expire = unserialize($purge_varnish_expire);
    $expire_post_front_page = isset($expire['post_front_page']) ? $expire['post_front_page'] : '';
    $expire_post_post_item = isset($expire['post_post_item']) ? $expire['post_post_item'] : '';
    $expire_post_category_page = isset($expire['post_category_page']) ? $expire['post_category_page'] : '';
    $expire_post_custom_url = isset($expire['post_custom_url']) ? $expire['post_custom_url'] : '';
    $expire_post_custom_urls = isset($expire['post_custom_urls']) ? $expire['post_custom_urls'] : '';
    $expire_comment_front_page = isset($expire['comment_front_page']) ? $expire['comment_front_page'] : '';
    $expire_comment_post_item = isset($expire['comment_post_item']) ? $expire['comment_post_item'] : '';
    $expire_comment_custom_url = isset($expire['comment_custom_url']) ? $expire['comment_custom_url'] : '';
    $expire_comment_custom_urls = isset($expire['comment_custom_urls']) ? $expire['comment_custom_urls'] : '';
    $expire_navmenu_front_page = isset($expire['navmenu_front_page']) ? $expire['navmenu_front_page'] : '';
    $expire_navmenu_link = isset($expire['navmenu_menu_link']) ? $expire['navmenu_menu_link'] : '';
    $expire_navmenu_custom_url = isset($expire['navmenu_custom_url']) ? $expire['navmenu_custom_url'] : '';
    $expire_navmenu_custom_urls = isset($expire['navmenu_custom_urls']) ? $expire['navmenu_custom_urls'] : '';
    $expire_wp_theme_front_page = isset($expire['wp_theme_front_page']) ? $expire['wp_theme_front_page'] : '';
    $expire_wp_theme_purge_all = isset($expire['wp_theme_purge_all']) ? $expire['wp_theme_purge_all'] : '';
    $expire_wp_theme_custom_url = isset($expire['wp_theme_custom_url']) ? $expire['wp_theme_custom_url'] : '';
    $expire_wp_theme_custom_urls = isset($expire['wp_theme_custom_urls']) ? $expire['wp_theme_custom_urls'] : '';
}
?>
<div class="purge_varnish">
    <div class="screen">
        <h2><?php print esc_html_e($title); ?></h2>
        <ul class="tab">
            <li><a href="javascript:void(0)" class="tablinks" onclick="open_menu(event, 'post_expiration')"><?php esc_html_e('Post expiration'); ?></a></li>
            <li><a href="javascript:void(0)" class="tablinks" onclick="open_menu(event, 'comment_expiration')"><?php esc_html_e('Comment expiration'); ?></a></li>
            <li><a href="javascript:void(0)" class="tablinks" onclick="open_menu(event, 'menu_expiration')"><?php esc_html_e('Menu links expiration'); ?></a></li>
            <li><a href="javascript:void(0)" class="tablinks" onclick="open_menu(event, 'switch_theme')"><?php esc_html_e('Switch Theme'); ?></a></li>
        </ul>
        <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
            <div id="post_expiration" class="tabcontent">
                <h3><?php esc_html_e('Post expiration'); ?></h3>
                <table cellpadding="5">
                    <tbody>
                        <tr>
                            <th width="20%"></th>
                            <td width="80%">
                                <b><?php esc_html_e('Post actions') ?></b>
                                <p>
                                    <input type="checkbox" name="purge_varnish_action[post_insert_update]" value="post_updated" <?php
                                    if ($post_insert_update == 'post_updated') {
                                        print 'checked="checked"';
                                    }
                                    ?> /> <label><?php esc_html_e('Post insert/update'); ?></label> <br /><span class="desc"><?php esc_html_e('Trigger when approved post is going to inserted/updated.'); ?></span><br />

                                    <input type="checkbox" name="purge_varnish_action[post_status]" value="transition_post_status" <?php
                                    if ($post_status == 'transition_post_status') {
                                        print 'checked="checked"';
                                    }
                                    ?> /> <label><?php esc_html_e('Post status changed'); ?></label> <br /><span class="desc"><?php esc_html_e('Trigger when post is published/unpublished.'); ?></span><br />

                                    <input type="checkbox" name="purge_varnish_action[post_trash]" value="wp_trash_post" <?php
                                    if ($post_trash == 'wp_trash_post') {
                                        print 'checked="checked"';
                                    }
                                    ?> /> <label><?php esc_html_e('Post trash'); ?></label> <br /><span class="desc"><?php esc_html_e('Trigger when approved post is going to trash.'); ?></span><br />
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th> </th>
                            <td>
                                <b><?php esc_html_e('What URLs should be expired when post action is triggered?') ?></b>
                                <p>
                                    <input type="checkbox" name="purge_varnish_expire[post_front_page]" value="front_page" <?php
                                    if ($expire_post_front_page == 'front_page') {
                                        print 'checked="checked"';
                                    }
                                    ?> /> <label><?php esc_html_e('Front page') ?></label> <br /><span class="desc"><?php esc_html_e('Expire url of the site front page'); ?></span><br />
                                    <input type="checkbox" name="purge_varnish_expire[post_post_item]" value="post_item" <?php
                                    if ($expire_post_post_item == 'post_item') {
                                        print 'checked="checked"';
                                    }
                                    ?> /> <label>Post/Page</label> <br /> 
                                    <span class="desc"><?php esc_html_e('Expire url of the expiring post/page'); ?></span><br />
                                    <input type="checkbox" name="purge_varnish_expire[post_category_page]" value="category_page" <?php
                                    if ($expire_post_category_page == 'category_page') {
                                        print 'checked="checked"';
                                    }
                                    ?> /> <label><?php esc_html_e('Category pages'); ?></label><br />
                                    <span class="desc">Expire all post/page URLs linked with category.</span><br />

                                    <?php
                                    $expire_post_custom_url_class = 'hide_custom_url';
                                    $expire_post_checked = '';
                                    if ($expire_post_custom_url == 'custom_url') {
                                        $expire_post_checked = 'checked="checked"';
                                        $expire_post_custom_url_class = 'show_custom_url';
                                    }
                                    ?>
                                    <input type="checkbox" name="purge_varnish_expire[post_custom_url]" value="custom_url" <?php print $expire_post_checked; ?> class="ck_custom_url" /> <label><?php esc_html_e('Custom URLs'); ?></label> <br />
                                    <span class="desc"><?php esc_html_e('Expire user-defined custom urls.'); ?></span>
                                <div class="custom_url <?php print $expire_post_custom_url_class; ?>">
                                    <textarea rows="3" cols="70" name="purge_varnish_expire[post_custom_urls]" class="input_custom_urls"><?php print $expire_post_custom_urls; ?></textarea>
                                    <div>
                                        </p>
                                        </td>
                                        </tr>
                                        </tbody>
                                        </table>
            </div>

            <div id="comment_expiration" class="tabcontent">
            <h3><?php esc_html_e('Comment expiration'); ?></h3>
            <table cellpadding="5">
                <tbody>
                    <tr>
                        <th width="20%"></th>
                        <td width="80%">
                            <b><?php esc_html_e('Comment actions') ?></b>
                            <p>
                                <input type="checkbox" name="purge_varnish_action[comment_insert]" value="wp_insert_comment" <?php
                                if ($comment_insert == 'wp_insert_comment') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('Comment inserted'); ?></label> <br /> <span class="desc"><?php esc_html_e('Trigger when comment is posted on approved post.'); ?></span><br />


                                <input type="checkbox" name="purge_varnish_action[comment_update]" value="edit_comment" <?php
                                if ($comment_update == 'edit_comment') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('Comment updated'); ?></label> <br /> <span class="desc"><?php esc_html_e('Trigger on approved post whenever comment is updated.'); ?></span><br />




                                <input type="checkbox" name="purge_varnish_action[comment_status_changed]" value="transition_comment_status" <?php
                                if ($comment_status_changed == 'transition_comment_status') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('Comment status changed'); ?></label> <br /><span class="desc"><?php esc_html_e('Trigger when comment is approved/unapproved.'); ?></span><br />

                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th> </th>
                        <td>
                            <b><?php esc_html_e('What URLs should be expired when comment action is triggered?'); ?></b>
                            <p>
                                <input type="checkbox" name="purge_varnish_expire[comment_front_page]" value="front_page" <?php
                                if ($expire_comment_front_page == 'front_page') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('Front page'); ?></label> <br /><span class="desc"><?php esc_html_e('Expire url of the site front page'); ?></span><br />
                                <input type="checkbox" name="purge_varnish_expire[comment_post_item]" value="post_item" <?php
                                if ($expire_comment_post_item == 'post_item') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('Post page'); ?></label> <br /> <span class="desc"><?php esc_html_e('Expire url of the expiring post/page'); ?></span><br />

                                <?php
                                $expire_comment_custom_url_class = 'hide_custom_url';
                                $expire_comment_checked = '';
                                if ($expire_comment_custom_url == 'custom_url') {
                                    $expire_comment_checked = 'checked="checked"';
                                    $expire_comment_custom_url_class = 'show_custom_url';
                                }
                                ?>
                                <input type="checkbox" name="purge_varnish_expire[comment_custom_url]" value="custom_url" <?php print $expire_comment_checked; ?> class="ck_custom_url" /> <label><?php esc_html_e('Custom URLs'); ?></label> <br />
                                <span class="desc"><?php esc_html_e('Expire user-defined custom urls.'); ?></span><br />
                            <div class="custom_url <?php print $expire_comment_custom_url_class; ?>">
                                <textarea rows="3" cols="70" name="purge_varnish_expire[comment_custom_urls]" class="input_custom_urls"><?php print $expire_comment_custom_urls; ?></textarea>
                            </div>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="menu_expiration" class="tabcontent">
            <h3><?php esc_html_e('Menu links expiration'); ?></h3>
            <table cellpadding="5">
                <tbody>
                    <tr>
                        <th width="20%"></th>
                        <td width="80%">
                            <b><?php esc_html_e('Menu link actions') ?></b>
                            <p>
                                <input type="checkbox" name="purge_varnish_action[navmenu_insert_update]" value="wp_update_nav_menu" <?php
                                if ($navmenu_insert_update == 'wp_update_nav_menu') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('Menu link insert/update/delete'); ?></label> <br /> <span class="desc"><?php esc_html_e('Trigger on menu save action.'); ?></span><br />
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th> </th>
                        <td>
                            <b><?php esc_html_e('What URLs should be expired when menu save action is triggered?') ?></b>
                            <p>
                                <input type="checkbox" name="purge_varnish_expire[navmenu_front_page]" value="front_page" <?php
                                if ($expire_navmenu_front_page == 'front_page') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('Front page'); ?></label> <br /><span class="desc"><?php esc_html_e('Expire url of the site front page'); ?></span><br />
                                <input type="checkbox" name="purge_varnish_expire[navmenu_menu_link]" value="menu_item" <?php
                                if ($expire_navmenu_link == 'menu_item') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('Menu links'); ?></label> <br /> <span class="desc"><?php esc_html_e('Expire url of menu links'); ?></span><br />

                                <?php
                                $expire_navmenu_custom_url_class = 'hide_custom_url';
                                $expire_navmenu_checked = '';
                                if ($expire_navmenu_custom_url == 'custom_url') {
                                    $expire_navmenu_checked = 'checked="checked"';
                                    $expire_navmenu_custom_url_class = 'show_custom_url';
                                }
                                ?>
                                <input type="checkbox" name="purge_varnish_expire[navmenu_custom_url]" value="custom_url" <?php print $expire_navmenu_checked; ?> class="ck_custom_url" /> <label><?php esc_html_e('Custom URLs'); ?></label> <br />
                                <span class="desc"><?php esc_html_e('Expire user-defined custom urls.'); ?></span><br />
                            <div class="custom_url <?php print $expire_navmenu_custom_url_class; ?>">
                                <textarea rows="3" cols="70" name="purge_varnish_expire[navmenu_custom_urls]" class="input_custom_urls"><?php print $expire_navmenu_custom_urls; ?></textarea>
                            </div>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="switch_theme" class="tabcontent">
            <h3><?php esc_html_e('Switch theme expiration'); ?></h3>
            <table cellpadding="5">
                <tbody>
                    <tr>
                        <th width="20%"></th>
                        <td width="80%">
                            <b><?php esc_html_e('Theme actions') ?></b>
                            <p>
                                <input type="checkbox" name="purge_varnish_action[theme_switch]" value="after_switch_theme" <?php
                                if ($switch_theme == 'after_switch_theme') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('switch theme'); ?></label> <br /><span class="desc"><?php esc_html_e('Trigger when theme is switched.'); ?></span><br />
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th> </th>
                        <td>
                            <b><?php esc_html_e('What URLs should be expired when theme switch action is triggered?') ?></b>
                            <p>
                                <input type="checkbox" name="purge_varnish_expire[wp_theme_front_page]" value="front_page" <?php
                                if ($expire_wp_theme_front_page == 'front_page') {
                                    print 'checked="checked"';
                                }
                                ?> /> <label><?php esc_html_e('Front page') ?></label> <br /><span class="desc"><?php esc_html_e('Expire url of the site front page'); ?></span><br />
                                <input type="checkbox" name="purge_varnish_expire[wp_theme_purge_all]" value="purge_all" <?php
                                if ($expire_wp_theme_purge_all == 'purge_all') {
                                    print 'checked="checked"';
                                }
                                ?> class="ck_custom_url" /> <label>All varnish cache</label> <br /> <span class="desc"><?php esc_html_e('Expire/Purge all varnish cache'); ?></span><br />                              

                                <?php
                                $expire_wp_theme_custom_url_class = 'hide_custom_url';
                                $expire_wp_theme_checked = '';
                                if ($expire_wp_theme_custom_url == 'custom_url') {
                                    $expire_wp_theme_checked = 'checked="checked"';
                                    $expire_wp_theme_custom_url_class = 'show_custom_url';
                                }
                                ?>
                                <input type="checkbox" name="purge_varnish_expire[wp_theme_custom_url]" value="custom_url" <?php print $expire_wp_theme_checked; ?> class="ck_custom_url" /> <label><?php esc_html_e('Custom URLs'); ?></label> <br />
                                <span class="desc"><?php esc_html_e('Expire user-defined custom urls.'); ?></span><br />
                            <div class="custom_url <?php print $expire_wp_theme_custom_url_class; ?>">
                                <textarea rows="3" cols="70" name="purge_varnish_expire[wp_theme_custom_urls]" class="input_custom_urls"><?php print $expire_wp_theme_custom_urls; ?></textarea>
                            </div>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <table cellpadding="5">
            <tr>
                <th width="20%"></th>
                <td>
                    <?php wp_nonce_field('pvEsetting'); ?>
                    <input type="submit" value="Save Configurations" name="save_configurations" />
                </td>
            </tr>
        </table>
        </form>
    </div>
</div>