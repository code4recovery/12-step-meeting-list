<?php

function tsml_addresses_page()
{
    $result = null;
    if (!empty($_POST['address'])) {
        $result = tsml_geocode($_POST['address']);
    }
?>
    <div class="wrap">
        <h1>Addresses</h1>
        <hr>
        <p>"Geocoding" is the process of looking up addresses to discover information about them such as geographic coordinates.</p>

        <div class="postbox">
            <div class="inside">
                <h2>Look Up</h2>
                <hr>
                <form method="post">
                    <label for="address">Address</label>
                    <input type="search" id="address" name="address" placeholder="123 Main Street, Buffalo, NY">
                    <input type="submit" value="Look Up">
                    <?php
                    if (!empty($result)) { ?>
                        <pre>
                            <?php print_r($result) ?>
                        </pre>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
<?php
}
