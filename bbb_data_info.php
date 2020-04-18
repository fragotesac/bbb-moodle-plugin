<?php
/**
 * @author Bruce Garriazo
 */

if (isset($_GET['update'])) {

    $id = required_param('update', PARAM_INT); // Course Module ID, or
    $b = optional_param('return', 0, PARAM_INT); // bigbluebuttonbn instance ID
    $group = optional_param('group', 0, PARAM_INT); // group instance ID

    if ($id) {
        $cm = get_coursemodule_from_id('bigbluebuttonbn', $id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', array('id' => $cm->instance), '*', MUST_EXIST);
    } elseif ($b) {
        $bigbluebuttonbn = $DB->get_record('bigbluebuttonbn', array('id' => $b), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $bigbluebuttonbn->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('bigbluebuttonbn', $bigbluebuttonbn->id, $course->id, false, MUST_EXIST);
    } else {
        print_error(get_string('view_error_url_missing_parameters', 'bigbluebuttonbn'));
    }

    require_login($course, true, $cm);

    $version_major = bigbluebuttonbn_get_moodle_version_major();
    if ($version_major < '2013111800') {
        //This is valid before v2.6.
        $module = $DB->get_record('modules', array('name' => 'bigbluebuttonbn'));
        $module_version = $module->version;
    } else {
        //This is valid after v2.6.
        $module_version = get_config('mod_bigbluebuttonbn', 'version');
    }
    $context = bigbluebuttonbn_get_context_module($cm->id);

// BigBluebuttonBN activity data.
    $bbbsession['bigbluebuttonbn'] = $bigbluebuttonbn;

// JavaScript variables.
    $bigbluebuttonbn_activity = 'open';
    $now = time();
    if (!empty($bigbluebuttonbn->openingtime) && $now < $bigbluebuttonbn->openingtime) {
        // Activity has not been opened.
        $bigbluebuttonbn_activity = 'not_started';
    } else if (!empty($bigbluebuttonbn->closingtime) && $now > $bigbluebuttonbn->closingtime) {
        // Activity has been closed.
        $bigbluebuttonbn_activity = 'ended';
        $bbbsession['presentation'] = bigbluebuttonbn_get_presentation_array($context, $bigbluebuttonbn->presentation);
    } else {
        // Activity is open.
        $bbbsession['presentation'] = bigbluebuttonbn_get_presentation_array($context, $bigbluebuttonbn->presentation, $bigbluebuttonbn->id);
    }
    $waitformoderator_ping_interval = bigbluebuttonbn_get_cfg_waitformoderator_ping_interval();

// Additional info related to the course.
    $bbbsession['course'] = $course;
    $bbbsession['coursename'] = $course->fullname;
    $bbbsession['cm'] = $cm;
    $bbbsession['context'] = $context;

// find out current groups mode.
    $groupmode = groups_get_activity_groupmode($bbbsession['cm']);
    if ($groupmode == NOGROUPS) {  // No groups mode.
        $bbbsession['meetingid'] = $bbbsession['bigbluebuttonbn']->meetingid . '-' . $bbbsession['course']->id . '-' . $bbbsession['bigbluebuttonbn']->id;
    } else {
        $bbbsession['group'] = groups_get_activity_group($bbbsession['cm'], true);
        $bbbsession['meetingid'] = $bbbsession['bigbluebuttonbn']->meetingid . '-' . $bbbsession['course']->id . '-' . $bbbsession['bigbluebuttonbn']->id . '[' . $bbbsession['group'] . ']';
        $group_name = get_string('allparticipants');
        if ($bbbsession['group'] > 0) {
            $group_name = groups_get_group_name($bbbsession['group']);
        }
        $bbbsession['meetingname'] = $bbbsession['bigbluebuttonbn']->name . ' (' . $group_name . ')';
    }

    $jsVars = array(
        'activity' => $bigbluebuttonbn_activity,
        'meetingid' => $bbbsession['meetingid'],
        'bigbluebuttonbnid' => $bbbsession['bigbluebuttonbn']->id,
        'ping_interval' => ($waitformoderator_ping_interval > 0 ? $waitformoderator_ping_interval * 1000 : 15000),
        'userlimit' => $bbbsession['userlimit'],
        'locales' => bigbluebuttonbn_get_locales_for_ui(),
        'opening' => ($bbbsession['openingtime']) ? get_string('mod_form_field_openingtime', 'bigbluebuttonbn') . ': ' . userdate($bbbsession['openingtime']) : '',
        'closing' => ($bbbsession['closingtime']) ? get_string('mod_form_field_closingtime', 'bigbluebuttonbn') . ': ' . userdate($bbbsession['closingtime']) : '',
        'version_major' => $version_major
    );

    $PAGE->requires->data_for_js('bigbluebuttonbn', $jsVars);


    $jsmodule = array(
        'name' => 'mod_bigbluebuttonbn',
        'fullpath' => '/mod/bigbluebuttonbn/module.js',
        'requires' => array('datasource-get', 'datasource-jsonschema', 'datasource-polling'),
    );
    $PAGE->requires->js_init_call('M.mod_bigbluebuttonbn.participantCount', array(), false, $jsmodule);
}
