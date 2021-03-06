<?php /** @noinspection GlobalVariableUsageInspection */
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

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
require_once __DIR__ . '/../../config.php';

class block_assign_get_feedback extends block_base
{
    private $page_url;
    private $cmid;
    private $cm;
    private $course;

    public final function init(): void
    {
        // set the title of this plugin
        try {
            $this->title = get_string('pluginname', 'block_assign_get_feedback');
            $this->page_url = $this->fullpageurl();
            $this->cmid = $this->get_cmid();
        } catch (coding_exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * @return array
     */
    public final function applicable_formats(): array
    {
        return array('all' => TRUE);
    }

    /**
     * @return string
     */
    private function fullpageurl(): string
    {
        if ($this->page_url === NULL) {
            global $_SERVER;
            $pageURL = 'http';
            if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on") {
                $pageURL .= "s";
            }
            $pageURL .= "://";
            if (isset($_SERVER["SERVER_PORT"]) && isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80") {
                $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . ($_SERVER["REQUEST_URI"] ?? '');
            } else {
                $pageURL .= ($_SERVER["SERVER_NAME"] ?? '') . ($_SERVER["REQUEST_URI"] ?? '');
            }
            $this->page_url = $pageURL;
        }
        return $this->page_url;
    }

    /**
     * @return int
     */
    private function get_cmid(): int
    {
        $cmid = 0;
        $params = [];
        $page_url = $this->fullpageurl();
        $page_path = isset(parse_url($page_url)['path']) ? parse_url($page_url)['path'] : '';
        if (strpos($page_path, '/mod/assign/view.php') !== FALSE) {
            $url_query = parse_url($page_url)['query'];
            # split URL query into parameters array
            parse_str($url_query, $params);
            if (isset($params['id']) && (int)$params['id'] > 0) {
                $cmid = (int)$params['id'];
                try {
                    list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'assign');
                    if ($course) {
                        $this->course = $course;
                    }
                    if ($cm) {
                        $this->cm = $cm;
                    }
                } catch (Exception $exception) {
                    error_log($exception->getMessage());
                }
            }
        }
        return $cmid;
    }

    /**
     * Method               get_content
     *
     * Purpose              create all the block contents and present it
     *                      Subscriptions Block Contents creation function
     *
     * Parameters           N/A
     *
     * Returns
     * @return              string, as HTML content for the block
     *
     */
    public final function get_content(): ?stdClass
    {
        // define usage of global variables
        global $PAGE; //, $COURSE, $DB , $CFG ; // $USER, $SITE , $OUTPUT, $THEME, $OUTPUT ;

        // Check if the page is referring to an assign module grading page
        if ('mod-assign-grading' !== $PAGE->pagetype) {
            return $this->content;
        }

        if (NULL !== $this->title) {
            try {
                $this->title = get_string('blockheader', 'block_assign_get_feedback');
            } catch (coding_exception $e) {
                error_log($e->getMessage());
            }
        }

        // if the contents are already set, just return them
        if ($this->content !== NULL) {
            return $this->content;
        } else error_log('Content is not null :' . print_r($this->content, TRUE));

        // this is only for logged in users
        try {
            if (!isloggedin() || isguestuser()) {
                return NULL;
            }
        } catch (coding_exception $e) {
            error_log($e->getMessage());
        }

        // get the current moodle configuration
        require_once __DIR__ . '/../../config.php';

        // this is only for logged in users
        try {
            require_login();
        } catch (coding_exception $e) {
            error_log($e->getMessage());
        } catch (require_login_exception $e) {
            error_log($e->getMessage());
        } catch (moodle_exception $e) {
            error_log($e->getMessage());
        }

        // prapare for contents
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->text .= '<strong>' . $PAGE->title . '</strong>';

        // add a footer for the block
        try {
            $this->content->footer = '<hr style="display: block!important;"/><div style="text-align:center;">' . get_string('blockfooter', 'block_assign_get_feedback') . '</div>';
        } catch (coding_exception $e) {
            error_log($e->getMessage());
        }


        // get the Course Module ID
        $cmid = $this->get_cmid();
        #error_log("The cmid is $cmid");

        // check if there is a valid glossary view page
        if ($cmid > 0) {
            // set page context
            try {
                $PAGE->set_context(context_module::instance($cmid));
            } catch (Throwable $e) {
                error_log('ERROR: assign_get_feedback set_context ' . $e->getMessage());
                return $this->content;
            }

            // Check if the course module is available and it is visible and it is visible to the user and it is an assign module
            if (!(1 == $this->is_visible($cmid))) {
                return $this->content;
            }

            // get to feedback comments
            $links = $this->show_links($cmid);

            // add the contents of the feedback comments to the block
            $this->content->text .= $links;
        }
        // Finish and return contents
        return $this->content;
    }

    /**
     * @param int $cmid
     * @return int
     */
    private final function is_visible(int $cmid): int
    {
        global $DB;
        $visibility = 0;
        try {
            $visibility = $DB->get_field('course_modules', 'visible', ['id' => $cmid]);
            # error_log('VISIBILITY_1_OK :'.print_r($visibility));
        } catch (Exception $exception) {
            error_log('VISIBILITY_1_ERR:' . print_r($exception, TRUE)); #->getMessage());
        }
        return $visibility;
    }

    private function show_feedback_comments_link(int $cmid): string
    {
        global $DB, $CFG;
        $html = '';
        if ($cmid > 0) {
            $stu = $DB->sql_concat_join("' '", ['cm.course', 'co.shortname', 'cm.id', 'ma.name', 'ac.assignment']);
            $tea = $DB->sql_concat_join("' '", ['tea.idnumber', 'tea.username', 'tea.firstname', 'tea.lastname']);
            $sql = /** @lang TEXT */
                "
SELECT 
       ag.id, $stu as student, $tea as teacher, ag.grade, ac.commenttext 
FROM {course_modules} AS cm 
JOIN {assign} AS ma ON ma.id = cm.instance
JOIN {modules} AS mo ON mo.id = cm.module  
JOIN {course} AS co ON co.id = cm.course 
JOIN {assignfeedback_comments} AS ac ON ac.assignment = ma.id 
JOIN {assign_grades} AS ag ON ag.id = ac.grade 
JOIN {user} AS stu ON stu.id = ag.userid 
JOIN {user} AS tea ON tea.id = ag.grader 
WHERE mo.name  = :module AND cm.id = :cmid ";
            try {
                $records = $DB->get_records_sql($sql, ['module' => 'assign', 'cmid' => $cmid]);
                if ($records) {
                    $action = new moodle_url('/blocks/assign_get_feedback/feedback_comments.php', ['id' => $cmid, 'sesskey' => sesskey()]);
                    $html .= '<p>' . html_writer::link($action, get_string('feedback_comments', 'block_assign_get_feedback'), ['target' => '_blank']) . '</p>';
                } else {
                    $html .= '<p>' . get_string('no_feedback_comments', 'block_assign_get_feedback') . '</p>';
                }
            } catch (Exception $exception) {
                if ($CFG->debug) {
                    error_log('COMMENTS_SQL_ERR ' . print_r($exception, TRUE));
                }
            }
        }
        return $html;
    }

    private function show_links(int $cmid): string
    {

        $html = '';
        $html .= $this->show_feedback_comments_link($cmid);
        return $html;
    }
}
