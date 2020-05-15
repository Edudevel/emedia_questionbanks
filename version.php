<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * LTI Web services version file
 * @package   local_emedia_questionbanks
 * @copyright 2020 Edudevel Solutions S.L. info@edudevel.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2020051502;   // The (date) version of this module + 2 extra digital for daily versions.
$plugin->requires = 2010112400;  // Requires this Moodle version - at least 2.0.
$plugin->component = 'local_emedia_questionbanks';
$plugin->cron     = 0;
$plugin->release = '1.1 (Build: 2020051502)';
$plugin->maturity = MATURITY_STABLE;