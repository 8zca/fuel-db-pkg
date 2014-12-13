<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.7
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2014 Fuel Development Team
 * @link       http://fuelphp.com
 */

Autoloader::add_core_namespace('Db\\Fuel');
Autoloader::add_classes(array(
    'Db\\Fuel\\Database_PDO_Connection' => __DIR__.'/classes/Fuel/Connection.php',
));

// Ensure the orm's config is loaded for Temporal
