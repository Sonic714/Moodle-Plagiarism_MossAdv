<?php
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
//                   Moss Anti-Plagiarism for Moodle                     //
//         github available          //
//                                                                       //
// Copyright (C) 2009 onwards  Sun Zhigang  http://sunner.cn             //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////


namespace plagiarism_moss\task;
defined('MOODLE_INTERNAL') || die();


class clear extends \core\task\scheduled_task {
    /**
     * Returns the name of this task.
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('clear', 'moss');
    }
    /**
     * Executes task.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot.'/plagiarism/lib.php');
        require_once($CFG->dirroot.'/plagiarism/moss/locallib.php');
        
        mtrace('---Moss begins---');
        
        moss_clean_noise();
        moss_measure_all();
        
        mtrace('---Moss done---');
    }
        
}