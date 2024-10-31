<?php

/**
 * Plugin Name: PDF Importer for NinjaForms
 * Plugin URI: http://pdfimporter.rednao.com/getit
 * Description: Import a pdf and fill it with your entry information.
 * Author: RedNao
 * Author URI: http://rednao.com
 * Version: 1.3.66
 * Text Domain: rednaopdfimporter
 * Domain Path: /languages/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0
 * Slug: pdf-importer-for-ninjaforms
 */

use rnpdfimporter\core\Integration\Adapters\NinjaForms\Loader\NinjaFormsSubLoader;

require_once dirname(__FILE__).'/AutoLoad.php';

new NinjaFormsSubLoader('rnpdfimporterninjafrms','rednaopdfimpwpform',26,12,basename(__FILE__));