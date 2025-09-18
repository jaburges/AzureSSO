<?php

use AcyMailing\Core\AcymPlugin;

if (!defined('ABSPATH')) exit;

class plgAcymPtaroles extends AcymPlugin
{
    public function __construct()
    {
        parent::__construct();
        $this->cms = 'WordPress';
        $this->installed = class_exists('PTARolesManager') && post_type_exists('pta_role');

        $this->pluginDescription->name = 'PTA Roles';
        $this->pluginDescription->icon = plugin_dir_url(__FILE__) . 'icon.png';
        $this->pluginDescription->category = 'Content management';
        $this->pluginDescription->description = '- Insert PTA role information and directories in emails<br/>- Show user assigned roles<br/>- Display open positions<br/>- Generate role directories<br/>- Requires PTA Roles Manager plugin';

        if ($this->installed) {
            // Plugin is properly installed and ready to use
            $this->settings = [
                'info' => [
                    'type' => 'custom',
                    'label' => '',
                    'content' => '<div class="acym__content__tab__card">
                        <h5>PTA Roles Integration Active</h5>
                        <p>This integration allows you to insert dynamic PTA role information in your emails.</p>
                        <p><strong>Available shortcodes:</strong></p>
                        <ul>
                            <li><code>{ptaroles:user_roles}</code> - User\'s assigned roles</li>
                            <li><code>{ptaroles:open_roles}</code> - List of open positions</li>
                            <li><code>{ptaroles:full_directory}</code> - Complete PTA directory</li>
                            <li>And 10 more options available in the email editor</li>
                        </ul>
                    </div>',
                ],
            ];
        } else {
            $this->settings = [
                'not_installed' => '1',
                'error_message' => 'PTA Roles Manager plugin is required but not installed or active.',
            ];
        }
    }

    public function getPossibleIntegrations()
    {
        return $this->pluginDescription;
    }

    public function dynamicText($mailId)
    {
        // Only show if PTA Roles Manager is active
        if (!$this->installed) {
            return null;
        }
        
        return $this->pluginDescription;
    }

