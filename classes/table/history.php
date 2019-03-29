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
 * Proxy lock factory, history table.
 *
 * @package    tool_lockstats
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lockstats\table;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once($CFG->libdir.'/tablelib.php');

use html_writer;
use moodle_url;
use stdClass;
use table_sql;

/**
 * Proxy lock factory, history table.
 *
 * @package    tool_lockstats
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history extends table_sql {
    /** @var int Incrementing table id. */
    private static $autoid = 0;

    /**
     * Constructor
     * @param moodle_url $baseurl
     * @param string|null $id to be used by the table, autogenerated if null.
     */
    public function __construct($baseurl, $id = null) {

        $id = (is_null($id) ? self::$autoid++ : $id);
        parent::__construct('tool_lockstats_history' . $id);

        $columns = array(
            'duration'  => get_string('table_duration', 'tool_lockstats'),
            'classname'      => get_string('name'),
        );

        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));

        $this->define_baseurl($baseurl);

        $this->set_attribute('class', 'generaltable admintable');
        $this->set_attribute('cellspacing', '0');

        $this->sortable(true, 'duration', SORT_DESC);

        $this->collapsible(false);

        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);

        $select = '*';
        $from = '(
          SELECT max(id) id,
                 max(taskid) taskid,
                 classname,
                 max(duration / lockcount) duration
            FROM {tool_lockstats_history}
           WHERE duration > 0
             AND lockcount > 0
             AND (duration / lockcount) > :threshold
             AND released > :releasedafter
        GROUP BY classname
        ) sub';
        $where = ' 1 = 1 ';
        $params = [
            'threshold'  => get_config('tool_lockstats', 'threshold'),
            'releasedafter'  => time() - 7 * 24 * 60 * 60,
        ];

        $this->set_sql($select, $from, $where, $params);
    }

    /**
     * Download the data.
     */
    public function download() {
        global $DB;

        $total = $DB->count_records_sql('SELECT COUNT(id) from {tool_lockstats_history}');
        $this->out($total, false);
    }


    /**
     * The time the lock was held for.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_duration($values) {
        $lockcount = null;
        $duration = null;

        if (isset($values->lockcount)) {
            $lockcount = $values->lockcount;
        }

        if (isset($values->duration)) {
            $duration = $values->duration;
        }

        if ($lockcount > 0) {
            $duration = sprintf('%.4f', $values->duration / $values->lockcount);
        }

        if ($this->is_downloading()) {
            return $duration;
        }

        return format_time($duration);
    }

    /**
     * A link to the task.
     *
     * @param stdClass $values
     * @return string
     */
    public function col_classname($values) {

        if ($this->is_downloading()) {
            return $values->taskid;
        }

        $url = new moodle_url("/admin/tool/lockstats/detail.php", [
            'task' => $values->taskid,
            'tsort' => 'duration',
        ]);

        $classname = explode("\\", $values->classname);
        $link = ucwords(str_replace("_", " ", end($classname)));
        $link = html_writer::link($url, $link)
            . "\n" . html_writer::tag('span', $values->classname, ['class' => 'task-class']);

        return $link;
    }

}
