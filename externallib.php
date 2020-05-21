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
 * External LTI Web Services
 *
 * @package   local_emedia_questionbanks
 * @copyright 2020 Edudevel Solutions S.L. info@edudevel.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use enrol_lti\helper;

require_once($CFG->libdir  . '/externallib.php');
require_once($CFG->dirroot . '/enrol/lti/classes/helper.php');
require_once($CFG->libdir  . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/files/externallib.php');
require_once($CFG->dirroot . '/question/category_class.php');
require_once($CFG->dirroot . '/question/editlib.php');

/**
 * Class local_wslti_services
 *
 * @copyright 2020 eLearningMedia (https://elearningmedia.es/)
 * @author    Ramón Llavero
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_wslti_services extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function local_wslti_get_currents_question_banks_parameters() {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_ALPHA, 'The field to search course.
                    id: course id
                    shortname: course short name
                    idnumber: course id number
                ', VALUE_DEFAULT, 'id'),
                'value' => new external_value(PARAM_RAW, 'The value to match', VALUE_DEFAULT, ''),
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHAEXT, 'Settings for the result:
                                            "includequizes" (bool) If yes(default) includes quizes categories in result,
                                            "includetops" (bool) Includes top categories. Default=false'
                            ),
                            'value' => new external_value(PARAM_RAW, 'the value for the option')
                        )
                    ), VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Get available question banks in course.
     * @param string $field field to find course
     * @param string $value valuet to match
     * @param array $options options for the result
     * @return array question banks
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function local_wslti_get_currents_question_banks($field = 'id', $value, $options = array()) {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::local_wslti_get_currents_question_banks_parameters(),
            array('field' => $field, 'value' => $value, 'options' => $options));

        // Clean parameters.
        switch ($params['field']) {
            case 'id':
                $value = clean_param($params['value'], PARAM_INT);
                break;
            case 'shortname':
                $value = clean_param($params['value'], PARAM_TEXT);
                break;
            case 'idnumber':
                $value = clean_param($params['value'], PARAM_RAW);
                break;
            default:
                throw new invalid_parameter_exception('Invalid field name');
        }

        // Default settings.
        $includequizes = true;
        $includetops = false;

        // Overwrite default values with options.
        if (isset($params['options'])) {
            foreach ($params['options'] as $option) {
                switch ($option['name']) {
                    case 'includequizes':
                        $includequizes = clean_param($option['value'], PARAM_BOOL);
                        break;
                    case 'includetops':
                        $includetops = clean_param($option['value'], PARAM_BOOL);
                        break;
                }
            }
        }

        // Retrieve course and context.
        $course = $DB->get_record('course', array($params['field'] => $value), '*',
            MUST_EXIST);
        $courseid = $course->id;
        $thiscontext = context_course::instance($courseid);

        // Get affected contexts from desired course.
        $contexts = (new question_edit_contexts($thiscontext))->all();

        if ($includequizes) {
            // Retrieve quizes contexts and add to array.
            $modinfo = get_fast_modinfo($courseid);
            $mods = $modinfo->get_cms();
            foreach ($mods as $mod) {
                if ($mod->modname != 'quiz') {
                    continue;
                }
                $quizcontext = context_module::instance($mod->id);
                array_unshift($contexts, $quizcontext);
            }
        }

        // Create default categories for course.
        $defaultcategory = question_make_default_categories($contexts);

        // Calculate categories.
        $categories = question_category_options($contexts, $includetops, $defaultcategory, false, -1);
        if ($includequizes) {
            $categories = array_reverse($categories);
        }

        // Compose response.
        $questionbanks = array();
        foreach ($categories as $catgroup => $catitems) {
            foreach ($catitems as $catid => $catname) {
                $catname = str_replace("&nbsp;", "-", $catname);
                $catresult = new stdClass();
                $catresult->id = $catid;
                $catresult->name = trim($catname);
                $questionbanks[] = $catresult;
            }
        }

        // Return result.
        $result = array();
        $result['question_banks'] = $questionbanks;
        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function local_wslti_get_currents_question_banks_returns() {
        $structure = array(
            'id' => new external_value(PARAM_TEXT, 'Id of the category'),
            'name' => new external_value(PARAM_TEXT, 'Category name. Also shows number of questions in category'),
        );
        return new external_single_structure(
            array(
                'question_banks' => new external_multiple_structure(new external_single_structure($structure),
                    'question banks'),
            )
        );
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function local_wslti_find_questions_parameters() {
        return new external_function_parameters(
            array(
                'field'   => new external_value(PARAM_RAW, 'The field to search the category.
                    id: question id (only one result),
                    idnumber: question id number (only one result),
                    ids: question ids (multiple results),
                    cat_id: category id (multiple results),
                    cat_idandcontext: category id and context id (multiple results),
                    cat_idnumber: category id number (multiple results)
                ', VALUE_DEFAULT, 'id'),
                'value'   => new external_value(PARAM_RAW, 'The value to match', VALUE_DEFAULT, ''),
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHAEXT, 'Option for the search:
                                            "addxml" (bool) If true export xml file will be added,
                                            "page" (int) Actual page,
                                            "perpage" (int) Rows in each page'
                            ),
                            'value' => new external_value(PARAM_RAW, 'the value for the option')
                        )
                    ), VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Find cuestions by criteria.
     * @param string $field field to search category
     * @param string $value value to match
     * @param array $options options for the search
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function local_wslti_find_questions($field = 'id', $value, $options = array()) {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::local_wslti_find_questions_parameters(),
            array('field' => $field, 'value' => $value, 'options' => $options));

        // Clean parameters and compose where clause.
        $sqlparams = array();
        switch ($params['field']) {
            case 'id':
                $value = clean_param($params['value'], PARAM_INT);
                $sqlparams['id'] = $value;
                $where = "qu.id = :id";
                break;
            case 'idnumber':
                $value = clean_param($params['value'], PARAM_RAW);
                $sqlparams['idnumber'] = $value;
                $where = "qu.idnumber = :idnumber";
                break;
            case 'ids':
                $value = clean_param($params['value'], PARAM_SEQUENCE);
                $ids = explode(',', $value);
                list($sqlids, $sqlparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $where = "qu.id {$sqlids}";
                break;
            case 'cat_id':
                $value = clean_param($params['value'], PARAM_INT);
                $sqlparams['id'] = $value;
                $where = "qc.id = :id";
                break;
            case 'cat_idandcontext':
                $value = clean_param($params['value'], PARAM_TEXT);
                list($catid, $catcontext) = explode(',', $value);
                $sqlparams['id'] = $catid;
                $sqlparams['contextid'] = $catcontext;
                $where = "qc.id = :id AND qc.contextid = :contextid";
                break;
            case 'cat_idnumber':
                $value = clean_param($params['value'], PARAM_RAW);
                $sqlparams['idnumber'] = $value;
                $where = "qc.idnumber = :idnumber";
                break;
            default:
                throw new invalid_parameter_exception('Invalid field name');
        }

        // Default settings.
        $addxml = false;
        $page = $perpage = 0;

        // Overwrite default values with options.
        if (isset($params['options'])) {
            foreach ($params['options'] as $option) {
                switch ($option['name']) {
                    case 'addxml':
                        $addxml = clean_param($option['value'], PARAM_BOOL);
                        break;
                    case 'page':
                        $page = clean_param($option['value'], PARAM_INT);
                        break;
                    case 'perpage':
                        $perpage = clean_param($option['value'], PARAM_INT);
                        break;
                }
            }
        }

        // Define rows to get.
        $limitfrom = $limitnum = 0;
        if ($perpage > 0) {
            $limitfrom = $page * $perpage;
            $limitnum = $perpage;
        }

        // Build query.
        $sql = "SELECT qu.*
                  FROM {question} qu
                  JOIN {question_categories} qc ON qu.category = qc.id
                 WHERE $where";
        $questions = $DB->get_records_sql($sql, $sqlparams, $limitfrom, $limitnum);

        // Build response.
        $qresults = array();
        foreach ($questions as $key => $question) {

            $qresult = new stdClass();
            $qresult->id = $question->id;
            $qresult->category = $question->category;
            $qresult->parent = $question->parent;
            $qresult->name = $question->name;
            $qresult->qtype = $question->qtype;
            $qresult->idnumber = $question->idnumber;
            $qresult->timemodified = $question->timemodified;

            // Generate export file.
            $qresult->exportfile = '';
            if ($addxml) {
                $xml = self::question_to_xml($question);
                $qresult->exportfile = $xml;
            }

            $qresults[] = $qresult;
        }

        // Return result.
        $result = array();
        $result['questions'] = $qresults;
        return $result;
    }

    /**
     * Generates export XML file for a question.
     * @param object $question
     * @return string|null returns export file for a question
     * @throws coding_exception
     */
    private static function question_to_xml($question) {

        // Generate question metadata.
        $question->export_process = true;
        $qtype = question_bank::get_qtype($question->qtype, false);
        if ($qtype->name() == 'missingtype') {
            return null;
        }
        $qtype->get_question_options($question);

        // Compose questions array to export and dummy course.
        $questions = array();
        $questions[] = $question;
        $course = new stdClass();
        $course->id = 0;

        // Call to exporter.
        $qformat = new qformat_xml();
        $qformat->setCourse($course);
        $qformat->setQuestions($questions);
        $qformat->setStoponerror(0);
        $qformat->setCattofile(0);
        $qformat->setContexttofile(0);
        $qformat->set_display_progress(false);
        $out = $qformat->exportprocess();

        return $out;
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function local_wslti_find_questions_returns() {
        return new external_single_structure(
            array(
                'questions' => new external_multiple_structure(self::get_question_structure(),
                    'questions'),
            )
        );
    }

    /**
     * Method that describes the structure of the objects in the response.
     * @return external_single_structure structure for response
     */
    protected static function get_question_structure() {
        $structure = array(
            'id' => new external_value(PARAM_INT, 'Id of the question'),
            'category' => new external_value(PARAM_INT, 'Category of the question'),
            'parent' => new external_value(PARAM_INT, 'Parent of the question'),
            'name' => new external_value(PARAM_TEXT, 'Name of the question'),
            'qtype' => new external_value(PARAM_TEXT, 'Type of the question'),
            'idnumber' => new external_value(PARAM_RAW, 'Id number of the question'),
            'timemodified' => new external_value(PARAM_INT, 'Last modification time of the question'),
            'exportfile' => new external_value(PARAM_RAW, 'Question in export file'),
        );
        return new external_single_structure($structure);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function local_wslti_import_questions_parameters() {
        return new external_function_parameters(
            array(
                'field'   => new external_value(PARAM_RAW, 'The field to search the category to import in.
                    cat_id: category id,
                    cat_idandcontext: category id and context id,
                    cat_idnumber: category id number
                ', VALUE_DEFAULT, 'cat_id'),
                'value' => new external_value(PARAM_RAW, 'The value to match', VALUE_DEFAULT, ''),
                'xml' => new external_value(PARAM_RAW, 'XML to import', VALUE_DEFAULT, ''),
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHAEXT, 'Option for the import process:
                                            "matchgrades" (string) Match grade mode
                                                (error: Fail if there is not a grade equals to the question,
                                                nearest: Select nearest grade if not found some equals) Default=error,
                                            "stoponerror" (int) Stop import on error (0=No, 1=Yes) Default=yes'
                            ),
                            'value' => new external_value(PARAM_RAW, 'the value for the option')
                        )
                    ), VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Import questions into selected category
     * @param string $field field to search category
     * @param string $value value to match
     * @param string $xml import file
     * @param array $options settings for the import process
     * @return array imported questions
     * @throws invalid_parameter_exception
     */
    public static function local_wslti_import_questions($field = 'cat_id', $value = '', $xml = '', $options = array()) {
        global $DB, $CFG;

        // Validate parameters.
        $params = self::validate_parameters(self::local_wslti_import_questions_parameters(),
            array('field' => $field, 'value' => $value, 'xml' => $xml, 'options' => $options));

        // Default import settings.
        $matchgrades = 'error';
        $stoponerror = 1;

        // Overwrite default values with options.
        if (isset($params['options'])) {
            foreach ($params['options'] as $option) {
                switch ($option['name']) {
                    case 'matchgrades':
                        $matchgrades = clean_param($option['value'], PARAM_ALPHA);
                        break;
                    case 'stoponerror':
                        $stoponerror = clean_param($option['value'], PARAM_INT);
                        break;
                }
            }
        }

        // Clean parameters and compose where clause.
        $sqlparams = array();
        switch ($params['field']) {
            case 'cat_id':
                $value = clean_param($params['value'], PARAM_INT);
                $sqlparams['id'] = $value;
                $where = "qc.id = :id";
                break;
            case 'cat_idandcontext':
                $value = clean_param($params['value'], PARAM_TEXT);
                list($catid, $catcontext) = explode(',', $value);
                $sqlparams['id'] = $catid;
                $sqlparams['contextid'] = $catcontext;
                $where = "qc.id = :id AND qc.contextid = :contextid";
                break;
            case 'cat_idnumber':
                $value = clean_param($params['value'], PARAM_RAW);
                $sqlparams['idnumber'] = $value;
                $where = "qc.idnumber = :idnumber";
                break;
            default:
                throw new invalid_parameter_exception('Invalid field name');
        }

        // Build query.
        $sql = "SELECT qc.*
                  FROM {question_categories} qc
                 WHERE $where";
        $category = $DB->get_record_sql($sql, $sqlparams, MUST_EXIST);
        $categorycontext = context::instance_by_id($category->contextid);
        $category->context = $categorycontext;
        $contexts = new question_edit_contexts($categorycontext);

        // Upload the file.
        $dirname = gmdate("ymdHis") .'_'. random_string(6);
        $tmpdir = "questionimports/$dirname";
        $filename = $dirname . '.xml';
        $importfile = "{$CFG->tempdir}/$tmpdir/{$filename}";
        make_temp_directory("$tmpdir");
        $uploaded = self::upload_xml_file($filename, $xml, $importfile);

        // Call to exporter.
        $course = new stdClass();
        $course->id = 0;

        $qformat = new qformat_xml();
        $qformat->setCategory($category);
        $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
        $qformat->setCourse($course);
        $qformat->setFilename($importfile);
        $qformat->setRealfilename($filename);
        $qformat->setMatchgrades($matchgrades);
        $qformat->setCatfromfile(0);
        $qformat->setContextfromfile(0);
        $qformat->setStoponerror($stoponerror);
        try {
            $qformat->set_display_progress(false);
        } catch (Exception $e) {
            $course->id = 0;
        }

        $questionids = '';
        try {
            // Process the uploaded file.
            $qformat->importprocess();
            $questionids = $qformat->questionids;

        } catch (Exception $e) {
            $questionids = '';
        } finally {
            // Delete temp file.
            fulldelete("{$CFG->tempdir}/$tmpdir");
        }

        // Return result.
        if ($questionids != '') {
            $searchoptions = array(
                array('name' => 'addxml', 'value' => 'true')
            );
            $ids = implode(",", $qformat->questionids);
            $importeds = self::local_wslti_find_questions('ids', $ids, $searchoptions);
            $result = $importeds;
        } else {
            $result = array();
            $result['questions'] = array();
        }

        return $result;
    }

    /**
     * Function for uploading files and move to desired location
     * @param string $filename filename
     * @param string $xml xml string to save
     * @param string $temppath desired location
     * @return bool true if uploaded succesfully
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function upload_xml_file ($filename = '', $xml = '', $temppath = '') {
        global $USER;

        // Create context.
        $context = context_user::instance($USER->id);
        $contextid = $context->id;
        $component = "user";
        $filearea = "draft";
        $itemid = 0;
        $filepath = "/";
        $filecontent = base64_encode($xml);
        $contextlevel = null;
        $instanceid = null;

        // Call the api to create a file.
        $fileinfo = core_files_external::upload($contextid, $component, $filearea, $itemid, $filepath,
            $filename, $filecontent, $contextlevel, $instanceid);
        // Get the created draft item id.
        $itemid = $fileinfo['itemid'];

        // Copy to desired location for import (Current location is draft).
        $browser = get_file_browser();
        $uploadedfile = $browser->get_file_info($context, $component, $filearea, $itemid, $filepath, $filename);
        $uploadedfile->copy_to_pathname($temppath);

        // Delete uploaded file.
        if ($uploadedfile) {
            $uploadedfile->delete();
        }

        return true;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function local_wslti_import_questions_returns() {
        return new external_single_structure(
            array(
                'questions' => new external_multiple_structure(self::get_question_structure(),
                    'questions'),
            )
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function local_wslti_create_question_category_parameters() {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_ALPHA, 'The field to search course.
                    id: course id
                    shortname: course short name
                    idnumber: course id number
                ', VALUE_DEFAULT, 'id'),
                'value' => new external_value(PARAM_RAW, 'The value to match', VALUE_DEFAULT, ''),
                'catparent' => new external_value(PARAM_TEXT, 'Id and context of the parent category', VALUE_DEFAULT, ''),
                'catname' => new external_value(PARAM_TEXT, 'Name of the new category', VALUE_DEFAULT, ''),
                'catdesc' => new external_value(PARAM_TEXT, 'Description of the new category', VALUE_DEFAULT, ''),
                'catidnumber' => new external_value(PARAM_RAW, 'Idnumber of the new category', VALUE_DEFAULT, ''),
            )
        );
    }

    /**
     * Creates a new question category
     * @param string $field field to search course context
     * @param string $value value to match
     * @param string $catparent id and context of the parent category
     * @param string $catname name of the new category
     * @param string $catdesc description of the new category
     * @param string $catidnumber idnumber of the new category
     * @return array created categories
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function local_wslti_create_question_category ($field = 'id', $value, $catparent = '', $catname = '',
                                                                 $catdesc = '', $catidnumber = '') {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::local_wslti_create_question_category_parameters(),
            array('field' => $field, 'value' => $value, 'catparent' => $catparent, 'catname' => $catname,
                'catdesc' => $catdesc, 'catidnumber' => $catidnumber));

        // Clean parameters.
        switch ($params['field']) {
            case 'id':
                $value = clean_param($params['value'], PARAM_INT);
                break;
            case 'shortname':
                $value = clean_param($params['value'], PARAM_TEXT);
                break;
            case 'idnumber':
                $value = clean_param($params['value'], PARAM_RAW);
                break;
            default:
                throw new invalid_parameter_exception('Invalid field name');
        }

        // Retrieve course and context.
        $course = $DB->get_record('course', array($params['field'] => $value), '*',
            MUST_EXIST);
        $courseid = $course->id;
        $thiscontext = context_course::instance($courseid);

        // Get affected contexts from desired course.
        $contexts = new question_edit_contexts($thiscontext);

        // Create new category.
        $qcobject = new question_category_object(null, '',
            $contexts->having_one_edit_tab_cap('categories'), 0,
            null, 0, $contexts->having_cap('moodle/question:add'));
        $catid = $qcobject->add_category($params['catparent'], $params['catname'], $params['catdesc'],
            true, 1, $params['catidnumber']);

        // Compose response.
        $questionbanks = array();
        $catresult = new stdClass();
        $catresult->id = $catid;
        $catresult->name = trim($catname);
        $questionbanks[] = $catresult;

        // Return result.
        $result = array();
        $result['question_banks'] = $questionbanks;
        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function local_wslti_create_question_category_returns() {
        $structure = array(
            'id' => new external_value(PARAM_TEXT, 'Id of the category'),
            'name' => new external_value(PARAM_TEXT, 'Category name.'),
        );
        return new external_single_structure(
            array(
                'question_banks' => new external_multiple_structure(new external_single_structure($structure),
                    'question banks'),
            )
        );
    }

}