<?php
namespace Config;

use Midtrans\Config;

class MidtransConfig {
    public static function init() {
        // Sandbox credentials - GANTI DENGAN KEY ANDA
        Config::$serverKey = 'Mid-server-t7UvdYAqvbKZNB-irrrZ9Ruz';
        Config::$clientKey = 'Mid-client-AkQ7Ch2NAQFP8kri';
        Config::$isProduction = false; // Set true untuk production
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }
}

// Panggil init
MidtransConfig::init();
?>