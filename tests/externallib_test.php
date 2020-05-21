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

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/local/emedia_questionbanks/externallib.php');

/**
 * Local WsLTI tests class.
 *
 * @copyright 2020 eLearningMedia (https://elearningmedia.es/)
 * @author    Ramón Llavero
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_wslti_services_testcase extends externallib_advanced_testcase {

    /**
     * Rollback all changes in database.
     */
    protected function setUp() {
        $this->resetAfterTest(true);
    }

    /**
     * Tests to retrieve question banks.
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function test_local_wslti_get_currents_question_banks() {

        $this->resetAfterTest(true);

        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        // Create a course.
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        // Add quiz to course.
        $quiz = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        $quizcontext = context_module::instance($quiz->cmid);

        // Create a question in the default category.
        $contexts = new question_edit_contexts($context);
        $cat = question_make_default_categories($contexts->all());

        // Retrieve categories.
        $options = array(
            array('name' => 'includequizes', 'value' => 'true'),
            array('name' => 'includetops', 'value' => 'false')
        );
        $questionbanks = local_wslti_services::local_wslti_get_currents_question_banks('id', $course->id, $options);

        $coursecatfound = false;
        $quizcatfound = false;
        foreach ($questionbanks['question_banks'] as $questionbank) {
            list($catid, $catcontext) = explode(',', $questionbank->id);
            if ($catcontext == $context->id) {
                $coursecatfound = true;
            }
            if ($catcontext == $quizcontext->id) {
                $quizcatfound = true;
            }
        }

        $this->assertTrue($coursecatfound);
        $this->assertTrue($quizcatfound);

    }

    /**
     * Test retrieving questions.
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public function test_local_wslti_find_questions() {

        $this->resetAfterTest(true);

        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        $quizgen = $generator->get_plugin_generator('mod_quiz');
        $questiongenerator = $generator->get_plugin_generator('core_question');

        // Create a course.
        $course = $generator->create_course();

        // Add quiz to course.
        $quiz = $quizgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);

        // Add question to quiz.
        $quizcat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, ['category' => $quizcat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        // Retrieve question.
        $options = array(
            array('name' => 'addxml', 'value' => 'true'),
            array('name' => 'page', 'value' => '0'),
            array('name' => 'perpage', 'value' => '0')
        );
        $result = local_wslti_services::local_wslti_find_questions('id', $question->id, $options);

        // Verify that question is retrieved.
        $questions = $result['questions'];
        $this->assertNotEmpty($questions);

        // Verifi that the question includes XML export file.
        $xml = $questions[0]->exportfile;
        $this->assertContains('<?xml', $xml);
    }

    /**
     * Test xml string as question.
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public function test_local_wslti_import_questions() {

        $this->resetAfterTest(true);

        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        // Create a course.
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        // Create a question in the default category.
        $contexts = new question_edit_contexts($context);
        $cat = question_make_default_categories($contexts->all());

        // Import question.
        $xmltoimport = '<?xml version="1.0" encoding="UTF-8"?>
        <quiz>
            <question type="truefalse">
                <name>
                  <text>Question example</text>
                </name>
                <questiontext format="html">
                  <text><![CDATA[<p>example text<br></p>]]></text>
                </questiontext>
                <generalfeedback format="html">
                  <text><![CDATA[<p>feedback</p>]]></text>
                </generalfeedback>
                <defaultgrade>1.0000000</defaultgrade>
                <penalty>1.0000000</penalty>
                <hidden>0</hidden>
                <idnumber>123456</idnumber>
                <answer fraction="0" format="moodle_auto_format">
                  <text>true</text>
                  <feedback format="html">
                    <text><![CDATA[<p>correct answer</p>]]></text>
                  </feedback>
                </answer>
                <answer fraction="100" format="moodle_auto_format">
                  <text>false</text>
                  <feedback format="html">
                    <text><![CDATA[<p>wrong answer</p>]]></text>
                  </feedback>
                </answer>
            </question>
        </quiz>';
        $catidentifier = $cat->id.','.$cat->contextid;
        local_wslti_services::local_wslti_import_questions('cat_idandcontext', $catidentifier, $xmltoimport);

        // Verify that question exists.
        $options = array(
            array('name' => 'addxml', 'value' => 'false'),
            array('name' => 'page', 'value' => '0'),
            array('name' => 'perpage', 'value' => '0')
        );
        $result = local_wslti_services::local_wslti_find_questions('cat_id', $cat->id, $options);
        $questions = $result['questions'];
        $this->assertNotEmpty($questions);

    }

    /**
     * Test new question category creation.
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function test_local_wslti_create_question_category() {

        $this->resetAfterTest(true);

        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        // Create a course.
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        // Create a question in the default category.
        $contexts = new question_edit_contexts($context);
        $cat = question_make_default_categories($contexts->all());

        // Create question category in course context.
        $catparent = $cat->id.','.$cat->contextid;
        $catname = "New category";
        $catdesc = "New category description";
        $catidnumber = "newcat123";
        local_wslti_services::local_wslti_create_question_category('id', $course->id, $catparent,
            $catname, $catdesc, $catidnumber);

        // Retrieve categories.
        $options = array(
            array('name' => 'includequizes', 'value' => 'false'),
            array('name' => 'includetops', 'value' => 'false')
        );
        $questionbanks = local_wslti_services::local_wslti_get_currents_question_banks('id', $course->id, $options);

        $newcatfound = false;
        foreach ($questionbanks['question_banks'] as $questionbank) {
            if (strpos($questionbank->name, $catname)) {
                $newcatfound = true;
            }
        }
        $this->assertTrue($newcatfound);

    }

}