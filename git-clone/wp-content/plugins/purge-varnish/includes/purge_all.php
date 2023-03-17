<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$msg = '';
$purge_varnish = new Purge_Varnish();

if (isset($_POST['purge_all']) && $_POST['purge_all'] == 'Purge all') {
  if($purge_varnish->purge_varnish_nonce('purgeAllCache') == true) {
     $msg = $purge_varnish->purge_varnish_all_cache_manually();
  }
}
?>
<div class="purge_varnish">
  <div class="screen">
    <h2><?php print esc_html_e($title); ?></h2>
    <form action="<?php echo str_replace('%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
      <table cellpadding="5">
        <tr>
          <td colspan="2">&nbsp;</td>
        </tr>
        <tbody>
          <?php if (!empty($msg)) { ?>
            <tr>
              <td></td>
              <td><div style="border: 1px solid #ccc; padding:0px 0px 0px 20px"><ul><?php print $msg; ?></ul></div></td>
            </tr>
          <?php } ?>
          <tr>
            <th width="50%"><?php esc_html_e('To clear whole site varnish cache, Click on Purge all button') ?></th>
            <td> 
              <?php wp_nonce_field('purgeAllCache');?>
              <input type="submit" value="Purge all" name="purge_all" />
            </td>
          </tr>
        </tbody>
      </table>
    </form>
  </div>
</div>