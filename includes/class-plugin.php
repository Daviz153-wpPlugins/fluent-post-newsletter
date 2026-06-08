<?php
namespace FluentPostNewsletter;

defined('ABSPATH') || exit;

class Plugin {

    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init(): void {
        $metaBox = new MetaBox();
        $metaBox->register();
    }
}