    public function textPopup()
    {
        $insertionOptions = '<div class="grid-x acym__popup__listing">';
        
        // User-specific options (require user context)
        $insertionOptions .= '<div class="cell large-12"><h6 style="margin: 15px 0 10px 0; color: #0073aa;">User-Specific Information</h6></div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':user_roles}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">User\'s Assigned Roles</div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':user_roles_detailed}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">User\'s Roles (with descriptions)</div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':user_role_count}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">Number of User\'s Roles</div>';
        
        // General organization information
        $insertionOptions .= '<div class="cell large-12"><h6 style="margin: 15px 0 10px 0; color: #0073aa;">Organization Information</h6></div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':open_roles}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">List of Open Positions</div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':open_roles_count}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">Number of Open Positions</div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':executive_directory}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">Executive Board Directory</div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':committee_chairs}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">Committee Chairs Directory</div>';
        
        // Full directories
        $insertionOptions .= '<div class="cell large-12"><h6 style="margin: 15px 0 10px 0; color: #0073aa;">Complete Directories</h6></div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':full_directory}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">Complete PTA Directory</div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':full_directory_detailed}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">Complete Directory (with descriptions)</div>';
        
        // Statistics
        $insertionOptions .= '<div class="cell large-12"><h6 style="margin: 15px 0 10px 0; color: #0073aa;">Statistics</h6></div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':total_roles}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">Total Number of Roles</div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':filled_roles}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">Number of Filled Roles</div>';
        $insertionOptions .= '<div onclick="setTag(\'{'.$this->name.':volunteer_count}\', jQuery(this));" class="cell acym__row__no-listing acym__listing__row__popup">Total Number of Volunteers</div>';
        
        $insertionOptions .= '</div>';

        echo $insertionOptions;
    }

    /**
     * Replace content that's the same for all receivers
     */
    public function replaceContent(&$email, $send = true)
    {
        // Check if PTA Roles Manager is available
        if (!$this->installed) {
            return;
        }
        
        $extractedTags = $this->pluginHelper->extractTags($email, $this->name);
        if (empty($extractedTags)) {
            return;
        }

        $tags = [];
        foreach ($extractedTags as $i => $oneTag) {
            if (isset($tags[$i])) {
                continue;
            }
            
            $contentToInsert = '';
            
            switch ($oneTag->id) {
                case 'open_roles':
                    $contentToInsert = $this->getOpenRolesList();
                    break;
                case 'open_roles_count':
                    $contentToInsert = $this->getOpenRolesCount();
                    break;
                case 'executive_directory':
                    $contentToInsert = $this->getExecutiveDirectory();
                    break;
                case 'committee_chairs':
                    $contentToInsert = $this->getCommitteeChairs();
                    break;
                case 'full_directory':
                    $contentToInsert = $this->getFullDirectory();
                    break;
                case 'full_directory_detailed':
                    $contentToInsert = $this->getFullDirectoryDetailed();
                    break;
                case 'total_roles':
                    $contentToInsert = $this->getTotalRoles();
                    break;
                case 'filled_roles':
                    $contentToInsert = $this->getFilledRoles();
                    break;
                case 'volunteer_count':
                    $contentToInsert = $this->getVolunteerCount();
                    break;
            }
            
            $tags[$i] = $contentToInsert;
        }

        $this->pluginHelper->replaceTags($email, $tags);
    }

    /**
     * Replace content specific to each user
     */
    public function replaceUserInformation(&$email, &$user, $send = true)
    {
        // Check if PTA Roles Manager is available
        if (!$this->installed) {
            return;
        }
        
        $extractedTags = $this->pluginHelper->extractTags($email, $this->name);
        if (empty($extractedTags)) {
            return;
        }

        // Try to get WordPress user
        $wordpressUser = null;
        if (!empty($user->cms_id)) {
            $wordpressUser = get_userdata($user->cms_id);
        }

        if (empty($wordpressUser)) {
            $wordpressUser = get_user_by('email', $user->email);
        }

        $tags = [];
        foreach ($extractedTags as $i => $oneTag) {
            if (isset($tags[$i])) {
                continue;
            }

            $contentToInsert = '';

            if ($wordpressUser) {
                switch ($oneTag->id) {
                    case 'user_roles':
                        $contentToInsert = $this->getUserRoles($wordpressUser->ID);
                        break;
                    case 'user_roles_detailed':
                        $contentToInsert = $this->getUserRolesDetailed($wordpressUser->ID);
                        break;
                    case 'user_role_count':
                        $contentToInsert = $this->getUserRoleCount($wordpressUser->ID);
                        break;
                }
            }

            $tags[$i] = $contentToInsert;
        }

        $this->pluginHelper->replaceTags($email, $tags);
    }

    // Helper methods for content generation

    private function getOpenRolesList()
    {
        $roles = $this->getPTARoles(['status' => 'open']);
        if (empty($roles)) {
            return '<p><em>No open positions at this time.</em></p>';
        }

        $output = '<div class="pta-open-roles"><h4>Open Positions:</h4><ul>';
        foreach ($roles as $role) {
            $required = (int) get_post_meta($role->ID, '_pta_role_required_assignees', true) ?: 1;
            $assigned = count(array_filter((array) get_post_meta($role->ID, 'pta_assigned_users', true)));
            $open = $required - $assigned;
            
            $output .= '<li><strong>' . esc_html($role->post_title) . '</strong>';
            if ($open > 1) {
                $output .= ' (' . $open . ' positions needed)';
            }
            $output .= '</li>';
        }
        $output .= '</ul></div>';

        return $output;
    }

    private function getOpenRolesCount()
    {
        $roles = $this->getPTARoles(['status' => 'open']);
        $total_open = 0;
        
        foreach ($roles as $role) {
            $required = (int) get_post_meta($role->ID, '_pta_role_required_assignees', true) ?: 1;
            $assigned = count(array_filter((array) get_post_meta($role->ID, 'pta_assigned_users', true)));
            $total_open += ($required - $assigned);
        }
        
        return (string) $total_open;
    }

    private function getExecutiveDirectory()
    {
        $roles = $this->getPTARoles(['tags' => 'executive-board']);
        return $this->formatDirectory($roles, false);
    }

    private function getCommitteeChairs()
    {
        $roles = $this->getPTARoles(['tags' => 'committee-chair']);
        return $this->formatDirectory($roles, false);
    }

    private function getFullDirectory()
    {
        $roles = $this->getPTARoles();
        return $this->formatDirectory($roles, false);
    }

    private function getFullDirectoryDetailed()
    {
        $roles = $this->getPTARoles();
        return $this->formatDirectory($roles, true);
    }

    private function getTotalRoles()
    {
        $roles = $this->getPTARoles();
        return (string) count($roles);
    }

    private function getFilledRoles()
    {
        $roles = $this->getPTARoles();
        $filled = 0;
        
        foreach ($roles as $role) {
            $required = (int) get_post_meta($role->ID, '_pta_role_required_assignees', true) ?: 1;
            $assigned = count(array_filter((array) get_post_meta($role->ID, 'pta_assigned_users', true)));
            if ($assigned >= $required) {
                $filled++;
            }
        }
        
        return (string) $filled;
    }

    private function getVolunteerCount()
    {
        $roles = $this->getPTARoles();
        $volunteers = [];
        
        foreach ($roles as $role) {
            $assigned_users = (array) get_post_meta($role->ID, 'pta_assigned_users', true);
            foreach ($assigned_users as $user_id) {
                if (!empty($user_id)) {
                    $volunteers[$user_id] = true;
                }
            }
        }
        
        return (string) count($volunteers);
    }

    private function getUserRoles($user_id)
    {
        $user_roles = $this->getUserAssignedRoles($user_id);
        if (empty($user_roles)) {
            return '<em>No assigned roles</em>';
        }

        $role_names = [];
        foreach ($user_roles as $role) {
            $role_names[] = esc_html($role->post_title);
        }

        return implode(', ', $role_names);
    }

    private function getUserRolesDetailed($user_id)
    {
        $user_roles = $this->getUserAssignedRoles($user_id);
        if (empty($user_roles)) {
            return '<p><em>No assigned roles</em></p>';
        }

        $output = '<div class="user-pta-roles"><h4>Your PTA Roles:</h4><ul>';
        foreach ($user_roles as $role) {
            $description = get_post_meta($role->ID, '_pta_role_description', true);
            $output .= '<li><strong>' . esc_html($role->post_title) . '</strong>';
            if (!empty($description)) {
                $output .= '<br/><em>' . esc_html($description) . '</em>';
            }
            $output .= '</li>';
        }
        $output .= '</ul></div>';

        return $output;
    }

    private function getUserRoleCount($user_id)
    {
        $user_roles = $this->getUserAssignedRoles($user_id);
        return (string) count($user_roles);
    }

    // Core helper methods

    private function getPTARoles($filters = [])
    {
        $query_args = [
            'post_type' => 'pta_role',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ];

        // Add tag filtering if specified
        if (!empty($filters['tags'])) {
            $tag_slugs = array_filter(array_map('trim', explode(',', $filters['tags'])));
            if (!empty($tag_slugs)) {
                $query_args['tax_query'] = [
                    [
                        'taxonomy' => 'post_tag',
                        'field' => 'slug',
                        'terms' => $tag_slugs
                    ]
                ];
            }
        }

        $roles = get_posts($query_args);

        // Filter by status if specified
        if (!empty($filters['status']) && $filters['status'] === 'open') {
            $filtered_roles = [];
            foreach ($roles as $role) {
                $assigned_users = (array) get_post_meta($role->ID, 'pta_assigned_users', true);
                $required_assignees = (int) get_post_meta($role->ID, '_pta_role_required_assignees', true) ?: 1;
                $current_count = count(array_filter($assigned_users));
                
                if ($current_count < $required_assignees) {
                    $filtered_roles[] = $role;
                }
            }
            return $filtered_roles;
        }

        return $roles;
    }

    private function getUserAssignedRoles($user_id)
    {
        $query_args = [
            'post_type' => 'pta_role',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'pta_assigned_users',
                    'value' => $user_id,
                    'compare' => 'LIKE'
                ]
            ],
            'post_status' => 'publish'
        ];

        return get_posts($query_args);
    }

    private function formatDirectory($roles, $detailed = false)
    {
        if (empty($roles)) {
            return '<p><em>No roles found.</em></p>';
        }

        if ($detailed) {
            return $this->formatDetailedDirectory($roles);
        } else {
            return $this->formatSimpleDirectory($roles);
        }
    }

    private function formatSimpleDirectory($roles)
    {
        $output = '<div class="pta-directory-simple"><table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;">';
        $output .= '<thead><tr style="background:#f5f5f5;"><th>Role</th><th>Assigned To</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($roles as $role) {
            $assigned_users = (array) get_post_meta($role->ID, 'pta_assigned_users', true);
            $required_assignees = (int) get_post_meta($role->ID, '_pta_role_required_assignees', true) ?: 1;
            $user_names = [];

            foreach ($assigned_users as $uid) {
                if (!empty($uid)) {
                    $user = get_userdata($uid);
                    if ($user) {
                        $user_names[] = esc_html($user->display_name);
                    }
                }
            }

            $current_count = count($user_names);
            $open_positions = $required_assignees - $current_count;

            $output .= '<tr>';
            $output .= '<td><strong>' . esc_html($role->post_title) . '</strong></td>';
            $output .= '<td>';
            
            if (!empty($user_names)) {
                $output .= implode(', ', $user_names);
                if ($open_positions > 0) {
                    $output .= ' <em>(+' . $open_positions . ' open)</em>';
                }
            } else {
                $output .= '<em>Open (' . $required_assignees . ' position' . ($required_assignees > 1 ? 's' : '') . ')</em>';
            }
            
            $output .= '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';
        return $output;
    }

    private function formatDetailedDirectory($roles)
    {
        $output = '<div class="pta-directory-detailed">';

        foreach ($roles as $role) {
            $assigned_users = (array) get_post_meta($role->ID, 'pta_assigned_users', true);
            $required_assignees = (int) get_post_meta($role->ID, '_pta_role_required_assignees', true) ?: 1;
            $description = get_post_meta($role->ID, '_pta_role_description', true);
            $user_names = [];

            foreach ($assigned_users as $uid) {
                if (!empty($uid)) {
                    $user = get_userdata($uid);
                    if ($user) {
                        $user_names[] = esc_html($user->display_name);
                    }
                }
            }

            $current_count = count($user_names);
            $open_positions = $required_assignees - $current_count;

            $output .= '<div style="margin-bottom:20px; padding:15px; border:1px solid #ddd; border-radius:5px;">';
            $output .= '<h4 style="margin:0 0 10px 0;">' . esc_html($role->post_title) . '</h4>';
            
            if (!empty($description)) {
                $output .= '<p style="margin:5px 0; font-style:italic;">' . esc_html($description) . '</p>';
            }
            
            $output .= '<p style="margin:5px 0;"><strong>Current Holders (' . $current_count . '/' . $required_assignees . '):</strong> ';
            
            if (!empty($user_names)) {
                $output .= implode(', ', $user_names);
                if ($open_positions > 0) {
                    $output .= ' <em>(+' . $open_positions . ' position' . ($open_positions > 1 ? 's' : '') . ' available)</em>';
                }
            } else {
                $output .= '<em>' . $required_assignees . ' position' . ($required_assignees > 1 ? 's' : '') . ' available</em>';
            }
            
            $output .= '</p>';
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }
}
