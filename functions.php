<?php
/**
 * Functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 * @since Twenty Twenty-One 1.0
 */

if (is_admin()) {
    include_once get_template_directory() . '/updater-theme.php';

    $updater = new Sofa1WPThemeUpdater(__FILE__);
    $updater->SetRepositoryName('themeTest');
    $updater->SetAuthorizationToken('testOAuthToken001');
    $updater->SetProxyUrl('flko.sofa1labs.at');
    $updater->SetRequiredWpVersion('5.7.0');
    $updater->SetTestedWpVersion('5.7.2');
    $updater->Init();
}
