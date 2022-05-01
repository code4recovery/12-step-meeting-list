<?php

function tsml_geocoding_page()
{
    $result = empty($_POST['address']) ? null : tsml_geocode($_POST['address']);
?>
    <div class="wrap">
        <h1>Geocoding</h1>
        <hr>
        <p>This admin page is enabled by adding <code>define('TSML_ADMIN', true);</code> to your <code>wp-config.php</code>.</p>
        <div class="postbox">
            <div class="inside">
                <h2>Try It</h2>
                <hr>
                <form method="post">
                    <label for="address">Address</label>
                    <input type="search" id="address" name="address" value="<?php if (!empty($_POST['address'])) echo $_POST['address'] ?>" placeholder="Enter address here">
                    <input type="submit" value="Look Up">
                    <?php
                    if (!empty($result)) {
                        if (is_string($result)) { ?>
                            <h3>Error!</h3>
                            <?php echo $result ?>
                        <?php } else { ?>
                            <h3>Success</h3>
                            <pre><?php print_r($result) ?></pre>
                    <?php }
                    } ?>
                </form>
            </div>
        </div>
        <div class="postbox">
            <div class="inside">
                <h2>C4R Gateway Registration</h2>
                <hr>
                <?php if ($registration = get_option('tsml_geocoding_registration')) { ?>
                    <p>This site is registered for the C4R Gateway.</p>
                    <pre><?php print_r($registration) ?></pre>
                <?php } else { ?>
                    <p>This site is not registered for the C4R Gateway.</p>
                <?php } ?>
            </div>
        </div>
        <div class="postbox">
            <div class="inside">
                <h2>Cache</h2>
                <hr>
                <?php if ($cache = get_option('tsml_addresses')) {
                    $cache = array_reverse($cache);
                ?>
                    <pre><?php print_r($cache) ?></pre>
                <?php } else { ?>
                    <p>Nothing in the cache yet.</p>
                <?php } ?>
            </div>
        </div>
    </div>
<?php
}
