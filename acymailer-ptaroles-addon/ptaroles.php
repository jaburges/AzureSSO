<?php
/*
 * Plugin Name: AcyMailing integration for PTA Roles Manager
 * Description: Adds dynamic PTA role information in AcyMailing emails
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPLv3
 * Version: 1.0.0
 * Requires Plugins: acymailing, pta-roles-manager
*/

use AcyMailing\Classes\PluginClass;

if (!defined('ABSPATH')) {
    exit;
}

class AcyMailingIntegrationForPTARoles
{
    const INTEGRATION_PLUGIN_NAME = 'plgAcymPtaroles';

    public function __construct()
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'disable']);
        register_uninstall_hook(__FILE__, [self::class, 'uninstall']);
        add_action('acym_load_installed_integrations', [$this, 'register'], 10, 2);
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    public function activate(): void
    {
        // Set a transient for the activation notice
        set_transient('acym_ptaroles_activated', true, 30);
    }

    public function admin_notices(): void
    {
        if (get_transient('acym_ptaroles_activated')) {
            delete_transient('acym_ptaroles_activated');
            
            $acymail_url = admin_url('admin.php?page=acymailing_configuration#integrations');
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>AcyMailing PTA Roles Integration</strong> has been activated successfully! ';
            echo '<a href="' . esc_url($acymail_url) . '">Configure in AcyMailing</a></p>';
            echo '</div>';
        }
        
        // Show warning if dependencies are missing
        if (is_plugin_active(plugin_basename(__FILE__))) {
            if (!class_exists('PTARolesManager')) {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>AcyMailing PTA Roles Integration:</strong> PTA Roles Manager plugin is required but not active.</p>';
                echo '</div>';
            }
            
            if (!class_exists('AcyMailing\\Classes\\PluginClass')) {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>AcyMailing PTA Roles Integration:</strong> AcyMailing plugin is required but not active.</p>';
                echo '</div>';
            }
        }
    }

    public function disable(): void
    {
        if (!self::loadAcyMailingLibrary()) {
            return;
        }

        $pluginClass = new PluginClass();
        $pluginClass->disable(self::getIntegrationName());
    }

    public static function uninstall(): void
    {
        if (!self::loadAcyMailingLibrary()) {
            return;
        }

        $pluginClass = new PluginClass();
        $pluginClass->deleteByFolderName(self::getIntegrationName());
    }

    public function register(array &$integrations, string $acyVersion): void
    {
        // Check if PTA Roles Manager is active
        if (!class_exists('PTARolesManager') || !post_type_exists('pta_role')) {
            return;
        }
        
        // Register with AcyMailing (version 10.4.0+)
        if (version_compare($acyVersion, '10.4.0', '>=')) {
            $integrations[] = [
                'path' => __DIR__,
                'className' => self::INTEGRATION_PLUGIN_NAME,
            ];
        }
    }

    private static function getIntegrationName(): string
    {
        return strtolower(substr(self::INTEGRATION_PLUGIN_NAME, 7));
    }

    private static function loadAcyMailingLibrary(): bool
    {
        $ds = DIRECTORY_SEPARATOR;
        $vendorFolder = dirname(__DIR__).$ds.'acymailing'.$ds.'vendor';
        $helperFile = dirname(__DIR__).$ds.'acymailing'.$ds.'back'.$ds.'Core'.$ds.'init.php';

        return file_exists($vendorFolder) && include_once $helperFile;
    }
}

new AcyMailingIntegrationForPTARoles();
