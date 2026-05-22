<?php
/*
Plugin Name: LOTE Belépési Kérelem
*/
add_shortcode('lote_join', function() {
    return '<iframe src="/connect/join.php"
                    width="100%" height="1789"
                    style="border:none;display:block;"
                    loading="lazy"></iframe>';
});