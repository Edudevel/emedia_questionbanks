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
 * LTI Web services local plugin template external functions and service definitions.
 *
 * @package    local_emedia_questionbanks
 * @copyright  2020 Edudevel Solutions S.L. info@edudevel.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// We defined the web service functions to install.
$functions = array(
    'local_wslti_get_currents_question_banks' => array(
        'classname'   => 'local_wslti_services',
        'methodname'  => 'local_wslti_get_currents_question_banks',
        'classpath'   => 'local/emedia_questionbanks/externallib.php',
        'description' => 'Get availables categories in question bank for the current course.',
        'type'        => 'read',
        'capabilities' => '',
    ),
    'local_wslti_find_questions' => array(
        'classname'   => 'local_wslti_services',
        'methodname'  => 'local_wslti_find_questions',
        'classpath'   => 'local/emedia_questionbanks/externallib.php',
        'description' => 'Find questions by criteria.',
        'type'        => 'read',
        'capabilities' => 'moodle/question:viewmine',
    ),
    'local_wslti_import_questions' => array(
        'classname'   => 'local_wslti_services',
        'methodname'  => 'local_wslti_import_questions',
        'classpath'   => 'local/emedia_questionbanks/externallib.php',
        'description' => 'Import questions into question bank.',
        'type'        => 'write',
        'capabilities' => 'moodle/question:add',
    ),
    'local_wslti_create_question_category' => array(
        'classname'   => 'local_wslti_services',
        'methodname'  => 'local_wslti_create_question_category',
        'classpath'   => 'local/emedia_questionbanks/externallib.php',
        'description' => 'Create question category.',
        'type'        => 'write',
        'capabilities' => 'moodle/question:add',
    )
);