<?php

/**
 * Version file for component local_bulk_enrol.
 *
 * @package         local_bulk_enrol
 * @author          Lucas Catalan <catalan.munoz.l@gmail.com>
 */

defined('MOODLE_INTERNAL') || die;


$componentname = 'local_bulk_enrol';
$pluginname = 'bulk_enrol';


// Default for users that have site config.
if ($hassiteconfig) {

    // Add the category to the local plugin branch.
    $ADMIN->add('localplugins', new \admin_category($componentname, get_string('pluginname', $componentname)));

    // Create a settings page for local_bcn_mailer.
    $settingspage = new \admin_settingpage($pluginname, get_string('pluginname', $componentname));

    // Make a container for all of the settings for the settings page.
    $settings = [];

    $settings[] = new admin_setting_configtext(
        $pluginname.'/destiny_endpoint',
        new lang_string('destiny_endpoint', $componentname),
        new lang_string('destiny_endpoint', $componentname),
        ''
    );

    $settings[] = new admin_setting_configtext(
        $pluginname.'/endpoint_username',
        new lang_string('endpoint_username', $componentname),
        new lang_string('endpoint_username', $componentname),
        ''
    );

    $settings[] = new admin_setting_configpasswordunmask(
        $pluginname.'/endpoint_password',
        new lang_string('endpoint_password', $componentname),
        new lang_string('endpoint_password', $componentname),
        ''
    );

    $settings[] = new admin_setting_configtext(
        $pluginname.'/current_token',
        new lang_string('current_token', $componentname),
        new lang_string('current_token', $componentname),
        ''
    );


    // Add all the settings to the settings page.
    foreach ($settings as $setting) {
        $settingspage->add($setting);
    }

    // Add the settings page to the nav tree.
    $ADMIN->add($componentname, $settingspage);


}
