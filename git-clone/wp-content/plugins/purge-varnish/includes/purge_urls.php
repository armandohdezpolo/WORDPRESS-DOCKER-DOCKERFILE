<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$msg = '';
$purge_varnish = new Purge_Varnish();

if (isset($_POST['purge']) && $_POST['purge'] == 'Purge') {
  if($purge_varnish->purge_varnish_nonce('pvUrls') == true) {
    $urls = array_filter($_POST['urls']);
    // Sanitize internally.
    $msg = $purge_varnish->purge_varnish_url($urls);
  }
}

$purge_varnish_url = Purge_Varnish::PURGE_VARNISH_COUNT_DEFAULT_URLS; 
?>
<div class="purge_varnish">
  <div class="screen">
    <h2><?php print esc_html_e($title); ?></h2>
    <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post" name="settings_form" id="settings_form">
      <table cellpadding="5">
        <tr>
          <td colspan="2">&nbsp;</td>
        </tr>
        <tbody>
          <?php if (!empty($msg)) { ?>
            <tr>
              <td></td>
              <td><div style="border: 1px solid #ccc; padding:0px 0px 0px 20px"><?php print $msg; ?> </div></td>
            </tr>
          <?php } ?>
          <?php
          for ($i = 1; $i <= $purge_varnish_url; $i++) {
            $number = $purge_varnish->purge_varnish_number_suffix($i);
            ?>  
            <tr>
              <th width="30%"><?php esc_html_e('Enter ' . $number . ' URL :') ?></th>
              <td width="70%">
                <input id="urls" name="urls[]" value="" size="60" maxlength="225" type="text" />
              </td>
            </tr>
          <?php } ?>
          <tr>
            <td></td>
            <td>
              <?php wp_nonce_field('pvUrls');?>
              <input type="submit" value="Purge" name="purge" />
            </td>
          </tr>
        </tbody>
      </table>
    </form>
  </div>
</div>