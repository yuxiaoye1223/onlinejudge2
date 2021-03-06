<?php

require_once('../../../../config.php');
require_once("$CFG->dirroot/mod/assignment/lib.php");
require_once('testcase_form.php');

$id = optional_param('id', 0, PARAM_INT);  // Course Module ID
$a  = optional_param('a', 0, PARAM_INT);   // Assignment ID

$url = new moodle_url('/mod/assignment/type/onlinejudge/testcase.php');
if ($id) {
    if (! $cm = get_coursemodule_from_id('assignment', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $assignment = $DB->get_record("assignment", array("id"=>$cm->instance))) {
        print_error('invalidid', 'assignment');
    }

    if (! $course = $DB->get_record("course", array("id"=>$assignment->course))) {
        print_error('coursemisconf', 'assignment');
    }
    $url->param('id', $id);
} else {
    if (!$assignment = $DB->get_record("assignment", array("id"=>$a))) {
        print_error('invalidid', 'assignment');
    }
    if (! $course = $DB->get_record("course", array("id"=>$assignment->course))) {
        print_error('coursemisconf', 'assignment');
    }
    if (! $cm = get_coursemodule_from_instance("assignment", $assignment->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

$PAGE->set_url($url);
require_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/assignment:grade', $context);

$testform = new testcase_form($DB->count_records('assignment_oj_testcases', array('assignment' => $assignment->id, 'unused' => '0')));

if ($testform->is_cancelled()){

	redirect($CFG->wwwroot.'/mod/assignment/view.php?id='.$id);

} else if ($fromform = $testform->get_data()){

    // Mark old testcases as unused
	$DB->set_field('assignment_oj_testcases', 'unused', '1', array('assignment' => $assignment->id));

	for ($i = 0; $i < $fromform->boundary_repeats; $i++) {
        if (emptycase($fromform, $i))
            continue;

        if (isset($fromform->usefile[$i])) {
            $testcase->usefile = true;
			$testcase->inputfile = $fromform->inputfile[$i];
			$testcase->outputfile = $fromform->outputfile[$i];
        } else {
            $testcase->usefile = false;
			$testcase->input = $fromform->input[$i];
			$testcase->output = $fromform->output[$i];
        }

        $testcase->feedback = $fromform->feedback[$i];
        $testcase->subgrade = $fromform->subgrade[$i];
        $testcase->assignment = $assignment->id;

        $testcase_id = $DB->insert_record('assignment_oj_testcases', $testcase);

        if ($testcase->usefile) {
            file_save_draft_area_files($testcase->inputfile, $context->id, 'mod_assignment', 'onlinejudge_input', $testcase_id);
            file_save_draft_area_files($testcase->outputfile, $context->id, 'mod_assignment', 'onlinejudge_output', $testcase_id);
        }

        unset($testcase);
	}

	redirect($CFG->wwwroot.'/mod/assignment/view.php?id='.$id);    

} else {

    $assignmentinstance = new assignment_onlinejudge($cm->id, $assignment, $cm, $course);
    $assignmentinstance->view_header();

    $testcases = $DB->get_records('assignment_oj_testcases', array('assignment' => $assignment->id, 'unused' => '0'), 'id ASC');

    $toform = array();
    if ($testcases) {
        $i = 0;
        foreach ($testcases as $tstObj => $tstValue) {
            $toform["input[$i]"] = $tstValue->input;
            $toform["output[$i]"] = $tstValue->output;
            $toform["feedback[$i]"] = $tstValue->feedback;
            $toform["subgrade[$i]"] = $tstValue->subgrade;
            $toform["usefile[$i]"] = $tstValue->usefile;

            file_prepare_draft_area($toform["inputfile[$i]"], $context->id, 'mod_assignment', 'onlinejudge_input', $tstValue->id, array('subdirs' => 0, 'maxfiles' => 1));
            file_prepare_draft_area($toform["outputfile[$i]"], $context->id, 'mod_assignment', 'onlinejudge_output', $tstValue->id, array('subdirs' => 0, 'maxfiles' => 1));

            $i++;
        }
    }

	$testform->set_data($toform);
	$testform->display();

	$assignmentinstance->view_footer();
}

function emptycase(&$form, $i) {
    if ($form->subgrade[$i] != 0.0)
        return false;

    if (isset($form->usefile[$i]))
        return empty($form->inputfile[$i]) && empty($form->outputfile[$i]);
    else
        return empty($form->input[$i]) && empty($form->output[$i]);
}
?>
