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
 * Library of interface functions and constants for module tadc
 *
 * @package    mod
 * @subpackage tadc
 * @copyright  2013 Talis Education Ltd.
 * @license    MIT
 */

defined('MOODLE_INTERNAL') || die();

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function tadc_supports($feature) {
    $tadc_cfg = get_config('tadc');
    switch($feature) {
        case FEATURE_MOD_INTRO:         return false;
        case FEATURE_MOD_ARCHETYPE:
            if($tadc_cfg->allow_requests)
            {
                return MOD_ARCHETYPE_RESOURCE;
            } else {
                // If requests aren't allowed in the admin settings, don't show up as an activity or resource
                return MOD_ARCHETYPE_SYSTEM;
            }
        case FEATURE_BACKUP_MOODLE2:    return true;
        default:                        return null;
    }
}

/**
 * Saves a new instance of the tadc into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $tadc An object from the form in mod_form.php
 * @param mod_tadc_mod_form $mform
 * @return int The id of the newly inserted tadc record
 */
function tadc_add_instance(stdClass $tadc, mod_tadc_mod_form $mform = null) {
    require_once(dirname(__FILE__).'/locallib.php');
    global $DB;
    $tadc->id = $DB->insert_record('tadc', $tadc);
    return $tadc->id;
}

/**
 * Updates an instance of the tadc in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $tadc An object from the form in mod_form.php
 * @param mod_tadc_mod_form $mform
 * @return boolean Success/Fail
 */
function tadc_update_instance(stdClass $tadc, mod_tadc_mod_form $mform = null) {
    require_once(dirname(__FILE__).'/locallib.php');
    global $DB;
    $tadc->timemodified = time();
    $tadc->id = $tadc->instance;

    # You may have to add extra stuff in here #
    tadc_form_identifiers_to_resource_identifiers($tadc);
    $instance = $DB->get_record('tadc', array('id'=>$tadc->instance));
    if((!isset($instance->request_status) || $instance->request_status === 'REJECTED') && ($mform && $mform->get_data('resubmit')) || @$tadc->resubmit)
    {
        $tadc->request_status = null;
        $tadc->status_message = null;
        $tadc->reason_code = null;
        $tadc->other_response_data = null;
        $DB->update_record('tadc', $tadc);
        $resource = $DB->get_record('tadc', array('id'=>$tadc->id));
        if(@$tadc->referral_message)
        {
            $resource->referral_message = $tadc->referral_message;
        }
        if(@$tadc->course_start)
        {
            $resource->course_start = $tadc->course_start;
        }
        if(@$tadc->course_end)
        {
            $resource->course_end = $tadc->course_end;
        }
        if(@$tadc->expected_enrollment)
        {
            $resource->expected_enrollment = $tadc->expected_enrollment;
        }
        $response = tadc_submit_request_form($resource);
        tadc_update_resource_with_tadc_response($resource, $response);
        $resource->name = tadc_build_title_string($resource);
        return $DB->update_record('tadc', $resource);
    } else {
        $tadc->name = tadc_build_title_string($tadc);
        return $DB->update_record('tadc', $tadc);
    }

}

/**
 * Removes an instance of the tadc from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function tadc_delete_instance($id) {
    global $DB;

    if (! $tadc = $DB->get_record('tadc', array('id' => $id))) {
        return false;
    }

    # Delete any dependent records here #

    $DB->delete_records('tadc', array('id' => $tadc->id));

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function tadc_user_outline($course, $user, $mod, $tadc) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $tadc the module instance record
 * @return void, is supposed to echp directly
 */
function tadc_user_complete($course, $user, $mod, $tadc) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in tadc activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function tadc_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link tadc_print_recent_mod_activity()}.
 *
 * @param array $activities sequentially indexed array of objects with the 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function tadc_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@see tadc_get_recent_mod_activity()}

 * @return void
 */
function tadc_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 **/
function tadc_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function tadc_get_extra_capabilities() {
    return array();
}

/**
 * Only display resources that are LIVE to non-manager roles
 *
 * @param cm_info $cm
 */
function tadc_cm_info_dynamic(cm_info $cm) {
    global $DB;
    $context = context_module::instance($cm->id);
    if($cm->modname === 'tadc')
    {
        $tadc = $DB->get_record('tadc', array('id'=>$cm->instance));
        if($tadc->request_status !== 'LIVE')
        {
            if(!has_capability('mod/tadc:updateinstance', $context))
            {
                $cm->set_user_visible(false);
            }
        }
    }
}