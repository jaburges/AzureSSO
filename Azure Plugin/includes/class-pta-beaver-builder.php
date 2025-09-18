<?php
/**
 * Beaver Builder Module for PTA Roles
 */

if (!defined('ABSPATH')) {
    exit;
}

class Azure_PTA_BeaverBuilder {
    
    public function __construct() {
        // Only initialize if Beaver Builder is active
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        if (!class_exists('FLBuilder')) {
            return;
        }
        
        add_action('init', array($this, 'load_modules'));
    }
    
    public function load_modules() {
        // Only load modules if Beaver Builder is available
        if (class_exists('FLBuilderModule')) {
            require_once AZURE_PLUGIN_PATH . 'includes/beaver-builder/pta-roles-directory/pta-roles-directory.php';
            require_once AZURE_PLUGIN_PATH . 'includes/beaver-builder/pta-department-roles/pta-department-roles.php';
            require_once AZURE_PLUGIN_PATH . 'includes/beaver-builder/pta-org-chart/pta-org-chart.php';
            require_once AZURE_PLUGIN_PATH . 'includes/beaver-builder/pta-open-positions/pta-open-positions.php';
        } else {
            Azure_Logger::debug('Beaver Builder not available - skipping module loading');
        }
    }
}

// Only define Beaver Builder modules if Beaver Builder is available
if (class_exists('FLBuilderModule')) {

/**
 * PTA Roles Directory Module
 */
class PTARolesDirectoryModule extends FLBuilderModule {
    
    public function __construct() {
        parent::__construct(array(
            'name'            => __('PTA Roles Directory', 'azure-plugin'),
            'description'     => __('Display a directory of PTA roles with filtering options', 'azure-plugin'),
            'group'           => __('Azure Plugin', 'azure-plugin'),
            'category'        => __('PTA Modules', 'azure-plugin'),
            'dir'             => AZURE_PLUGIN_PATH . 'includes/beaver-builder/pta-roles-directory/',
            'url'             => AZURE_PLUGIN_URL . 'includes/beaver-builder/pta-roles-directory/',
            'editor_export'   => true,
            'enabled'         => true,
            'icon'            => 'networking.svg'
        ));
    }
}

/**
 * PTA Department Roles Module
 */
class PTADepartmentRolesModule extends FLBuilderModule {
    
    public function __construct() {
        parent::__construct(array(
            'name'            => __('PTA Department Roles', 'azure-plugin'),
            'description'     => __('Display roles for a specific department', 'azure-plugin'),
            'group'           => __('Azure Plugin', 'azure-plugin'),
            'category'        => __('PTA Modules', 'azure-plugin'),
            'dir'             => AZURE_PLUGIN_PATH . 'includes/beaver-builder/pta-department-roles/',
            'url'             => AZURE_PLUGIN_URL . 'includes/beaver-builder/pta-department-roles/',
            'editor_export'   => true,
            'enabled'         => true,
            'icon'            => 'groups.svg'
        ));
    }
}

/**
 * PTA Org Chart Module
 */
class PTAOrgChartModule extends FLBuilderModule {
    
    public function __construct() {
        parent::__construct(array(
            'name'            => __('PTA Org Chart', 'azure-plugin'),
            'description'     => __('Display an interactive organizational chart', 'azure-plugin'),
            'group'           => __('Azure Plugin', 'azure-plugin'),
            'category'        => __('PTA Modules', 'azure-plugin'),
            'dir'             => AZURE_PLUGIN_PATH . 'includes/beaver-builder/pta-org-chart/',
            'url'             => AZURE_PLUGIN_URL . 'includes/beaver-builder/pta-org-chart/',
            'editor_export'   => true,
            'enabled'         => true,
            'icon'            => 'chart-organization.svg'
        ));
    }
}

/**
 * PTA Open Positions Module
 */
class PTAOpenPositionsModule extends FLBuilderModule {
    
    public function __construct() {
        parent::__construct(array(
            'name'            => __('PTA Open Positions', 'azure-plugin'),
            'description'     => __('Display currently open PTA positions', 'azure-plugin'),
            'group'           => __('Azure Plugin', 'azure-plugin'),
            'category'        => __('PTA Modules', 'azure-plugin'),
            'dir'             => AZURE_PLUGIN_PATH . 'includes/beaver-builder/pta-open-positions/',
            'url'             => AZURE_PLUGIN_URL . 'includes/beaver-builder/pta-open-positions/',
            'editor_export'   => true,
            'enabled'         => true,
            'icon'            => 'megaphone.svg'
        ));
    }
}

} // End if (class_exists('FLBuilderModule'))

new Azure_PTA_BeaverBuilder();


