<div class="wrap">
    <h2><?php _e('Network Search Settings', $this->td); ?></h2>

    <form method="POST" action="<?php echo ( is_network_admin() ) ? admin_url('admin-post.php?action=network_search-network-settings') : '' ?>">
        <?php
            wp_nonce_field('save-network_search-settings');
        ?>
        <input type="hidden" name="save-network_search-settings" value="1">

        <h4><?php _e('Post types (comma separated)', $this->td); ?></h4>
        <div>
            <input type="text" name="post_types" value="<?php echo (isset($this->settings['post_types']) && is_array($this->settings['post_types'])) ? implode(", ", $this->settings['post_types']) : ''; ?>">
        </div>

        <div style="margin-top: 20px;">
            <input type="submit" value="<?php _e('Save Changes', $this->td); ?>">
        </div>

    </form>

</div>
