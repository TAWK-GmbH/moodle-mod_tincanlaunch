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
 * Prints a particular instance of tincanlaunch
 *
 * @package mod_tincanlaunch
 * @copyright  2013 Andrew Downes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require('header.php');

// Trigger module viewed event.
$event = \mod_tincanlaunch\event\course_module_viewed::create(array(
    'objectid' => $tincanlaunch->id,
    'context' => $context,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('tincanlaunch', $tincanlaunch);
$event->add_record_snapshot('course_modules', $cm);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/tincanlaunch/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($tincanlaunch->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$PAGE->requires->jquery();

// Output starts here.
echo $OUTPUT->header();

if ($tincanlaunch->intro) { // Conditions to show the intro can change to look for own settings or whatever.
    echo $OUTPUT->box(
        format_module_intro('tincanlaunch', $tincanlaunch, $cm->id),
        'generalbox mod_introbox',
        'tincanlaunchintro'
    );
}

// TODO: Put all the php inserted data as parameters on the functions and put the functions in a separate JS file.
?>
    <script>
        // Function to test for key press and call launch function if space or enter is hit.
        function key_test(registration) {
            if (event.keyCode === 13 || event.keyCode === 32) {
                mod_tincanlaunch_launchexperience(registration);
            }
        }
        // Function to run when the experience is launched.
        function mod_tincanlaunch_launchexperience(registration) {
            // Set the form paramters.
            $('#launchform_registration').val(registration);
            // Post it.
            $('#launchform').submit();
            // Remove the launch links.
            $('#tincanlaunch_newattempt').remove();
            $('#tincanlaunch_attempttable').remove();
            //Add some new content.
            if (!$('#tincanlaunch_status').length) {
                var message = "<?php echo get_string('tincanlaunch_progress', 'tincanlaunch'); ?>";
                $('#region-main .card-body').append('\
                <div id="tincanlaunch_status"> \
                    <span id="tincanlaunch_completioncheck"></span> \
                    <p id="tincanlaunch_attemptprogress">' + message + '</p> \
                    <p id="tincanlaunch_exit"> \
                        <a class="btn btn-primary" href="complete.php?id=<?php echo $id ?>&n=<?php echo $n ?>" title="Zurück zum Kurs"> \
                            Zurück zum Kurs \
                        </a> \
                    </p> \
                </div>\
            ');
            }
            $('#tincanlaunch_completioncheck').load('completion_check.php?id=<?php echo $id ?>&n=<?php echo $n ?>');
        }

        // TODO: there may be a better way to check completion. Out of scope for current project.
        $(document).ready(function() {
            setInterval(function() {
                $('#tincanlaunch_completioncheck').load('completion_check.php?id=<?php echo $id ?>&n=<?php echo $n ?>');
            }, 30000); // TODO: make this interval a configuration setting.
        });
    </script>
<?php

// Generate a registration id for any new attempt.
$tincanphputil = new \TinCan\Util();
$registrationid = $tincanphputil->getUUID();
$getregistrationdatafromlrsstate = tincanlaunch_get_global_parameters_and_get_state(
    "http://tincanapi.co.uk/stateapikeys/registrations"
);
$lrsrespond = $getregistrationdatafromlrsstate->httpResponse['status'];


if ($lrsrespond != 200 && $lrsrespond != 404) {
    // On clicking new attempt, save the registration details to the LRS State and launch a new attempt.
    echo "<div class='alert alert-error'>" . get_string('tincanlaunch_notavailable', 'tincanlaunch') . "</div>";

    if ($CFG->debug == 32767) {
        echo "<p>Error attempting to get registration data from State API.</p>";
        echo "<pre>";
        var_dump($getregistrationdatafromlrsstate);
        echo "</pre>";
    }
    die();
}

if ($lrsrespond == 200) {
    $registrationdatafromlrs = json_decode($getregistrationdatafromlrsstate->content->getContent(), true);

    foreach ($registrationdatafromlrs as $key => $item) {

        if (!is_array($registrationdatafromlrs[$key])) {
            $reason = "Excepted array, found " . $registrationdatafromlrs[$key];
            throw new moodle_exception($reason, 'tincanlaunch', '', $warnings[$reason]);
        }
        array_push(
            $registrationdatafromlrs[$key],
            "<a tabindex=\"0\" id='tincanrelaunch_attempt'
            onkeyup=\"key_test('".$key."')\" onclick=\"mod_tincanlaunch_launchexperience('".$key."')\" class='btn btn-primary'>"
            . get_string('tincanlaunchviewlaunchlink', 'tincanlaunch') . "</a>"
        );
        
        $registrationdatafromlrs[$key]['created'] = userdate(date_create($registrationdatafromlrs[$key]['created'])->getTimestamp(), '%d. %B %Y, %R');
        $registrationdatafromlrs[$key]['lastlaunched'] = userdate(date_create($registrationdatafromlrs[$key]['lastlaunched'])->getTimestamp(), '%d. %B %Y, %R');
    }
    $table = new html_table();
    $table->id = 'tincanlaunch_attempttable';
    $table->caption = get_string('modulenameplural', 'tincanlaunch');
    $table->head = array(
        get_string('tincanlaunchviewfirstlaunched', 'tincanlaunch'),
        get_string('tincanlaunchviewlastlaunched', 'tincanlaunch'),
        get_string('tincanlaunchviewlaunchlinkheader', 'tincanlaunch')
    );
    $table->data = $registrationdatafromlrs;
    echo html_writer::table($table);
    // Needs to come after previous attempts so a non-sighted user can hear launch options.
    if ($tincanlaunch->tincanmultipleregs) {
        echo "<p id='tincanlaunch_newattempt' style='text-align: center'><button tabindex=\"0\"
        onkeyup=\"key_test('".$registrationid."')\" onclick=\"mod_tincanlaunch_launchexperience('"
            . $registrationid
            . "')\" class=\"btn btn-primary\">"
            . get_string('tincanlaunch_attempt', 'tincanlaunch')
            . "</button></p>";
    }
} else {
    echo "<p tabindex=\"0\"
        onkeyup=\"key_test('".$registrationid."')\"
        id='tincanlaunch_newattempt' style='text-align: center'><button onclick=\"mod_tincanlaunch_launchexperience('"
        . $registrationid
        . "')\" class=\"btn btn-primary\">"
        . get_string('tincanlaunch_attempt', 'tincanlaunch')
        . "</button></p>";
}

// Add a form to be posted based on the attempt selected.
?>
    <form id="launchform" action="launch.php" method="get" target="_blank">
        <input id="launchform_registration" name="launchform_registration" type="hidden" value="default">
        <input id="id" name="id" type="hidden" value="<?php echo $id ?>">
        <input id="n" name="n" type="hidden" value="<?php echo $n ?>">
    </form>
<?php

echo $OUTPUT->footer();
